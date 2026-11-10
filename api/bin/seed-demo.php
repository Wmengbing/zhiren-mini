<?php
// Local preview: php -d extension=php_gd.dll bin/seed-demo.php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use think\facade\Db;

$app = new \think\App();
$app->initialize();
if (!in_array('--assets-only', $argv, true) && !in_array('--allow-demo', $argv, true)
    && Db::query("SHOW TABLES LIKE 'official_content_source'") && Db::name('official_content_source')->count()) {
    throw new RuntimeException('Official content is already installed. Demo seeding is disabled; use --allow-demo only for an intentional test reset.');
}
if (!extension_loaded('gd')) {
    throw new RuntimeException('Run with the GD extension enabled to generate demo images.');
}
$font = 'C:/Windows/Fonts/msyh.ttc';
if (!is_file($font)) throw new RuntimeException('Chinese font missing: ' . $font);
$assetDir = dirname(__DIR__) . '/public/uploads/demo-white-v3';
if (!is_dir($assetDir) && !mkdir($assetDir, 0755, true)) throw new RuntimeException('Cannot create demo image directory.');
$assetUrl = '/uploads/demo-white-v3/';

function color($im, string $hex): int
{
    return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function label($im, string $text, int $size, int $x, int $y, string $hex): void
{
    global $font;
    imagettftext($im, $size, 0, $x, $y, color($im, $hex), $font, $text);
}

function mechanism($im, string $type, int $cx, int $cy, float $scale): void
{
    $metal = color($im, 'A8B1BB');
    $light = color($im, 'EEF0F3');
    $dark = color($im, '56606B');
    $accent = color($im, 'EE642D');
    $x = fn(float $v): int => (int) round($cx + $v * $scale);
    $y = fn(float $v): int => (int) round($cy + $v * $scale);
    $rect = function ($x1, $y1, $x2, $y2, $c) use ($im, $x, $y) { imagefilledrectangle($im, $x($x1), $y($y1), $x($x2), $y($y2), $c); };
    $oval = function ($ox, $oy, $w, $h, $c) use ($im, $x, $y, $scale) { imagefilledellipse($im, $x($ox), $y($oy), (int) ($w * $scale), (int) ($h * $scale), $c); };
    $oval(0, 150, 420, 36, color($im, 'CBD0D6'));
    switch ($type) {
        case 'valve':
            $rect(-165, -28, 165, 60, $dark); $rect(-155, -20, 155, 48, $metal);
            $oval(0, 15, 190, 170, $dark); $oval(0, 9, 166, 145, $metal);
            foreach ([-165, 165] as $offset) { $rect($offset - 13, -65, $offset + 13, 92, $dark); $rect($offset - 9, -61, $offset + 9, 86, $light); }
            $rect(-27, -98, 27, -48, $dark); $rect(-17, -160, 17, -93, $metal);
            $rect(-106, -176, 106, -158, $accent); $oval(0, 3, 70, 70, $light);
            break;
        case 'pipe':
            $rect(-165, -38, 165, 60, $metal); $rect(-45, -150, 45, 32, $metal);
            foreach ([-165, 165] as $offset) { $oval($offset, 10, 54, 128, $dark); $oval($offset, 10, 30, 96, $light); }
            $oval(0, -150, 122, 52, $dark); $oval(0, -150, 88, 27, $light);
            $rect(-124, -29, -68, -13, $light); $rect(65, -29, 126, -13, $light);
            break;
        case 'hinge':
            $rect(-175, -135, -14, 135, $dark); $rect(14, -135, 175, 135, $dark);
            $rect(-167, -127, -14, 127, $metal); $rect(14, -127, 167, 127, $metal);
            $rect(-20, -147, 20, 147, $light);
            foreach ([-100, 100] as $ox) foreach ([-82, 82] as $oy) { $oval($ox, $oy, 39, 39, $dark); $oval($ox, $oy, 20, 20, $light); }
            break;
        case 'marine':
            $oval(0, 90, 310, 85, $dark); $oval(0, 82, 300, 73, $metal);
            $rect(-25, -60, 25, 78, $metal); $rect(-175, -80, 175, -47, $dark); $rect(-170, -84, 170, -58, $light);
            foreach ([-95, 95] as $ox) $oval($ox, 82, 30, 20, $dark);
            break;
        case 'kitchen':
            $rect(-160, -95, -110, 95, $dark); $rect(110, -95, 160, 95, $dark);
            $rect(-150, -85, -120, 85, $metal); $rect(120, -85, 150, 85, $metal);
            $rect(-135, -105, 135, -42, $metal); $rect(-121, -96, 121, -77, $light);
            $oval(-135, 98, 80, 24, $light); $oval(135, 98, 80, 24, $light);
            break;
        case 'medical':
            $rect(-165, -28, 165, 38, $metal); $rect(-110, -75, 55, 85, $dark); $rect(-103, -68, 48, 78, $metal);
            $oval(55, 5, 54, 160, $light); $oval(55, 5, 25, 100, $dark);
            $rect(-58, -143, -2, -69, $metal); $oval(-30, -144, 82, 30, $light);
            break;
        default:
            for ($i = 0; $i < 12; $i++) {
                $angle = $i * M_PI / 6;
                $oval(cos($angle) * 130, sin($angle) * 130 - 8, 67, 67, $dark);
            }
            $oval(0, -8, 268, 268, $dark); $oval(0, -8, 234, 234, $metal); $oval(0, -8, 115, 115, $light); $oval(0, -8, 72, 72, $dark);
    }
}

function picture(string $filename, string $title, string $subtitle, string $type, bool $wide = false): void
{
    global $assetDir;
    $path = $assetDir . '/' . $filename;
    if (is_file($path)) return;
    $w = $wide ? 1400 : 1000;
    $h = $wide ? 700 : 800;
    $im = imagecreatetruecolor($w, $h);
    imageantialias($im, true);
    for ($row = 0; $row < $h; $row++) {
        $t = $row / $h;
        $r = (int) (243 - 19 * $t); $g = (int) (245 - 18 * $t); $b = (int) (248 - 19 * $t);
        imageline($im, 0, $row, $w, $row, imagecolorallocate($im, $r, $g, $b));
    }
    $grid = color($im, 'E0E4EA');
    for ($col = 0; $col < $w; $col += 70) imageline($im, $col, 0, $col, $h, $grid);
    for ($row = 0; $row < $h; $row += 70) imageline($im, 0, $row, $w, $row, $grid);
    if ($wide) {
        imagefilledrectangle($im, 0, 0, 715, $h, color($im, 'FFFFFF'));
        label($im, 'JIANGXI ZHIREN', 19, 65, 94, '9EA8B7');
        label($im, $title, 39, 65, 242, '253346');
        label($im, $subtitle, 20, 65, 302, '8190A3');
        imagefilledrectangle($im, 65, 355, 180, 361, color($im, 'EE642D'));
        label($im, '金属加工 / 精密铸造 / 金属制品', 19, 65, 437, '566476');
        label($im, '演示内容 · 产品图为示意图', 17, 65, 625, '9EA8B7');
        mechanism($im, $type, 1060, 350, 1.32);
    } else {
        label($im, 'ZHIREN / PRODUCT STUDY', 18, 55, 68, '737D8A');
        mechanism($im, $type, 500, 350, 1.35);
        imagefilledrectangle($im, 0, 605, $w, $h, color($im, 'FFFFFF'));
        label($im, $title, 31, 55, 669, '252C36');
        label($im, $subtitle, 19, 55, 716, '77818E');
        label($im, '演示示意图 · 非实物照片', 17, 55, 767, '919AA6');
    }
    if (!imagepng($im, $path)) throw new RuntimeException('Cannot write image: ' . $filename);
    imagedestroy($im);
}

$categories = [
    '阀类' => ['valve', '阀体与阀门配件的分类展示示例'],
    '管件' => ['pipe', '管路连接类精密铸造件展示示例'],
    '建筑五金' => ['hinge', '合页、连接件等建筑五金展示示例'],
    '船用五金' => ['marine', '系泊与船用连接配件展示示例'],
    '厨具配件' => ['kitchen', '厨具把手与连接配件展示示例'],
    '医疗器械配件' => ['medical', '器械结构配件展示示例，不涉及临床用途说明'],
    '机械配件' => ['gear', '传动与装配结构件展示示例'],
];
foreach ($categories as $name => [$type]) picture($type . '.png', $name . ' · 铸件示例', '精密铸造件 / 展示测试', $type);
picture('banner-company.png', '走进志人制造', '多类型精密铸造件展示', 'gear', true);
picture('banner-products.png', '精密铸造产品', '探索阀类、管件及五金配件', 'valve', true);
picture('banner-cooperation.png', '从需求到样件', '沟通图纸需求，了解合作流程', 'pipe', true);
picture('news-process.png', '图纸与需求沟通', '演示资讯 / 合作流程', 'pipe');
picture('news-materials.png', '产品资料准备', '演示资讯 / 内容维护', 'hinge');
picture('news-preview.png', '小程序预览指南', '演示资讯 / 功能体验', 'gear');

if (in_array('--assets-only', $argv, true)) {
    echo 'White studio demo images generated. Database unchanged.', PHP_EOL;
    exit(0);
}

$backup = [];
foreach (['banner', 'product', 'article', 'company_page', 'product_category', 'site_setting'] as $table) {
    $backup[$table] = Db::name($table)->select()->toArray();
}
$backupDir = dirname(__DIR__) . '/runtime/demo-backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$backupFile = $backupDir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
if (file_put_contents($backupFile, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot save pre-import backup.');
}

$counts = ['products' => 0, 'banners' => 0, 'articles' => 0, 'company_pages' => 0];
$now = date('Y-m-d H:i:s');
$note = '【演示数据】本条内容仅用于页面预览；图片为示意图，示例参数不代表公司实际产品规格，请以正式图纸与确认资料为准。';
Db::transaction(function () use ($categories, $assetUrl, $now, $note, &$counts): void {
    $categoryIds = [];
    foreach ($categories as $name => [$type, $description]) {
        $row = Db::name('product_category')->where('name', $name)->find();
        if (!$row) throw new RuntimeException('Required category missing: ' . $name);
        $categoryIds[$name] = $row['id'];
        $changes = [];
        if ($row['description'] === '') $changes['description'] = '【演示】' . $description;
        if ($row['image_url'] === '') $changes['image_url'] = $assetUrl . $type . '.png';
        if ($changes) Db::name('product_category')->where('id', $row['id'])->update($changes + ['updated_at' => $now]);
    }

    $samples = [
        ['阀类', '不锈钢阀体铸件', 'valve', '阀体结构展示 / 可按图沟通', '示例材质：304 不锈钢\n示例通径：DN25\n供货形式：铸件毛坯示例'],
        ['管件', '不锈钢三通接头', 'pipe', '管路连接 / 三通结构展示', '示例材质：304 不锈钢\n示例接口：1 英寸\n结构形式：三通示例'],
        ['建筑五金', '不锈钢门窗合页', 'hinge', '建筑五金 / 合页结构展示', '示例材质：304 不锈钢\n示例长度：100 mm\n安装形式：螺钉固定示例'],
        ['船用五金', '船用系缆配件', 'marine', '船用五金 / 系缆结构展示', '示例材质：316 不锈钢\n示例长度：150 mm\n安装方式：底座固定示例'],
        ['厨具配件', '不锈钢厨具把手', 'kitchen', '厨具配件 / 把手结构展示', '示例材质：304 不锈钢\n示例孔距：128 mm\n表面外观：示意效果'],
        ['机械配件', '机械传动轮铸件', 'gear', '机械配件 / 传动结构展示', '示例材质：不锈钢\n示例外径：120 mm\n加工余量：按正式图纸确认'],
        ['医疗器械配件', '器械结构连接件', 'medical', '器械配件 / 连接结构展示', '示例材质：不锈钢\n示例尺寸：60 × 30 mm\n用途说明：结构展示，不作临床适用承诺'],
        ['阀类', '阀门连接组件', 'valve', '阀类配件 / 连接结构展示', '示例材质：316 不锈钢\n示例通径：DN40\n连接形式：按图确认'],
        ['管件', '管路连接座', 'pipe', '管件配件 / 装配结构展示', '示例材质：304 不锈钢\n示例接口：DN20\n外形尺寸：按图确认'],
        ['机械配件', '设备装配结构件', 'gear', '机械配件 / 装配结构展示', '示例材质：不锈钢\n示例外形：80 × 60 mm\n结构与公差：按图确认'],
    ];
    $firstProductId = 0;
    foreach ($samples as $index => [$category, $name, $type, $subtitle, $specifications]) {
        $title = '【演示】' . $name;
        $id = Db::name('product')->where('title', $title)->value('id');
        if (!$id) {
            $id = Db::name('product')->insertGetId([
                'category_id' => $categoryIds[$category], 'title' => $title, 'subtitle' => $subtitle,
                'cover_url' => $assetUrl . $type . '.png', 'summary' => $note . "\n\n" . $name . '用于展示产品列表、分类筛选、详情介绍和在线咨询流程。',
                'features' => "1. 演示产品结构与外观展示。\n2. 可用于测试图片、参数及应用说明的展示效果。\n3. 实际材质、工艺、尺寸和交付条件需另行确认。",
                'specifications' => str_replace('\\n', "\n", $specifications) . "\n以上均为测试参数，不能作为采购依据。",
                'application' => $category . "相关场景的页面展示示例。实际适用场景需经技术沟通确认。",
                'sort' => 100 - $index * 5, 'is_featured' => $index < 6 ? 1 : 0, 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $counts['products']++;
        }
        if (!$firstProductId) $firstProductId = (int) $id;
    }

    foreach ([
        ['走进志人制造', 'banner-company.png', 'company', '', 100],
        ['精密铸造产品', 'banner-products.png', 'product', (string) $firstProductId, 90],
        ['从需求到样件', 'banner-cooperation.png', 'company', '', 80],
    ] as [$title, $image, $linkType, $linkValue, $sort]) {
        $title = '【演示】' . $title;
        if (!Db::name('banner')->where('title', $title)->find()) {
            Db::name('banner')->insert(['title' => $title, 'image_url' => $assetUrl . $image, 'link_type' => $linkType, 'link_value' => $linkValue, 'sort' => $sort, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $counts['banners']++;
        }
    }

    $articles = [
        ['图纸与需求沟通流程', 'news-process.png', '了解需求确认、图纸沟通与样件验证的页面展示示例。', "本篇为演示资讯，用于测试新闻列表和详情页面，不代表公司已发布的业务公告。\n\n第一步：整理产品用途、结构图纸及数量需求。\n第二步：沟通材质、尺寸、公差及外观要求。\n第三步：在正式确认工艺与报价后，再讨论样件验证和交付安排。\n\n以上为示例流程，实际合作方式请与公司确认。"],
        ['产品资料可以怎样准备', 'news-materials.png', '示例说明产品图片、文字和参数的整理方式。', "本篇为演示资讯，方便预览长文本与封面图效果。\n\n产品图片：建议准备清晰的实物正面、侧面和局部照片。\n产品文字：可包括名称、分类、用途与结构特点。\n产品参数：应以实际图纸或经过确认的资料为准。\n\n测试完成后，可在后台替换本篇文章和图片。"],
        ['企业展示小程序体验指南', 'news-preview.png', '体验首页、产品筛选、详情与合作咨询等功能。', "欢迎体验企业展示小程序。本篇内容属于测试数据。\n\n首页：查看轮播、公司介绍和推荐产品。\n产品：按分类筛选，也可以输入产品关键词搜索。\n资讯：浏览新闻列表和文章详情。\n联系：查看联系信息并进入合作咨询。\n\n所有带有“演示”标识的产品、图片与参数仅用于功能测试。"],
    ];
    foreach ($articles as $index => [$title, $image, $summary, $content]) {
        $title = '【演示】' . $title;
        if (!Db::name('article')->where('title', $title)->find()) {
            Db::name('article')->insert(['title' => $title, 'summary' => $summary, 'cover_url' => $assetUrl . $image, 'content' => $content, 'published_at' => date('Y-m-d H:i:s', time() - $index * 86400), 'sort' => 100 - $index, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $counts['articles']++;
        }
    }

    foreach ([
        ['demo-process', '【演示】合作沟通流程', '从需求整理到样件讨论的流程示例', "【演示内容】\n1. 提供图纸、用途与数量需求。\n2. 沟通材质、工艺及尺寸要求。\n3. 确认样件验证与后续合作安排。\n\n此流程用于页面预览，具体安排以双方正式确认为准。", 'news-process.png'],
        ['demo-applications', '【演示】产品应用领域', '七类精密铸造件的展示范围', "【演示内容】\n产品展示覆盖阀类、管件、建筑五金、船用五金、厨具配件、医疗器械配件及机械配件。\n\n本页面中的图片为图形示意，不是公司实物照片。实际产品范围和供货条件请与公司确认。", 'gear.png'],
    ] as [$slug, $title, $summary, $content, $image]) {
        if (!Db::name('company_page')->where('slug', $slug)->find()) {
            Db::name('company_page')->insert(['slug' => $slug, 'title' => $title, 'summary' => $summary, 'content' => $content, 'image_url' => $assetUrl . $image, 'sort' => 30, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $counts['company_pages']++;
        }
    }
    foreach ([
        'email' => 'demo@zhiren.example',
        'address' => '演示地址：江西省，详细地址待补充',
        'work_hours' => '演示：工作日 09:00—18:00（待确认）',
    ] as $key => $value) {
        Db::name('site_setting')->where('key', $key)->where('value', '')->update(['value' => $value]);
    }
});

echo json_encode(['inserted' => $counts, 'backup' => $backupFile, 'image_base_url' => $assetUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
