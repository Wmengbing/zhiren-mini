<?php
// Switch generated demo image URLs to the white palette; preserve user uploads.
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$app = new \think\App();
$app->initialize();

use think\facade\Db;

$fields = ['banner' => 'image_url', 'product_category' => 'image_url', 'product' => 'cover_url', 'article' => 'cover_url', 'company_page' => 'image_url'];
$changes = [];
foreach ($fields as $table => $field) {
    foreach (Db::name($table)->whereLike($field, '%/uploads/demo-%')->field(['id', $field])->select()->toArray() as $row) {
        if (!preg_match('#/uploads/(?:demo-v1|demo-industrial-v2)/#', $row[$field])) continue;
        $file = basename((string) parse_url($row[$field], PHP_URL_PATH));
        if (!is_file(dirname(__DIR__) . '/public/uploads/demo-white-v3/' . $file)) {
            throw new RuntimeException('New demo image missing: ' . $file);
        }
        $changes[] = ['table' => $table, 'field' => $field, 'id' => $row['id'], 'before' => $row[$field], 'after' => preg_replace('#/uploads/(?:demo-v1|demo-industrial-v2)/#', '/uploads/demo-white-v3/', $row[$field])];
    }
}
if (!$changes) { echo "No demo image URLs need changing.\n"; exit(0); }
$backupDir = dirname(__DIR__) . '/runtime/demo-backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$backup = $backupDir . '/white-image-urls-' . date('Ymd-His') . '.json';
if (file_put_contents($backup, json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot save image URL backup.');
}
Db::transaction(function () use ($changes): void {
    foreach ($changes as $change) {
        Db::name($change['table'])->where('id', $change['id'])->where($change['field'], $change['before'])->update([$change['field'] => $change['after']]);
    }
});
echo count($changes), ' generated demo image URLs updated; backup: ', $backup, PHP_EOL;
