<?php
// Config used inside the container: config.sample.php, plus values from environment variables
// (Dokploy › Environment) and secrets generated once into the data volume by docker/init.php.
$config = require __DIR__ . '/config.sample.php';

$secretsFile = __DIR__ . '/data/secrets.php';
$secrets = is_file($secretsFile) ? require $secretsFile : [];
$config['click_salt'] = (string) ($secrets['click_salt'] ?? '');
$config['admin_password_hash'] = (string) ($secrets['admin_password_hash'] ?? '');

// Links built outside a web request (Facebook posts from the background job) need the public address.
$config['site_url'] = rtrim((string) (getenv('SITE_URL') ?: 'https://cat.mltcenters.com'), '/');
$config['shopee_api']['app_id'] = (string) getenv('SHOPEE_APP_ID');
$config['shopee_api']['secret'] = (string) getenv('SHOPEE_SECRET');
$config['thecatapi_key'] = (string) getenv('THECATAPI_KEY');
if (getenv('LINE_OA_URL')) {
    $config['line_oa_url'] = (string) getenv('LINE_OA_URL');
}
$config['facebook']['page_token'] = (string) getenv('FB_PAGE_TOKEN');
if (getenv('FB_GRAPH_URL')) {
    $config['facebook']['graph_url'] = rtrim((string) getenv('FB_GRAPH_URL'), '/');
}

return $config;
