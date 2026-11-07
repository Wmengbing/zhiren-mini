<?php
declare(strict_types=1);
require __DIR__ . '/lib/database-transfer.php';
$path = $argv[1] ?? '';
if (!is_file($path)) throw new RuntimeException('Usage: php bin/restore-database.php backup.json.gz');
$pdo = transferPdo();
if ($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('Restore requires an EMPTY database; existing data was not changed');
$read = function (callable $visitor) use ($path): void {
    $handle = gzopen($path, 'rb');
    if (!$handle) throw new RuntimeException('Cannot read backup');
    $header = json_decode((string) gzgets($handle), true, 512, JSON_THROW_ON_ERROR);
    if (($header['format'] ?? '') !== 'zhiren-db-v1' || ($header['prefix'] ?? null) !== (string) env('DATABASE.PREFIX', '')) throw new RuntimeException('Backup format or table prefix does not match');
    $allowed = transferTables();
    $complete = false;
    try {
        while (($line = gzgets($handle)) !== false) {
            $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (($entry['complete'] ?? false) === true) { $complete = true; break; }
            if (!in_array($entry['table'] ?? '', $allowed, true)) throw new RuntimeException('Unexpected backup table');
            $visitor($entry);
        }
        if (!$complete) throw new RuntimeException('Backup is incomplete; restore aborted');
    } finally { gzclose($handle); }
};
// Validate the complete stream before creating any tables.
$read(function (array $entry): void {});
$read(function (array $entry) use ($pdo): void {
    if (isset($entry['ddl'])) {
        // Only restore files produced by this project's backup tool from a trusted source.
        $pdo->exec($entry['ddl']);
    } elseif (isset($entry['row'])) {
        $keys = array_keys($entry['row']);
        foreach ($keys as $key) if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) throw new RuntimeException('Invalid column');
        $statement = $pdo->prepare('INSERT INTO ' . $entry['table'] . ' (' . implode(',', array_map(fn($key) => chr(96) . $key . chr(96), $keys)) . ') VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $statement->execute(array_values($entry['row']));
    }
});
echo "Database restored. Run php bin/migrate.php next.\n";
