<?php
// OAuth return address for "เชื่อมบัญชี Pinterest" (admin › Pinterest). Registered as the app's redirect URI,
// which must be a plain path, so it lives in its own file instead of admin/?a=…
declare(strict_types=1);

require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
admin_session_start();
if (!is_logged_in()) {
    admin_redirect('login');
}

$state = (string) ($_SESSION['pinterest_state'] ?? '');
unset($_SESSION['pinterest_state']);
if ($state === '' || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
    flash('เชื่อมไม่สำเร็จ: ลิงก์หมดอายุ ลองกดเชื่อมใหม่');
} elseif (empty($_GET['code'])) {
    flash('ยกเลิกการเชื่อม Pinterest' . (!empty($_GET['error']) ? ' (' . mb_substr((string) $_GET['error'], 0, 80) . ')' : ''));
} else {
    try {
        $error = pinterest_connect((string) $_GET['code']);
    } catch (Throwable $ex) {
        $error = 'เชื่อมไม่สำเร็จ: ' . $ex->getMessage();
    }
    flash($error ?? 'เชื่อม Pinterest แล้ว เลือกบอร์ดที่จะปักหมุดด้านล่าง');
}
admin_redirect('pinterest');
