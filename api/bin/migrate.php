<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/vendor/autoload.php';
(new \think\App())->initialize();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    exit(1);
});
use think\facade\Db;

$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/20261107-hardening.sql');
$prefix = (string) env('DATABASE.PREFIX', '');
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid database prefix');
// Apply the rate-limit schema with the configured prefix too.
Db::execute(str_replace('admin_login_limit', $prefix . 'admin_login_limit', substr($sql, 0, strpos($sql, ';') + 1)));
$additions = [
    'product' => ['gallery_json' => 'TEXT NULL'],
    'inquiry' => ['consent_version' => "VARCHAR(40) NOT NULL DEFAULT ''", 'consented_at' => 'DATETIME NULL'],
];
foreach ($additions as $table => $columns) {
    $tableName = $prefix . $table;
    $existing = array_column(Db::query("SHOW COLUMNS FROM " . $tableName), 'Field');
    foreach ($columns as $column => $definition) {
        if (!in_array($column, $existing, true)) Db::execute("ALTER TABLE " . $tableName . " ADD COLUMN " . $column . " " . $definition);
    }
}
echo "Migrations complete.\n";
