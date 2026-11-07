<?php
// Preview: php bin/import-official.php --dry-run
// Import:  php bin/import-official.php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use think\facade\Db;

$app = new \think\App();
$app->initialize();
$apiRoot = dirname(__DIR__);
$curated = require $apiRoot . '/database/official-content.php';
$prepared = json_decode(file_get_contents($apiRoot . '/database/official-site/prepared.json'), true, 512, JSON_THROW_ON_ERROR);
$displayNames = is_file($apiRoot . '/database/official-site/product-display-names.json') ? json_decode(file_get_contents($apiRoot . '/database/official-site/product-display-names.json'), true, 512, JSON_THROW_ON_ERROR)['products'] : [];
$assets = json_decode(file_get_contents($apiRoot . '/database/official-site/assets.json'), true, 512, JSON_THROW_ON_ERROR);
$baseUrl = rtrim((string) env('SITE.PUBLIC_BASE_URL', 'http://127.0.0.1:8000'), '/');
$now = date('Y-m-d H:i:s');
foreach ($assets as $url => $asset) {
    $path = $apiRoot . '/public' . $asset['relative_url'];
    if (!is_file($path) || filesize($path) !== $asset['bytes']) throw new RuntimeException('Missing/incomplete official asset: ' . $url);
}
function assetUrl(string $source): string
{
    global $assets, $baseUrl;
    if (!isset($assets[$source])) throw new RuntimeException('Unknown official asset: ' . $source);
    return $baseUrl . $assets[$source]['relative_url'];
}
function officialRecord(string $table, string $sourceId, string $sourceUrl, array $record, array $metadata = [], ?array $match = null): int
{
    global $now;
    $key = hash('sha256', $sourceId);
    $source = Db::name('official_content_source')->where('resource', $table)->where('source_key', $key)->find();
    $id = $source ? (int) $source['record_id'] : 0;
    if ($id && !Db::name($table)->where('id', $id)->find()) $id = 0;
    if (!$id && $match) $id = (int) (Db::name($table)->where($match)->value('id') ?: 0);
    if ($id) Db::name($table)->where('id', $id)->update($record + ['updated_at' => $now]);
    else $id = (int) Db::name($table)->insertGetId($record + ['created_at' => $now, 'updated_at' => $now]);
    $sourceRecord = ['resource' => $table, 'record_id' => $id, 'source_key' => $key, 'source_id' => $sourceId, 'source_url' => $sourceUrl, 'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'imported_at' => $now];
    if ($source) Db::name('official_content_source')->where('id', $source['id'])->update($sourceRecord);
    else Db::name('official_content_source')->insert($sourceRecord);
    return $id;
}
$demoProducts = Db::name('product')->whereLike('title', '【演示】%')->column('id');
$plan = ['official_products' => count($prepared['products']), 'official_categories' => count($curated['categories']), 'official_articles' => count($curated['articles']), 'company_pages' => count($curated['company_pages']), 'production_videos' => count($prepared['videos']), 'demo_products_to_replace' => count($demoProducts), 'demo_articles_to_replace' => Db::name('article')->whereLike('title', '【演示】%')->count(), 'existing_non_demo_products' => Db::name('product')->whereNotLike('title', '【演示】%')->count()];
foreach ($prepared['products'] as $product) {
    if (!isset($curated['product_titles'][$product['title']])) throw new RuntimeException('Product title needs translation: ' . $product['title']);
    $found = false;
    foreach ($curated['categories'] as $definition) if ($definition['english'] === $product['source_category']) $found = true;
    if (!$found) throw new RuntimeException('Unknown source category: ' . $product['source_category']);
}
if (in_array('--dry-run', $argv, true)) { echo json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL; exit(0); }

$backup = [];
foreach (['product', 'product_category', 'article', 'banner', 'company_page', 'site_setting'] as $table) $backup[$table] = Db::name($table)->select()->toArray();
$backup['inquiry_product_references'] = Db::name('inquiry')->field(['id', 'product_id'])->select()->toArray();
$backupDir = $apiRoot . '/runtime/official-backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$backupPath = $backupDir . '/before-official-import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
if (file_put_contents($backupPath, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot save import backup.');
Db::execute(file_get_contents($apiRoot . '/database/official-content-source.sql'));
$result = Db::transaction(function () use ($prepared, $curated, $displayNames, $demoProducts, $now): array {
    if ($demoProducts) {
        Db::name('inquiry')->whereIn('product_id', $demoProducts)->update(['product_id' => null]);
        Db::name('product')->whereIn('id', $demoProducts)->delete();
    }
    Db::name('article')->whereLike('title', '【演示】%')->delete();
    Db::name('banner')->whereLike('title', '【演示】%')->delete();
    Db::name('company_page')->whereIn('slug', ['demo-process', 'demo-applications'])->delete();
    foreach (['阀类', '管件', '建筑五金', '船用五金', '厨具配件', '医疗器械配件', '机械配件'] as $name) {
        $old = Db::name('product_category')->where('name', $name)->find();
        if ($old && !Db::name('product')->where('category_id', $old['id'])->count()) Db::name('product_category')->where('id', $old['id'])->delete();
    }
    $categoryIds = [];
    $firstCover = [];
    $categoryIndex = 0;
    foreach ($curated['categories'] as $slug => $category) {
        $sourceUrl = $curated['origin'] . '/Product/' . $slug . '.html';
        $first = null;
        foreach ($prepared['products'] as $product) if ($product['source_category'] === $category['english']) { $first = $product; break; }
        if (!$first) throw new RuntimeException('Empty official category: ' . $slug);
        $firstCover[$slug] = assetUrl($first['cover']);
        $categoryIds[$category['english']] = officialRecord('product_category', $slug, $sourceUrl, ['name' => $category['name'], 'description' => $category['description'], 'image_url' => $firstCover[$slug], 'sort' => 100 - $categoryIndex++ * 10, 'status' => 1], [], ['name' => $category['name']]);
    }
    $perCategory = [];
    $firstProductId = 0;
    foreach ($prepared['products'] as $index => $product) {
        $category = null;
        foreach ($curated['categories'] as $definition) if ($definition['english'] === $product['source_category']) $category = $definition;
        $perCategory[$product['source_category']] = ($perCategory[$product['source_category']] ?? 0) + 1;
        $gallery = array_values(array_unique(array_map('assetUrl', $product['images'])));
        $display = $displayNames[$product['source_id']] ?? null;
        $id = officialRecord('product', $product['source_id'], $product['source_url'], [
            'category_id' => $categoryIds[$product['source_category']], 'title' => $display['title'] ?? $curated['product_titles'][$product['title']],
            'subtitle' => $category['name'] . ' · 精密铸造', 'cover_url' => assetUrl($product['cover']),
            'summary' => $category['description'], 'features' => $category['features'], 'specifications' => '', 'application' => $category['application'],
            'sort' => 1000 - $index, 'is_featured' => $perCategory[$product['source_category']] === 1 && $product['source_category'] !== 'Cupronickel Casting' ? 1 : 0, 'status' => 1,
        ], ['original_title' => $product['title'], 'original_category' => $product['source_category'], 'gallery' => $gallery, 'display_name' => $display['title'] ?? '', 'display_name_basis' => $display['basis'] ?? 'official_title', 'display_name_note' => $display['note'] ?? '']);
        if (!$firstProductId) $firstProductId = $id;
    }
    foreach ($curated['company_pages'] as $index => $page) {
        officialRecord('company_page', $page['slug'], $curated['origin'] . $page['source'], ['slug' => $page['slug'], 'title' => $page['title'], 'summary' => mb_substr($page['content'], 0, 120), 'content' => $page['content'], 'image_url' => assetUrl($prepared['named_images'][$page['image']]), 'sort' => 100 - $index * 5, 'status' => 1], [], ['slug' => $page['slug']]);
    }
    foreach ($curated['articles'] as $index => $article) {
        $url = $curated['origin'] . $article['url'];
        officialRecord('article', $url, $url, ['title' => $article['title'], 'summary' => $article['summary'], 'content' => $article['content'], 'cover_url' => $firstCover[$article['category']], 'published_at' => $article['date'] . ' 00:00:00', 'sort' => 100 - $index, 'status' => 1], ['language' => 'zh-CN', 'adaptation' => 'Chinese summary of the official English article']);
    }
    foreach ([['official-factory', '走进志人精密', 'factory', 'company', ''], ['official-production', '硅溶胶精密铸造与数字化制造', 'manufacturing', 'company', ''], ['official-products', '探索铜合金精密铸件', null, 'product', (string) $firstProductId]] as $index => [$key, $title, $image, $linkType, $linkValue]) {
        $url = $image ? assetUrl($prepared['named_images'][$image]) : $firstCover['AluminumBronzeCasting'];
        officialRecord('banner', $key, $curated['origin'] . '/', ['title' => $title, 'image_url' => $url, 'link_type' => $linkType, 'link_value' => $linkValue, 'sort' => 100 - $index * 10, 'status' => 1]);
    }
    $videos = [];
    foreach ($prepared['videos'] as $video) $videos[] = ['title' => $video['title'], 'url' => assetUrl($video['source_url']), 'poster' => assetUrl($prepared['named_images']['manufacturing'])];
    $settings = $curated['settings'] + [
        'logo_url' => [assetUrl($prepared['named_images']['logo']), '公司 Logo'],
        'hero_image_url' => [assetUrl($prepared['named_images']['manufacturing']), '首页主视觉'],
        'company_image_url' => [assetUrl($prepared['named_images']['factory']), '公司介绍主图'],
        'production_videos' => [json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), '生产视频资料'],
    ];
    foreach ($settings as $key => [$value, $label]) {
        $existing = Db::name('site_setting')->where('key', $key)->find();
        if ($existing) Db::name('site_setting')->where('id', $existing['id'])->update(['value' => $value, 'label' => $label]);
        else Db::name('site_setting')->insert(['key' => $key, 'value' => $value, 'label' => $label]);
    }
    $hours = Db::name('site_setting')->where('key', 'work_hours')->find();
    if ($hours && str_starts_with($hours['value'], '演示')) Db::name('site_setting')->where('id', $hours['id'])->update(['value' => '']);
    return ['categories' => count($categoryIds), 'products' => count($prepared['products']), 'articles' => count($curated['articles']), 'company_pages' => count($curated['company_pages']), 'videos' => count($videos)];
});
echo json_encode(['imported' => $result, 'backup' => $backupPath, 'preserved_original_categories_for_conflicting_titles' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
