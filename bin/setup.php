<?php
// Creates config.php and sets the admin password.
//
//   php bin/setup.php              create config (if missing) and prompt for a new admin password
//   php bin/setup.php --generate   same, but generate a random password and save it to data/admin-password.txt

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
$configFile = $root . '/config.php';

if (!is_file($configFile)) {
    $text = (string) file_get_contents($root . '/config.sample.php');
    $text = str_replace("'click_salt'          => ''", "'click_salt'          => " . var_export(bin2hex(random_bytes(32)), true), $text);
    file_put_contents($configFile, $text);
    chmod($configFile, 0640);
    echo "Created config.php\n";
}

if (in_array('--generate', $argv, true)) {
    $password = rtrim(strtr(base64_encode(random_bytes(15)), '+/', 'Ab'), '=');
    if (!is_dir($root . '/data')) {
        mkdir($root . '/data', 0775, true);
    }
    file_put_contents($root . '/data/admin-password.txt', $password . "\n");
    chmod($root . '/data/admin-password.txt', 0600);
    echo "Generated an admin password and saved it to data/admin-password.txt\n";
} else {
    echo 'New admin password (min 10 characters): ';
    system('stty -echo');
    $password = trim((string) fgets(STDIN));
    system('stty echo');
    echo "\n";
    if (mb_strlen($password) < 10) {
        exit("Password too short — nothing changed.\n");
    }
}

$text = (string) file_get_contents($configFile);
$hash = password_hash($password, PASSWORD_DEFAULT);
// A callback, because the hash contains "$" which preg_replace would read as back-references.
$text = (string) preg_replace_callback(
    "/'admin_password_hash'\s*=>\s*'[^']*'/",
    function () use ($hash) {
        return "'admin_password_hash' => " . var_export($hash, true);
    },
    $text
);
file_put_contents($configFile, $text);
echo "Admin password updated.\n";
