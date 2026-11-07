<?php
declare(strict_types=1);
require __DIR__ . '/lib/database-transfer.php';
$options = getopt('', ['from:', 'to:', 'apply']);
$from = rtrim($options['from'] ?? 'http://127.0.0.1:8000', '/');
$to = rtrim($options['to'] ?? '', '/');
if (!filter_var($from, FILTER_VALIDATE_URL) || !filter_var($to, FILTER_VALIDATE_URL) || parse_url($to, PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('Usage: php bin/rebase-media.php --to=https://api.your-domain.com [--from=http://127.0.0.1:8000] [--apply]');
}
$pdo = transferPdo();
$prefix = (string) env('DATABASE.PREFIX', '');
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid prefix');
$columns = [
    'banner' => ['image_url'], 'company_page' => ['image_url'], 'product_category' => ['image_url'],
    'product' => ['cover_url', 'gallery_json'], 'article' => ['cover_url'],
    'site_setting' => ['value'], 'official_content_source' => ['metadata'],
];
// Match complete URL prefixes, and cover escaped JSON produced by older imports.
$pairs = [$from . '/' => $to . '/', str_replace('/', '\/', $from . '/') => str_replace('/', '\/', $to . '/')];
$total = 0;
$pdo->beginTransaction();
try {
    foreach ($columns as $table => $fields) {
        foreach ($pdo->query('SELECT * FROM ' . $prefix . $table) as $row) {
            $changes = [];
            foreach ($fields as $field) {
                if (!is_string($row[$field])) continue;
                $new = strtr($row[$field], $pairs);
                if ($new !== $row[$field]) $changes[$field] = $new;
            }
            if (!$changes) continue;
            $total++;
            if (isset($options['apply'])) {
                $statement = $pdo->prepare('UPDATE ' . $prefix . $table . ' SET ' . implode(',', array_map(fn($field) => chr(96) . $field . chr(96) . '=?', array_keys($changes))) . ' WHERE id=?');
                $statement->execute([...array_values($changes), $row['id']]);
            }
        }
    }
    if (isset($options['apply'])) $pdo->commit(); else $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo (isset($options['apply']) ? 'Updated' : 'Preview only') . ': ' . $total . " records. Product names and categories were preserved.\n";
