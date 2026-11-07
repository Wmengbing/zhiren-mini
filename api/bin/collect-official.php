<?php
declare(strict_types=1);

const OFFICIAL_ORIGIN = 'https://www.zhirencasting.com';
$outputDir = dirname(__DIR__) . '/runtime/official-import';
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

function officialFetch(string $url): string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 40, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36', CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,image/avif,image/webp,*/*;q=0.8'], CURLOPT_REFERER => OFFICIAL_ORIGIN . '/']);
    $ca = 'D:/Git/mingw64/etc/ssl/certs/ca-bundle.crt';
    if (is_file($ca)) curl_setopt($curl, CURLOPT_CAINFO, $ca);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($body === false || $status !== 200) throw new RuntimeException('Fetch failed ' . $url . ' HTTP ' . $status . ' ' . curl_error($curl));
    curl_close($curl);
    return $body;
}

function officialDom(string $html): DOMXPath
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET);
    libxml_clear_errors();
    return new DOMXPath($document);
}

function officialUrl(string $value): string
{
    if (str_starts_with($value, '//')) return 'https:' . $value;
    if (str_starts_with($value, '/')) return OFFICIAL_ORIGIN . $value;
    if (!preg_match('#^https?://#', $value)) return OFFICIAL_ORIGIN . '/' . $value;
    return $value;
}

function officialExtract(string $html): array
{
    $xpath = officialDom($html);
    $texts = [];
    foreach ($xpath->query('//h1|//h2|//h3|//*[contains(@class,"e_richText") or contains(@class,"e_text-") or contains(@class,"p_detail") or contains(@class,"p_summary")]') as $node) {
        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        if ($text !== '') $texts[] = ['tag' => $node->nodeName, 'class' => $node->getAttribute('class'), 'text' => $text];
    }
    $images = [];
    foreach ($xpath->query('//img') as $node) {
        $attributes = [];
        foreach ($node->attributes as $attribute) $attributes[$attribute->name] = $attribute->value;
        $parent = $node->parentNode;
        $attributes['parent'] = $parent instanceof DOMElement ? $parent->getAttribute('class') : '';
        $images[] = $attributes;
    }
    $links = [];
    foreach ($xpath->query('//a[@href]') as $node) {
        $href = $node->getAttribute('href');
        if ($href !== '' && !preg_match('#^(?:javascript:|tel:|mailto:|\#)#i', $href)) {
            $links[] = ['url' => officialUrl($href), 'text' => trim(preg_replace('/\s+/u', ' ', $node->textContent))];
        }
    }
    $videos = [];
    foreach ($xpath->query('//video|//video/source') as $node) {
        $attrs = [];
        foreach ($node->attributes as $attribute) $attrs[$attribute->name] = $attribute->value;
        $videos[] = $attrs;
    }
    return ['title' => $xpath->evaluate('string(//title)'), 'texts' => $texts, 'images' => $images, 'links' => $links, 'videos' => $videos];
}

if (defined('OFFICIAL_COLLECT_LIBRARY')) return;

if (in_array('--crawl', $argv, true)) {
    $links = json_decode(file_get_contents($outputDir . '/links.json'), true, 512, JSON_THROW_ON_ERROR);
    $pagesDir = $outputDir . '/pages';
    if (!is_dir($pagesDir)) mkdir($pagesDir, 0755, true);
    $queue = [OFFICIAL_ORIGIN . '/'];
    foreach (array_keys($links) as $url) {
        $url = explode('#', $url)[0];
        if (preg_match('#/(?:about|contact|solution|product_index/1|video/1|blog_list/(?:3|17873516|17873521|81163)|Product/[^/]+)\.html$#', $url)) $queue[] = $url;
    }
    $queue = array_values(array_unique($queue));
    $manifest = [];
    $errors = [];
    $seen = [];
    $newsCount = 0;
    while ($queue) {
        $batch = [];
        while ($queue && count($batch) < 4) {
            $url = array_shift($queue);
            if (isset($seen[$url])) continue;
            $seen[$url] = true;
            $batch[] = $url;
        }
        if (!$batch) continue;
        $multi = curl_multi_init();
        $handles = [];
        foreach ($batch as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 45, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36', CURLOPT_REFERER => OFFICIAL_ORIGIN . '/', CURLOPT_CAINFO => 'D:/Git/mingw64/etc/ssl/certs/ca-bundle.crt']);
            curl_multi_add_handle($multi, $ch);
            $handles[] = [$url, $ch];
        }
        do { curl_multi_exec($multi, $running); if ($running) curl_multi_select($multi, 1); } while ($running);
        foreach ($handles as [$url, $ch]) {
            $body = curl_multi_getcontent($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $key = substr(sha1($url), 0, 16);
            if ($status !== 200 || $body === '') {
                $errors[$url] = 'HTTP ' . $status . ' ' . curl_error($ch);
                echo 'FAIL ', $url, ' ', $errors[$url], PHP_EOL;
            } else {
                file_put_contents($pagesDir . '/' . $key . '.html', $body);
                $data = officialExtract($body);
                file_put_contents($pagesDir . '/' . $key . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $manifest[$url] = ['key' => $key, 'title' => $data['title']];
                echo count($manifest), ' OK ', $url, PHP_EOL;
                foreach ($data['links'] as $link) {
                    $target = explode('#', $link['url'])[0];
                    if (parse_url($target, PHP_URL_HOST) !== 'www.zhirencasting.com' || isset($seen[$target]) || in_array($target, $queue, true)) continue;
                    if (preg_match('#/(?:Product/[^/]+/[^/]+|product_details/[0-9]+)\.html$#', $target)) $queue[] = $target;
                    if (preg_match('#/(?:blog_details/[0-9]+|oem-[^/]+)\.html$#', $target) && $newsCount < 14) { $queue[] = $target; $newsCount++; }
                }
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        file_put_contents($outputDir . '/manifest.json', json_encode(['pages' => $manifest, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    echo 'Collected ', count($manifest), ' pages; errors ', count($errors), PHP_EOL;
    exit($errors ? 1 : 0);
}

if (isset($argv[1]) && str_starts_with($argv[1], '--page=')) {
    $url = substr($argv[1], strlen('--page='));
    if (parse_url($url, PHP_URL_HOST) !== 'www.zhirencasting.com') throw new RuntimeException('Unexpected website host.');
    $html = officialFetch($url);
    $basename = basename((string) parse_url($url, PHP_URL_PATH), '.html');
    $key = $basename . '-' . substr(sha1($url), 0, 8);
    file_put_contents($outputDir . '/' . $key . '.html', $html);
    $extracted = officialExtract($html);
    file_put_contents($outputDir . '/' . $key . '.json', json_encode($extracted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['url' => $url, 'key' => $key, 'title' => $extracted['title'], 'texts' => $extracted['texts'], 'images' => $extracted['images']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}

$html = officialFetch(OFFICIAL_ORIGIN . '/');
file_put_contents($outputDir . '/home.html', $html);
$xpath = officialDom($html);
$links = [];
foreach ($xpath->query('//a[@href]') as $node) {
    $href = $node->getAttribute('href');
    if ($href === '' || preg_match('#^(?:javascript:|tel:|mailto:|\#)#i', $href)) continue;
    $url = officialUrl($href);
    if (parse_url($url, PHP_URL_HOST) !== 'www.zhirencasting.com') continue;
    $links[$url] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
}
file_put_contents($outputDir . '/links.json', json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
