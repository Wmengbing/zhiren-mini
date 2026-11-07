<?php
namespace think;

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = realpath(__DIR__ . $path);
    if ($file && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
        && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
        return false;
    }
}
require __DIR__ . '/../vendor/autoload.php';
$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
