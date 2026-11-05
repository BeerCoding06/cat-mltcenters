<?php
// Runs at container start: creates data/secrets.php on the first run and applies ADMIN_PASSWORD when it changes.

$data = dirname(__DIR__) . '/data';
$file = $data . '/secrets.php';
$secrets = is_file($file) ? require $file : [];

if (empty($secrets['click_salt'])) {
    $secrets['click_salt'] = bin2hex(random_bytes(32));
}

$password = (string) getenv('ADMIN_PASSWORD');
if ($password !== '') {
    if (mb_strlen($password) < 10) {
        fwrite(STDERR, "ADMIN_PASSWORD must be at least 10 characters — admin password not changed\n");
    } elseif (!password_verify($password, (string) ($secrets['admin_password_hash'] ?? ''))) {
        $secrets['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        echo "Admin password set from ADMIN_PASSWORD\n";
    }
} elseif (empty($secrets['admin_password_hash'])) {
    echo "ADMIN_PASSWORD is not set — the admin page stays locked until it is\n";
}

file_put_contents($file, "<?php\nreturn " . var_export($secrets, true) . ";\n");
chmod($file, 0600);
