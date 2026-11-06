<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(500);
    exit("Missing config.php — run: php bin/setup.php\n");
}

$GLOBALS['config'] = require APP_ROOT . '/config.php';

require APP_ROOT . '/inc/helpers.php';
require APP_ROOT . '/inc/db.php';
require APP_ROOT . '/inc/products.php';
require APP_ROOT . '/inc/promos.php';
require APP_ROOT . '/inc/visits.php';
require APP_ROOT . '/inc/photos.php';
require APP_ROOT . '/inc/articles.php';
require APP_ROOT . '/inc/shopee.php';
require APP_ROOT . '/inc/illustrations.php';
require APP_ROOT . '/inc/cat-presets.php';
require APP_ROOT . '/inc/http.php';
require APP_ROOT . '/inc/facebook.php';
require APP_ROOT . '/inc/breeds.php';
require APP_ROOT . '/inc/news.php';

date_default_timezone_set((string) config('timezone', 'Asia/Bangkok'));
mb_internal_encoding('UTF-8');
