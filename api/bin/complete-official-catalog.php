<?php
declare(strict_types=1);
define('OFFICIAL_COLLECT_LIBRARY', true);
require __DIR__ . '/collect-official.php';

$manifest = json_decode(file_get_contents($outputDir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$catalog = [];
foreach ($manifest['pages'] as $url => $entry) {
    if (!preg_match('#/Product/[^/]+\.html$#', $url)) continue;
    $html = file_get_contents($outputDir . '/pages/' . $entry['key'] . '.html');
    $xpath = officialDom($html);
    foreach ($xpath->query('//input[@name="_config"]') as $node) {
        $config = json_decode($node->getAttribute('value'), true);
        if (($config['cname'] ?? '') !== '产品-产品列表') continue;
        $params = $config['params'];
        $params['size'] = 200;
        $params['from'] = 0;
        $ch = curl_init(OFFICIAL_ORIGIN . $config['api']);
        $headers = ['Content-Type: application/json'];
        foreach ($params['header'] ?? [] as $key => $value) $headers[] = $key . ': ' . $value;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36', CURLOPT_REFERER => $url, CURLOPT_CAINFO => 'D:/Git/mingw64/etc/ssl/certs/ca-bundle.crt', CURLOPT_TIMEOUT => 40]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $status !== 200) throw new RuntimeException('Public catalog read failed: ' . $url . ' HTTP ' . $status . ' ' . curl_error($ch));
        curl_close($ch);
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $catalog[$url] = $response;
        echo $url, ' ', substr(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 1400), PHP_EOL;
        break;
    }
}
file_put_contents($outputDir . '/catalog-api.json', json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
