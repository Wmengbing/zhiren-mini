<?php
declare(strict_types=1);
require __DIR__ . '/lib/database-transfer.php';
$path = $argv[1] ?? dirname(__DIR__) . '/runtime/backups/database-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json.gz';
if (is_file($path)) throw new RuntimeException('Backup already exists');
if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) throw new RuntimeException('Cannot create backup directory');
$handle = gzopen($path, 'wb6');
if (!$handle) throw new RuntimeException('Cannot write backup');
chmod($path, 0600);
$write = function (array $row) use ($handle): void {
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (gzwrite($handle, $line) !== strlen($line)) throw new RuntimeException('Backup write failed');
};
$pdo = transferPdo();
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction();
try {
    $write(['format' => 'zhiren-db-v1', 'created_at' => date(DATE_ATOM), 'prefix' => (string) env('DATABASE.PREFIX', '')]);
    foreach (transferTables() as $table) {
        $ddl = $pdo->query("SHOW CREATE TABLE " . $table)->fetch(PDO::FETCH_NUM)[1];
        $write(['table' => $table, 'ddl' => $ddl]);
        // Sessions and failed-login counters are intentionally not migrated.
        if (in_array($table, [(string) env('DATABASE.PREFIX', '') . 'admin_session', (string) env('DATABASE.PREFIX', '') . 'admin_login_limit'], true)) continue;
        $rows = $pdo->query("SELECT * FROM " . $table);
        while ($row = $rows->fetch()) $write(['table' => $table, 'row' => $row]);
    }
    $write(['complete' => true]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    gzclose($handle);
    unlink($path);
    throw $error;
}
gzclose($handle);
echo "Database backup created: " . $path . "\n";
