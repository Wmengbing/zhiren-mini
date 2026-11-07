<?php
// Usage: php bin/create-admin.php admin
require dirname(__DIR__) . '/vendor/autoload.php';

$app = new \think\App();
$app->initialize();
$username = $argv[1] ?? 'admin';
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    fwrite(STDERR, "用户名需为 3-50 位字母、数字或下划线\n");
    exit(1);
}
fwrite(STDOUT, "请设置管理员密码（至少 12 位）：");
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "密码需为 12-72 字节\n");
    exit(1);
}
\think\facade\Db::name('admin')->insert([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'status' => 1,
    'created_at' => date('Y-m-d H:i:s'),
]);
fwrite(STDOUT, "管理员已创建：{$username}\n");
