<?php
// Preview: php bin/import-jxzrcasting.php
// Apply after a database backup: php bin/import-jxzrcasting.php --apply
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use think\facade\Db;

(new \think\App())->initialize();
$root = dirname(__DIR__);
$dataDir = $root . '/database/jxzrcasting';
$manifest = json_decode(file_get_contents($dataDir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$digests = require $dataDir . '/article-digests.php';
$origin = 'https://www.jxzrcasting.com';
if (($manifest['origin'] ?? '') !== $origin || count($manifest['categories'] ?? []) !== 8
    || count($manifest['products'] ?? []) < 90 || count($manifest['articles'] ?? []) !== 15) {
    throw new RuntimeException('Unexpected jxzrcasting snapshot; import stopped.');
}
foreach ($manifest['articles'] as $article) {
    if (!isset($digests[$article['slug']])) throw new RuntimeException('Missing Chinese digest: ' . $article['slug']);
}
$assetPath = static function (string $url) use ($manifest): string {
    if (!isset($manifest['assets'][$url])) throw new RuntimeException('Missing image in snapshot: ' . $url);
    return $manifest['assets'][$url]['relative_url'];
};
$plan = [
    'source' => $origin,
    'categories' => count($manifest['categories']),
    'products' => count($manifest['products']),
    'articles' => count($manifest['articles']),
    'images' => count($manifest['assets']),
    'previous_source_products' => Db::name('official_content_source')->where('resource', 'product')
        ->whereLike('source_url', 'https://www.zhirencasting.com/%')->count(),
    'customer_inquiries_preserved' => Db::name('inquiry')->count(),
];
if (!in_array('--apply', $argv, true)) {
    echo json_encode(['preview' => $plan], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}

// Copy and verify all images before changing any database record.
$destination = $root . '/public/uploads/jxzrcasting';
if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new RuntimeException('Cannot create media directory');
foreach ($manifest['assets'] as $url => $asset) {
    if (!str_starts_with($url, $origin . '/') || !preg_match('/^[a-f0-9]{20}\.(?:jpe?g|png|webp)$/', $asset['file'])) {
        throw new RuntimeException('Invalid image entry in snapshot');
    }
    $source = $dataDir . '/assets/' . $asset['file'];
    $target = $destination . '/' . $asset['file'];
    if (!is_file($source) || filesize($source) !== $asset['bytes'] || hash_file('sha256', $source) !== $asset['sha256']) {
        throw new RuntimeException('Snapshot image missing or changed: ' . $asset['file']);
    }
    if (!is_file($target) || hash_file('sha256', $target) !== $asset['sha256']) {
        if (!copy($source, $target) || hash_file('sha256', $target) !== $asset['sha256']) {
            throw new RuntimeException('Cannot install image: ' . $asset['file']);
        }
    }
}

$now = date('Y-m-d H:i:s');
function upsertOfficial(string $resource, string $slug, string $url, array $record, array $metadata = [], ?array $match = null): int
{
    global $now;
    $tables = ['product_category', 'product', 'article', 'company_page', 'banner'];
    if (!in_array($resource, $tables, true)) throw new RuntimeException('Unsupported resource');
    $sourceId = 'jxzrcasting:' . $resource . ':' . $slug;
    if (strlen($sourceId) > 100) throw new RuntimeException('Source ID too long: ' . $sourceId);
    $key = hash('sha256', $sourceId);
    $source = Db::name('official_content_source')->where('resource', $resource)->where('source_key', $key)->find();
    $id = $source ? (int) $source['record_id'] : 0;
    if ($id && !Db::name($resource)->where('id', $id)->find()) $id = 0;
    if (!$id && $match) $id = (int) (Db::name($resource)->where($match)->value('id') ?: 0);
    if ($id) Db::name($resource)->where('id', $id)->update($record + ['updated_at' => $now]);
    else $id = (int) Db::name($resource)->insertGetId($record + ['created_at' => $now, 'updated_at' => $now]);
    $sourceRecord = [
        'resource' => $resource, 'record_id' => $id, 'source_key' => $key,
        'source_id' => $sourceId, 'source_url' => $url,
        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'imported_at' => $now,
    ];
    if ($source) Db::name('official_content_source')->where('id', $source['id'])->update($sourceRecord);
    else Db::name('official_content_source')->insert($sourceRecord);
    return $id;
}

$result = Db::transaction(static function () use ($manifest, $digests, $assetPath, $origin, $now): array {
    $old = Db::name('official_content_source')->whereLike('source_url', 'https://www.zhirencasting.com/%')
        ->select()->toArray();
    usort($old, static function (array $a, array $b): int {
        $rank = ['product' => 0, 'article' => 1, 'banner' => 2, 'company_page' => 3, 'product_category' => 4];
        return ($rank[$a['resource']] ?? 5) <=> ($rank[$b['resource']] ?? 5);
    });
    $referenced = array_map('intval', Db::name('inquiry')->where('product_id', '>', 0)->column('product_id'));
    $archived = 0;
    foreach ($old as $source) {
        $resource = $source['resource'];
        $id = (int) $source['record_id'];
        if ($resource === 'product') {
            if (in_array($id, $referenced, true)) {
                Db::name('product')->where('id', $id)->update(['status' => 0, 'is_featured' => 0]);
                $archived++;
                continue;
            }
            Db::name('product')->where('id', $id)->delete();
        } elseif ($resource === 'product_category') {
            if (Db::name('product')->where('category_id', $id)->count()) {
                Db::name('product_category')->where('id', $id)->update(['status' => 0]);
                continue;
            }
            Db::name('product_category')->where('id', $id)->delete();
        } elseif (in_array($resource, ['article', 'banner'], true)) {
            Db::name($resource)->where('id', $id)->delete();
        } elseif ($resource !== 'company_page') {
            continue;
        }
        Db::name('official_content_source')->where('id', $source['id'])->delete();
    }

    $categoryIds = [];
    foreach ($manifest['categories'] as $index => $category) {
        $categoryIds[$category['slug']] = upsertOfficial('product_category', $category['slug'],
            $category['source_url'], [
                'name' => $category['title'], 'description' => $category['description'],
                'image_url' => $assetPath($category['image_url']), 'sort' => 100 - $index * 10, 'status' => 1,
            ], ['source_category' => $category['slug']], ['name' => $category['title']]);
    }
    foreach ($manifest['products'] as $index => $product) {
        if (!isset($categoryIds[$product['category_slug']])) throw new RuntimeException('Unknown product category');
        $categoryTitle = $manifest['categories'][array_search($product['category_slug'], array_column($manifest['categories'], 'slug'), true)]['title'];
        $materials = $product['materials'] ?: ['材料牌号、工艺和交付状态按图纸及项目要求确认。'];
        upsertOfficial('product', $product['slug'], $product['source_url'], [
            'category_id' => $categoryIds[$product['category_slug']], 'title' => $product['title'],
            'subtitle' => $categoryTitle . ' · 来图定制', 'cover_url' => $assetPath($product['image_url']),
            'gallery_json' => json_encode([$assetPath($product['image_url'])], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'summary' => preg_replace('/。+$/u', '', $product['summary']) . '。图片为部件示意，实际结构与供货范围按图纸确认。',
            'features' => "候选材料（具体按图纸评估）：\n" . implode("\n", $materials),
            'specifications' => implode("\n", $product['requirements']),
            'application' => $product['application'] ?: $categoryTitle,
            'sort' => 1000 - $index, 'is_featured' => $index < 8 ? 1 : 0, 'status' => 1,
        ], ['source_category' => $product['category_slug'], 'image_note' => '官网部件示意图']);
    }

    foreach ($manifest['articles'] as $article) {
        $digest = $digests[$article['slug']];
        upsertOfficial('article', $article['slug'], $article['source_url'], [
            'title' => $digest['title'], 'summary' => $digest['summary'],
            'cover_url' => $article['image_url'] ? $assetPath($article['image_url']) : '',
            'content' => $digest['content'], 'published_at' => $article['published_at'],
            'sort' => 0, 'status' => 1,
        ], ['english_title' => $article['title_en'], 'content_note' => '中文摘要；完整文章见来源']);
    }

    $about = $manifest['pages']['about'];
    $capabilities = $manifest['pages']['capabilities'];
    $solutions = $manifest['pages']['solutions'];
    $company = [
        ['about', '公司简介', implode("\n\n", array_slice($about['paragraphs'], 0, 8)), $about['images'][0] ?? '', '/about/'],
        ['manufacturing', '精密铸造与制造能力', implode("\n\n", array_slice($capabilities['paragraphs'], 0, 7)) . "\n\n制造流程：" . implode('、', array_slice($capabilities['headings'], 8, 12)), $capabilities['images'][0] ?? '', '/capabilities/'],
        ['quality', '质量控制与检测', '铸件检验与交付要求应根据图纸、材料规范和项目验收标准逐项确认。官网展示 ISO 9001:2015 质量管理体系认证及相关证书；材料成分、尺寸和具体性能检测范围以项目约定为准。', $capabilities['images'][2] ?? '', '/capabilities/'],
        ['applications', '行业应用领域', ($solutions['paragraphs'][0] ?? '') . "\n\n" . implode("\n", array_slice($solutions['headings'], 1, 6)), $solutions['images'][0] ?? '', '/solutions/'],
        ['sustainability', '合理使用材料与能源', implode("\n\n", array_slice($about['paragraphs'], 6, 2)), $about['images'][1] ?? '', '/about/'],
    ];
    foreach ($company as $index => [$slug, $title, $content, $image, $path]) {
        upsertOfficial('company_page', $slug, $origin . $path . '?lang=zh-cn', [
            'slug' => $slug, 'title' => $title, 'summary' => mb_substr(str_replace("\n", ' ', $content), 0, 180),
            'content' => $content, 'image_url' => $image ? $assetPath($image) : '',
            'sort' => 100 - $index * 10, 'status' => 1,
        ], [], ['slug' => $slug]);
    }
    // Certificates shown on both company websites remain published; their scanned originals stay local.
    foreach (['certificate-iso', 'certificate-association', 'honor-development', 'university-cooperation'] as $slug) {
        $existing = Db::name('company_page')->where('slug', $slug)->find();
        if (!$existing) continue;
        upsertOfficial('company_page', $slug, $origin . '/capabilities/?lang=zh-cn', [
            'slug' => $slug, 'title' => $existing['title'], 'summary' => $existing['summary'],
            'content' => $existing['content'], 'image_url' => $existing['image_url'],
            'sort' => $existing['sort'], 'status' => 1,
        ], [], ['slug' => $slug]);
    }

    $homeImages = $manifest['home']['images'];
    if (count($homeImages) < 2) throw new RuntimeException('Not enough homepage images');
    foreach ([
        ['铜合金精密铸造', $homeImages[0], 'company', ''],
        ['按零件需求定制铸件', $homeImages[1], 'product', (string) Db::name('product')->where('status', 1)->order('sort desc')->value('id')],
    ] as $index => [$title, $image, $linkType, $linkValue]) {
        upsertOfficial('banner', 'home-' . $index, $origin . '/', [
            'title' => $title, 'image_url' => $assetPath($image), 'link_type' => $linkType,
            'link_value' => $linkValue, 'sort' => 100 - $index * 10, 'status' => 1,
        ]);
    }
    $settings = [
        'company_name' => ['江西志人不锈钢制品有限公司', '公司名称'],
        'hero_title' => [$manifest['home']['hero_title'], '首页主标题'],
        'hero_subtitle' => ['按零件图纸开展铜合金精密铸造与制造评估', '首页副标题'],
        'company_summary' => [implode("\n\n", array_slice($about['paragraphs'], 0, 2)), '公司简介摘要'],
        'phone' => ['+86-791-88670101', '联系电话'],
        'email' => ['info@jxzrcasting.com', '电子邮箱'],
        'alternate_email' => ['', '备用邮箱'],
        'contact_person' => ['', '联系人'],
        'whatsapp' => ['+86 19170070673', 'WhatsApp'],
        'address' => ['江西省南昌市南昌县南新乡黄渡村樟树自然村188号', '联系地址'],
        'registered_address' => ['江西省南昌市扬子洲岛', '公司总部所在地'],
        'website' => [$origin . '/', '公司官网'],
        'founded_year' => ['1993', '创立年份'],
        'annual_capacity' => ['2156 吨', '官网展示的产能数据'],
        'countries_served' => ['34', '官网展示的国家数量'],
        'hero_image_url' => [$assetPath($homeImages[0]), '首页主图'],
        'company_image_url' => [$assetPath($about['images'][0]), '公司图片'],
    ];
    if ($manifest['home']['logo_url']) $settings['logo_url'] = [$assetPath($manifest['home']['logo_url']), '公司标志'];
    foreach ($settings as $key => [$value, $label]) {
        $row = Db::name('site_setting')->where('key', $key)->find();
        if ($row) Db::name('site_setting')->where('id', $row['id'])->update(['value' => $value, 'label' => $label]);
        else Db::name('site_setting')->insert(['key' => $key, 'value' => $value, 'label' => $label]);
    }
    return ['categories' => count($categoryIds), 'products' => count($manifest['products']),
        'articles' => count($manifest['articles']), 'archived_inquiry_products' => $archived];
});
echo json_encode(['updated' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
