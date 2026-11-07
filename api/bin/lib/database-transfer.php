<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
(new \think\App())->initialize();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Database transfer failed: ' . $error->getMessage() . "\n");
    exit(1);
});

function transferPdo(): PDO
{
    return new PDO(
        'mysql:host=' . env('DATABASE.HOSTNAME', '127.0.0.1') . ';port=' . env('DATABASE.HOSTPORT', '3306')
        . ';dbname=' . env('DATABASE.DATABASE', '') . ';charset=utf8mb4',
        (string) env('DATABASE.USERNAME', ''), (string) env('DATABASE.PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function transferTables(): array
{
    $prefix = (string) env('DATABASE.PREFIX', '');
    if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid prefix');
    return array_map(fn($table) => $prefix . $table, [
        'admin', 'admin_session', 'admin_login_limit', 'site_setting', 'banner', 'company_page',
        'product_category', 'product', 'article', 'inquiry', 'official_content_source',
    ]);
}
