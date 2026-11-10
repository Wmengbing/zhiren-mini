<?php
// One-time conversion of local media URLs to /uploads/... paths.
// Preview: php bin/normalize-media-paths.php
// Apply after a database backup: php bin/normalize-media-paths.php --apply
declare(strict_types=1);
require __DIR__ . '/lib/database-transfer.php';

use app\support\MediaUrl;

$apply = in_array('--apply', $argv, true);
$prefix = (string) env('DATABASE.PREFIX', '');
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid database prefix');
$columns = [
    'banner' => ['image_url'],
    'company_page' => ['image_url'],
    'product_category' => ['image_url'],
    'product' => ['cover_url', 'gallery_json'],
    'article' => ['cover_url'],
    'site_setting' => ['value'],
    'official_content_source' => ['metadata'],
];
$jsonFields = ['gallery_json', 'metadata'];
$settingImages = ['logo_url', 'hero_image_url', 'company_image_url', 'wechat_qr'];
$baseUrl = (string) env('SITE.PUBLIC_BASE_URL', '');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL)
    || !in_array(strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
    throw new RuntimeException('Configure SITE.PUBLIC_BASE_URL before normalizing media paths');
}
$pdo = transferPdo();
$changedRecords = 0;
$changedFields = 0;
$samples = [];
$pdo->beginTransaction();
try {
    foreach ($columns as $table => $fields) {
        $name = '`' . $prefix . $table . '`';
        foreach ($pdo->query('SELECT * FROM ' . $name) as $row) {
            $changes = [];
            foreach ($fields as $field) {
                if (!is_string($row[$field] ?? null)) continue;
                if ($table === 'site_setting' && $field === 'value'
                    && !in_array($row['key'], [...$settingImages, 'production_videos'], true)) continue;
                $value = $row[$field];
                if (in_array($field, $jsonFields, true)
                    || ($table === 'site_setting' && $row['key'] === 'production_videos')) {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $normalized = MediaUrl::normalize($decoded, $baseUrl);
                    // Re-encoding alone is not a migration.
                    if ($normalized === $decoded) continue;
                    $new = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                } else {
                    $new = MediaUrl::forStorage($value, $baseUrl);
                }
                if ($new !== $value) $changes[$field] = $new;
            }
            if (!$changes) continue;
            $changedRecords++;
            $changedFields += count($changes);
            if (count($samples) < 8) $samples[] = $table . '#' . $row['id'];
            if ($apply) {
                $assignments = implode(',', array_map(fn($field) => '`' . $field . '`=?', array_keys($changes)));
                $statement = $pdo->prepare('UPDATE ' . $name . ' SET ' . $assignments . ' WHERE id=?');
                $statement->execute([...array_values($changes), $row['id']]);
            }
        }
    }
    if ($apply) $pdo->commit(); else $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo ($apply ? 'Updated' : 'Preview only') . ': ' . $changedRecords . ' records, ' . $changedFields . " fields.\n";
if ($samples) echo 'Samples: ' . implode(', ', $samples) . "\n";
