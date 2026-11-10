<?php
namespace app\support;

final class MediaUrl
{
    private static function localPath(string $value): ?string
    {
        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/uploads/[A-Za-z0-9/_\.-]+$#', $path)) return null;
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') return null;
        }
        return $path;
    }

    public static function forStorage(string $value, string $baseUrl): string
    {
        $path = self::localPath($value);
        if ($path === null || $value === $path) return $value;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)
            || parse_url($value, PHP_URL_QUERY) !== null
            || parse_url($value, PHP_URL_FRAGMENT) !== null) return $value;
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if (!in_array($host, array_filter([$siteHost, 'localhost', '127.0.0.1']), true)) return $value;
        // Only URLs from our site or a previous local preview can become local paths.
        return is_file(dirname(__DIR__, 2) . '/public' . $path) ? $path : $value;
    }

    public static function normalize($value, string $baseUrl)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) $value[$key] = self::normalize($item, $baseUrl);
            return $value;
        }
        return is_string($value) ? self::forStorage($value, $baseUrl) : $value;
    }

    public static function forResponse($value, string $baseUrl)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) $value[$key] = self::forResponse($item, $baseUrl);
            return $value;
        }
        return is_string($value) && self::localPath($value) === $value
            ? rtrim($baseUrl, '/') . $value : $value;
    }
}
