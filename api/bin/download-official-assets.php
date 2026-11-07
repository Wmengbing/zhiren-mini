<?php
declare(strict_types=1);
define('OFFICIAL_COLLECT_LIBRARY', true);
require __DIR__ . '/collect-official.php';

$catalog = json_decode(file_get_contents($outputDir . '/catalog-api.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($outputDir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assetDir = dirname(__DIR__) . '/public/uploads/official-site';
if (!is_dir($assetDir)) mkdir($assetDir, 0755, true);
$cdn = 'https://omo-oss-image.thefastimg.com/';
$cdnPath = $cdn . 'portal-saas/pg2025121516225710076/cms/image/';
$jobs = [];
$named = [
    'logo' => '3662ab28-c63c-4bb3-881d-8efe4cd19570.png',
    'factory' => '79accdbf-61a3-47ca-b7cb-9a1f6c52a8a5.jpg',
    'manufacturing' => '58b55f52-45f9-45fe-a030-5d5a9d51745b.jpg',
    'sustainability' => 'fe40d7c8-49de-46e4-86b8-9313224c37f1.jpg',
    'iso-certificate' => 'f7b26b60-a085-42c0-9223-548c67b923b7.jpg',
    'association-certificate' => 'c6944a7c-adcc-4b5a-b16b-e371ff90e59e.jpg',
    'contribution-honor' => '4505ed7f-dd9d-4159-b013-767fad824b5c.png',
    'university-cooperation' => '67f37b40-6fcd-4925-a226-7c083eca4f87.png',
];
foreach ($named as $key => $filename) { $named[$key] = $cdnPath . $filename; $jobs[$named[$key]] = 'image'; }
$products = [];
foreach ($catalog as $categoryUrl => $response) {
    if (count($response['data']['list']) !== (int) $response['data']['page']['totalCount']) throw new RuntimeException('Incomplete official category: ' . $categoryUrl);
    foreach ($response['data']['list'] as $row) {
        $id = (string) $row['productId'];
        $images = [];
        foreach ($row['images'] ?? [] as $image) {
            $url = $image['url'] ?? $image['imageUrl'];
            if (!str_starts_with($url, 'http')) $url = $cdn . ltrim($url, '/');
            $jobs[$url] = 'image';
            $images[] = $url;
        }
        $cover = $row['coverImage'] ?? '';
        if ($cover && !str_starts_with($cover, 'http')) $cover = $cdn . ltrim($cover, '/');
        if ($cover) $jobs[$cover] = 'image';
        $products[$id] = ['source_id' => $id, 'source_url' => officialUrl($row['href']), 'category_url' => $categoryUrl, 'source_category' => $row['categoryName'] ?? '', 'title' => $row['title'], 'cover' => $cover, 'images' => array_values(array_unique($images)), 'original_summary' => $row['summary'] ?? '', 'original_content' => $row['content'] ?? '', 'pub_date' => $row['pubDate'] ?? ''];
    }
}
$videoPage = $manifest['pages'][OFFICIAL_ORIGIN . '/video/1.html'];
$video = json_decode(file_get_contents($outputDir . '/pages/' . $videoPage['key'] . '.json'), true, 512, JSON_THROW_ON_ERROR);
$videoTitles = ['三维数字检测', '压铸车间', '制壳车间', '组树车间', '修蜡车间', '制蜡车间', '浇铸车间', '模具与机加工车间', '公司厂区'];
$videos = [];
foreach ($video['videos'] as $index => $item) {
    $url = $item['src'];
    if (parse_url($url, PHP_URL_HOST) !== 'omo-oss-video110.thefastvideo.com') throw new RuntimeException('Unexpected official video host.');
    $jobs[$url] = 'video';
    $videos[] = ['title' => $videoTitles[$index] ?? ('生产视频 ' . ($index + 1)), 'source_url' => $url];
}
$assets = is_file($outputDir . '/assets.json') ? json_decode(file_get_contents($outputDir . '/assets.json'), true, 512, JSON_THROW_ON_ERROR) : [];
$queue = array_keys($jobs);
$completed = 0;
while ($queue) {
    $batch = array_splice($queue, 0, 4);
    $multi = curl_multi_init();
    $handles = [];
    foreach ($batch as $url) {
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'mp4'], true)) throw new RuntimeException('Unexpected asset type: ' . $url);
        $name = substr(sha1($url), 0, 20) . '.' . $ext;
        $path = $assetDir . '/' . $name;
        if (isset($assets[$url]) && is_file($path) && filesize($path) > 0) continue;
        $stream = fopen($path . '.download', 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $stream, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => $jobs[$url] === 'video' ? 300 : 70, CURLOPT_USERAGENT => 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36', CURLOPT_REFERER => OFFICIAL_ORIGIN . '/', CURLOPT_CAINFO => 'D:/Git/mingw64/etc/ssl/certs/ca-bundle.crt']);
        curl_multi_add_handle($multi, $ch);
        $handles[] = [$url, $ch, $stream, $path, $name];
    }
    do { curl_multi_exec($multi, $running); if ($running) curl_multi_select($multi, 1); } while ($running);
    foreach ($handles as [$url, $ch, $stream, $path, $name]) {
        fclose($stream);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status !== 200 || !is_file($path . '.download') || filesize($path . '.download') === 0) throw new RuntimeException('Asset download failed: ' . $url . ' HTTP ' . $status . ' ' . curl_error($ch));
        rename($path . '.download', $path);
        $meta = ['filename' => $name, 'relative_url' => '/uploads/official-site/' . $name, 'bytes' => filesize($path), 'kind' => $jobs[$url], 'sha256' => hash_file('sha256', $path)];
        if ($jobs[$url] === 'image') {
            $size = getimagesize($path);
            if (!$size) throw new RuntimeException('Not a valid image: ' . $url);
            $meta['width'] = $size[0]; $meta['height'] = $size[1];
        }
        $assets[$url] = $meta;
        echo ++$completed, ' downloaded ', $name, ' ', $meta['bytes'], ' bytes', PHP_EOL;
        curl_multi_remove_handle($multi, $ch); curl_close($ch);
    }
    curl_multi_close($multi);
    file_put_contents($outputDir . '/assets.json', json_encode($assets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
file_put_contents($outputDir . '/prepared.json', json_encode(['named_images' => $named, 'products' => array_values($products), 'videos' => $videos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Prepared ', count($products), ' products, ', count($videos), ' videos, ', count($assets), ' local assets.', PHP_EOL;
