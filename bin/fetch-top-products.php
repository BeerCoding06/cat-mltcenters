<?php
// Fetches today's best-selling products from the Shopee Affiliate API and ranks the top N on the site.
// Run every morning from cron / launchd:
//
//   php bin/fetch-top-products.php
//   php bin/fetch-top-products.php --fixture=path/to/response.json   (test without calling the API)

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$fixture = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--fixture=') === 0) {
        $fixture = substr($arg, strlen('--fixture='));
    }
}

if ($fixture === null && !shopee_configured()) {
    fwrite(STDERR, "Shopee API is not configured yet — fill in shopee_api.app_id and shopee_api.secret in config.php\n");
    exit(1);
}

$result = fetch_top_products($fixture);
$result['finished_at'] = now();
file_put_contents(fetch_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo $result['finished_at'] . " fetched={$result['fetched']} created={$result['created']} updated={$result['updated']} hidden={$result['hidden']} errors=" . count($result['errors']) . "\n";
foreach ($result['top'] as $line) {
    echo "  $line\n";
}
foreach ($result['errors'] as $error) {
    echo "  ERROR $error\n";
}
exit($result['errors'] && !$result['top'] ? 2 : 0);
