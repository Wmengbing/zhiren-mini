<?php
declare(strict_types=1);
// Local/integration environment only. Creates temporary rows and cleans them up.
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/vendor/autoload.php';
(new \think\App())->initialize();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
});
use think\facade\Db;
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Run this fixture test only against a local server');
$assertions = 0;
function verify(bool $condition, string $description): void {
    global $assertions;
    if (!$condition) throw new RuntimeException('FAILED: ' . $description);
    $assertions++;
}
function callApi(string $method, string $path, ?array $body = null, string $token = ''): array {
    global $base;
    $curl = curl_init($base . '/api/v1' . $path);
    $headers = ['Content-Type: application/json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('HTTP connection failed: ' . curl_error($curl));
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return [$status, $data['data'] ?? null, $data['message'] ?? ''];
}
$suffix = bin2hex(random_bytes(6));
$username = 'smoke_' . $suffix;
$password = bin2hex(random_bytes(16));
$nextPassword = bin2hex(random_bytes(16));
$adminId = $categoryId = $productId = $articleId = null;
$loopbackBucket = hash('sha256', 'ip:127.0.0.1');
// This is a developer fixture: isolate its loopback counter from earlier test runs.
Db::name('admin_login_limit')->where('bucket', $loopbackBucket)->delete();
try {
    verify(callApi('GET', '/health')[0] === 200, 'health');
    verify(callApi('GET', '/admin/products')[0] === 401, 'admin requires authentication');
    $adminId = Db::name('admin')->insertGetId(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 1, 'created_at' => date('Y-m-d H:i:s')]);
    [$status, $session] = callApi('POST', '/admin/login', ['username' => $username, 'password' => $password]);
    verify($status === 200 && strlen($session['token'] ?? '') === 64, 'admin login');
    $token = $session['token'];
    $secondToken = callApi('POST', '/admin/login', ['username' => $username, 'password' => $password])[1]['token'];
    [$status, $category] = callApi('POST', '/admin/categories', ['name' => $username], $token);
    verify($status === 200, 'category create');
    $categoryId = $category['id'];
    [$status, $product] = callApi('POST', '/admin/products', [
        'category_id' => $categoryId, 'title' => $username, 'application' => 'fixture_' . $suffix,
        'cover_url' => '/uploads/smoke-cover.jpg', 'gallery_json' => '["/uploads/smoke-extra.jpg"]',
    ], $token);
    verify($status === 200, 'product create');
    $productId = $product['id'];
    [$status, $detail] = callApi('GET', '/products/' . $productId);
    verify($status === 200 && count($detail['gallery']) === 2, 'product gallery');
    [$status, $list] = callApi('GET', '/admin/products?keyword=' . $username . '&status=1&category_id=' . $categoryId, null, $token);
    verify($status === 200 && $list['total'] === 1, 'admin filters');
    [$status, $list] = callApi('GET', '/products?keyword=fixture_' . $suffix);
    verify($status === 200 && $list['total'] === 1, 'search application');
    verify(!array_key_exists('features', $list['items'][0]), 'compact public cards');
    verify(callApi('PUT', '/admin/products/' . $productId, ['title' => str_repeat('x', 161)], $token)[0] === 400, 'title max length');
    verify(callApi('PUT', '/admin/products/' . $productId, ['gallery_json' => '["javascript:alert(1)"]'], $token)[0] === 400, 'gallery rejects unsafe scheme');
    verify(callApi('PUT', '/admin/products/' . $productId, ['gallery_json' => '[]'], $token)[0] === 200, 'gallery edit');
    verify(count(callApi('GET', '/products/' . $productId)[1]['gallery']) === 1, 'gallery removal retained');
    [$status, $article] = callApi('POST', '/admin/articles', ['title' => $username, 'published_at' => '2099-01-01 00:00:00'], $token);
    verify($status === 200, 'scheduled article create');
    $articleId = $article['id'];
    verify(callApi('GET', '/articles/' . $articleId)[0] === 404, 'future article hidden');
    verify(callApi('PUT', '/admin/articles/' . $articleId, ['published_at' => '2026-02-31 00:00:00'], $token)[0] === 400, 'invalid article date rejected');
    callApi('PUT', '/admin/categories/' . $categoryId, ['status' => 0], $token);
    verify(callApi('GET', '/products/' . $productId)[0] === 404, 'inactive category detail hidden');
    verify(callApi('GET', '/products?keyword=' . $username)[1]['total'] === 0, 'inactive category products hidden');
    verify(callApi('POST', '/inquiries', ['name' => 'Smoke User', 'phone' => '13800000000', 'message' => 'Smoke inquiry'])[0] === 400, 'consent enforced');
    verify(callApi('POST', '/admin/security/password', ['current_password' => $password, 'new_password' => $nextPassword], $token)[0] === 200, 'password change');
    verify(callApi('GET', '/admin/me', null, $token)[0] === 401 && callApi('GET', '/admin/me', null, $secondToken)[0] === 401, 'password change revokes all sessions');
    [$status, $session] = callApi('POST', '/admin/login', ['username' => $username, 'password' => $nextPassword]);
    verify($status === 200, 'new password works');
    $token = $session['token'];
    verify(callApi('POST', '/admin/security/revoke-sessions', [], $token)[0] === 200 && callApi('GET', '/admin/me', null, $token)[0] === 401, 'revoke sessions');
    for ($i = 1; $i <= 6; $i++) {
        $status = callApi('POST', '/admin/login', ['username' => 'missing_' . $suffix, 'password' => $password])[0];
        verify($status === ($i <= 5 ? 401 : 429), 'login throttling attempt ' . $i);
    }
    $home = callApi('GET', '/home');
    verify($home[0] === 200 && !isset($home[1]['settings']['production_videos']), 'home payload');
    echo 'PASS: ' . $assertions . " API integration assertions.\n";
} finally {
    if ($productId) Db::name('product')->where('id', $productId)->delete();
    if ($categoryId) Db::name('product_category')->where('id', $categoryId)->delete();
    if ($articleId) Db::name('article')->where('id', $articleId)->delete();
    if ($adminId) {
        Db::name('admin_session')->where('admin_id', $adminId)->delete();
        Db::name('admin')->where('id', $adminId)->delete();
    }
    foreach ([$username, 'missing_' . $suffix] as $loginName) Db::name('admin_login_limit')->where('bucket', hash('sha256', 'pair:127.0.0.1:' . $loginName))->delete();
    Db::name('admin_login_limit')->where('bucket', $loopbackBucket)->delete();
}
