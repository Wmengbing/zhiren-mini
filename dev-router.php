<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$public = realpath(__DIR__ . '/api/public');

if ($path === '/admin') {
    header('Location: /admin/');
    return true;
}

if (str_starts_with($path, '/admin/')) {
    $dist = realpath(__DIR__ . '/admin/dist');
    if ($dist === false) {
        http_response_code(503);
        echo 'Admin build is missing.';
        return true;
    }

    $relative = substr($path, strlen('/admin/'));
    $candidate = $relative === '' ? $dist . '/index.html' : $dist . '/' . $relative;
    $file = realpath($candidate);
    if ($file === false || !str_starts_with($file, $dist . DIRECTORY_SEPARATOR) || !is_file($file)) {
        $file = $dist . DIRECTORY_SEPARATOR . 'index.html';
    }

    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
    ];
    header('Content-Type: ' . ($contentTypes[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

if ($public !== false) {
    $file = realpath($public . $path);
    if ($file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp4') {
            $size = filesize($file);
            $start = 0;
            $end = $size - 1;
            header('Content-Type: video/mp4');
            header('Accept-Ranges: bytes');
            $range = $_SERVER['HTTP_RANGE'] ?? '';
            if ($range !== '') {
                if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && ($matches[2] ?? '') === '')) {
                    http_response_code(416);
                    header('Content-Range: bytes */' . $size);
                    header('Content-Length: 0');
                    return true;
                }
                if ($matches[1] === '') $start = max(0, $size - (int) $matches[2]);
                else {
                    $start = (int) $matches[1];
                    if (($matches[2] ?? '') !== '') $end = min($end, (int) $matches[2]);
                }
                if ($start >= $size || $end < $start) {
                    http_response_code(416);
                    header('Content-Range: bytes */' . $size);
                    header('Content-Length: 0');
                    return true;
                }
                http_response_code(206);
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            }
            header('Content-Length: ' . ($end - $start + 1));
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return true;
            $stream = fopen($file, 'rb');
            fseek($stream, $start);
            $remaining = $end - $start + 1;
            while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
                $chunk = fread($stream, min(131072, $remaining));
                if ($chunk === false || $chunk === '') break;
                echo $chunk;
                $remaining -= strlen($chunk);
            }
            fclose($stream);
            return true;
        }
        return false;
    }
}

require __DIR__ . '/api/public/index.php';
return true;
