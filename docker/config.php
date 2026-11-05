<?php
// Config used inside the container: config.sample.php, plus values from environment variables
// (Dokploy › Environment) and secrets generated once into the data volume by docker/init.php.
$config = require __DIR__ . '/config.sample.php';

$secretsFile = __DIR__ . '/data/secrets.php';
$secrets = is_file($secretsFile) ? require $secretsFile : [];
$config['click_salt'] = (string) ($secrets['click_salt'] ?? '');
$config['admin_password_hash'] = (string) ($secrets['admin_password_hash'] ?? '');

$config['site_url'] = rtrim((string) getenv('SITE_URL'), '/');
$config['shopee_api']['app_id'] = (string) getenv('SHOPEE_APP_ID');
$config['shopee_api']['secret'] = (string) getenv('SHOPEE_SECRET');
$config['thecatapi_key'] = (string) getenv('THECATAPI_KEY');

return $config;
