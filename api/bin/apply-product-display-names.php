<?php
// Update product titles only; preserve existing content, category and images.
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use think\facade\Db;

$app = new \think\App();
$app->initialize();
$apiRoot = dirname(__DIR__);
$definitions = json_decode(file_get_contents($apiRoot . '/database/official-site/product-display-names.json'), true, 512, JSON_THROW_ON_ERROR)['products'];
$curated = require $apiRoot . '/database/official-content.php';
$changes = [];
$preserved = [];
foreach ($definitions as $sourceId => $definition) {
    $source = Db::name('official_content_source')->where('resource', 'product')->where('source_id', (string) $sourceId)->find();
    if (!$source) throw new RuntimeException('Product source missing: ' . $sourceId);
    $product = Db::name('product')->where('id', $source['record_id'])->find();
    if (!$product) { $preserved[] = ['source_id' => $sourceId, 'reason' => 'Product already removed']; continue; }
    $metadata = json_decode($source['metadata'], true, 512, JSON_THROW_ON_ERROR);
    $originalDisplay = $curated['product_titles'][$metadata['original_title']] ?? '';
    if (!in_array($product['title'], [$originalDisplay, $definition['title'], $metadata['display_name'] ?? ''], true)) {
        $preserved[] = ['id' => $product['id'], 'title' => $product['title'], 'reason' => 'Preserved manually edited title'];
        continue;
    }
    $nextMetadata = $metadata;
    $nextMetadata['display_name'] = $definition['title'];
    $nextMetadata['display_name_basis'] = $definition['basis'];
    $nextMetadata['display_name_note'] = $definition['note'];
    $nextMetadata['display_name_reviewed_at'] = date('Y-m-d H:i:s');
    $changes[] = ['id' => $product['id'], 'source_id' => (string) $sourceId, 'source_record_id' => $source['id'], 'before_title' => $product['title'], 'after_title' => $definition['title'], 'before_metadata' => $metadata, 'after_metadata' => $nextMetadata];
}
if (in_array('--dry-run', $argv, true)) {
    echo json_encode(['updates' => count($changes), 'preserved' => $preserved, 'samples' => array_map(fn($row) => ['id' => $row['id'], 'before' => $row['before_title'], 'after' => $row['after_title']], array_slice($changes, 0, 8))], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}
$dir = $apiRoot . '/runtime/product-name-review';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$backup = $dir . '/before-product-names-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
if (file_put_contents($backup, json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot back up product names.');
Db::transaction(function () use ($changes): void {
    foreach ($changes as $change) {
        Db::name('product')->where('id', $change['id'])->update(['title' => $change['after_title'], 'updated_at' => date('Y-m-d H:i:s')]);
        Db::name('official_content_source')->where('id', $change['source_record_id'])->update(['metadata' => json_encode($change['after_metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }
});
echo json_encode(['updated' => count($changes), 'preserved' => $preserved, 'backup' => $backup], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
