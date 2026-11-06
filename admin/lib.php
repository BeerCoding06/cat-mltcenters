<?php

function admin_session_start(): void
{
    $path = url('admin/');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(['path' => $path, 'httponly' => true, 'samesite' => 'Lax']);
    } else {
        session_set_cookie_params(0, $path, '', false, true);
    }
    session_name('deal_admin');
    // Keep sessions in the data volume: the container's /tmp is wiped on every deploy, which logged the admin out.
    $dir = APP_ROOT . '/data/sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) {
        session_save_path($dir);
    }
    session_start();
}

function admin_url(string $action = '', array $params = []): string
{
    if ($action !== '') {
        $params = ['a' => $action] + $params;
    }
    return url('admin/') . ($params ? '?' . http_build_query($params) : '');
}

function admin_redirect(string $action = '', array $params = []): void
{
    header('Location: ' . admin_url($action, $params), true, 303);
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Bad request');
    }
}

function flash(?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

function admin_header(string $title): void
{
    $flash = flash();
    ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Admin</title>
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body class="admin">
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="logo" href="<?= e(admin_url()) ?>"><span class="logo-mark">%</span>Admin</a>
      <?php if (is_logged_in()): ?>
      <nav class="admin-nav">
        <a href="<?= e(admin_url()) ?>">ภาพรวม</a>
        <a href="<?= e(admin_url('products')) ?>">สินค้า</a>
        <a href="<?= e(admin_url('edit')) ?>">+ เพิ่มสินค้า</a>
        <a href="<?= e(admin_url('import')) ?>">นำเข้า CSV</a>
        <a href="<?= e(admin_url('articles')) ?>">บทความ</a>
        <a href="<?= e(admin_url('promos')) ?>">แบนเนอร์โปร</a>
        <a href="<?= e(admin_url('clicks')) ?>">รายงานคลิก</a>
        <a href="<?= e(url()) ?>" target="_blank">ดูหน้าเว็บ ↗</a>
        <form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="linklike">ออกจากระบบ</button></form>
      </nav>
      <?php endif; ?>
    </div>
  </header>
  <main class="wrap admin-main">
    <?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>
    <?php
}

function admin_footer(): void
{
    echo "  </main>\n</body>\n</html>\n";
}
