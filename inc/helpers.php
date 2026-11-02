<?php

function config(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** URL path of the app root, e.g. "/cat-mltcenters" under MAMP or "" at a domain root. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
        $base = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0)
            ? rtrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($appRoot, strlen($docRoot))), '/')
            : '';
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    $site = rtrim((string) config('site_url', ''), '/');
    if ($site !== '') {
        return $site . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $v;
}

/** Current request path relative to the app root, without leading/trailing slashes. */
function request_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path();
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    if ($path === '/index.php') {
        $path = '';
    }
    return trim($path, '/');
}

function baht($amount): string
{
    $amount = (float) $amount;
    $decimals = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
    return '฿' . number_format($amount, $decimals);
}

function thai_date(string $datetime): string
{
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($datetime);
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . ((int) date('Y', $ts) + 543);
}

function slugify(string $text): string
{
    $slug = mb_strtolower($text);
    $slug = (string) preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', $slug);
    $slug = trim(mb_substr(trim($slug, '-'), 0, 60), '-');
    return $slug === '' ? 'item' : $slug;
}

function is_http_url(string $value): bool
{
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

function is_allowed_affiliate_url(string $value): bool
{
    if (!is_http_url($value)) {
        return false;
    }
    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
    foreach ((array) config('allowed_affiliate_hosts', []) as $allowed) {
        $allowed = strtolower($allowed);
        if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
            return true;
        }
    }
    return false;
}

/** Renders views/<view>.php inside views/layout.php. Views may set $title, $description, etc. */
function render(string $view, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/views/layout.php';
}

function query_url(string $path, array $params): string
{
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null && $v !== 0 && $v !== '0';
    });
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}
