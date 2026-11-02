<?php
// Router for PHP's built-in server (local development only):
//   php -S localhost:8765 router.php
// Mirrors the rules in .htaccess.

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/(data|inc|bin|views|docs|deploy)(/|$)|^/README\.md$|^/(config|config\.sample|router)\.php$|/\.#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

$file = __DIR__ . $path;
if ($path !== '/' && (is_file($file) || is_dir($file))) {
    return false;
}

require __DIR__ . '/index.php';
