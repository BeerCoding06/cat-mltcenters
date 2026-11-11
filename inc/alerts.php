<?php

/** Send an alert only when the price is at least this much below the last price we told them about. */
const ALERT_MIN_DROP = 0.03;

/** Sign-ups one visitor can make per day (keeps the form from being used to spam addresses). */
const ALERT_DAILY_LIMIT = 5;

function mail_config(string $key): string
{
    return (string) (((array) config('mail', []))[$key] ?? '');
}

function alerts_enabled(): bool
{
    return mail_config('resend_key') !== '' && mail_config('from') !== '';
}

/** Sends one email through Resend. True when Resend accepted it. */
function mail_send(string $to, string $subject, string $html): bool
{
    try {
        $status = http_post_raw_json('https://api.resend.com/emails', [
            'from'    => mail_config('from'),
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ], 20, ['Authorization: Bearer ' . mail_config('resend_key')]);
    } catch (Throwable $ex) {
        error_log('mail_send: ' . $ex->getMessage());
        return false;
    }
    return $status >= 200 && $status < 300;
}

function alert_link(string $action, string $token): string
{
    return abs_url('alert/' . $action) . '?t=' . rawurlencode($token);
}

/** Email wrapper: one short message, the button, and the way out. */
function alert_email(string $intro, string $buttonText, string $buttonUrl, string $stopToken): string
{
    return '<div style="font-family:sans-serif;font-size:16px;line-height:1.6;color:#3b2a1f;max-width:520px">'
        . '<p>' . $intro . '</p>'
        . '<p><a href="' . e($buttonUrl) . '" style="display:inline-block;padding:10px 20px;border-radius:999px;background:#a6532a;color:#fff;text-decoration:none;font-weight:700">'
        . e($buttonText) . '</a></p>'
        . '<p style="font-size:13px;color:#8a7766">ได้รับอีเมลนี้เพราะมีคนกรอกอีเมลนี้ที่ ' . e((string) config('site_name')) . ' เพื่อรับแจ้งเตือนราคา'
        . ' · <a href="' . e(alert_link('stop', $stopToken)) . '" style="color:#8a7766">ยกเลิกการแจ้งเตือนสินค้านี้</a></p></div>';
}

/**
 * Starts an alert for one product: saves it unconfirmed and sends the confirm email.
 * Returns a message for the visitor. Asking again for the same product re-sends the confirm link.
 */
function alert_signup(array $product, string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
        return 'อีเมลไม่ถูกต้อง ลองตรวจอีกครั้ง';
    }
    $hash = visitor_hash();
    $today = (int) db_value('SELECT COUNT(*) FROM price_alerts WHERE created_at >= ? AND token LIKE ?', [date('Y-m-d 00:00:00'), substr($hash, 0, 8) . '%']);
    if ($today >= ALERT_DAILY_LIMIT) {
        return 'วันนี้ลงทะเบียนครบจำนวนแล้ว ลองใหม่พรุ่งนี้';
    }
    $row = db_one('SELECT * FROM price_alerts WHERE product_id = ? AND email = ?', [$product['id'], $email]);
    if ($row && $row['confirmed_at'] && !$row['stopped_at']) {
        return 'อีเมลนี้รับแจ้งเตือนสินค้านี้อยู่แล้ว';
    }
    // The token starts with the visitor's daily hash so the daily limit above can be counted without another column.
    $token = substr($hash, 0, 8) . bin2hex(random_bytes(16));
    if ($row) {
        db_run('UPDATE price_alerts SET token = ?, price = ?, created_at = ?, confirmed_at = NULL, stopped_at = NULL WHERE id = ?',
            [$token, $product['price'], now(), $row['id']]);
    } else {
        db_run('INSERT INTO price_alerts (product_id, email, token, price, created_at) VALUES (?, ?, ?, ?, ?)',
            [$product['id'], $email, $token, $product['price'], now()]);
    }
    $sent = mail_send($email, 'ยืนยันรับแจ้งเตือนราคา: ' . short_text($product['name'], 60), alert_email(
        'กดปุ่มด้านล่างเพื่อยืนยัน แล้วเราจะส่งอีเมลบอกเมื่อ <strong>' . e($product['name']) . '</strong> ราคาลดลงจาก '
        . e(baht($product['price'])) . ' ถ้าไม่ได้ขอรับแจ้งเตือนนี้ ไม่ต้องทำอะไร เราจะไม่ส่งอะไรไปอีก',
        'ยืนยันรับแจ้งเตือน', alert_link('confirm', $token), $token));
    return $sent ? 'ส่งลิงก์ยืนยันไปที่ ' . $email . ' แล้ว กดลิงก์ในอีเมลเพื่อเริ่มรับแจ้งเตือน (ถ้าไม่เห็น ลองดูในกล่องจดหมายขยะ)'
                 : 'ส่งอีเมลไม่สำเร็จ ลองใหม่อีกครั้งภายหลัง';
}

/** Handles /alert/confirm and /alert/stop links. Returns a message to show. */
function alert_handle_link(string $action, string $token): string
{
    $row = $token !== '' ? db_one('SELECT * FROM price_alerts WHERE token = ?', [$token]) : null;
    if (!$row) {
        return 'ลิงก์นี้ใช้ไม่ได้แล้ว';
    }
    if ($action === 'confirm') {
        db_run('UPDATE price_alerts SET confirmed_at = COALESCE(confirmed_at, ?), stopped_at = NULL WHERE id = ?', [now(), $row['id']]);
        return 'เรียบร้อย เราจะส่งอีเมลบอกเมื่อสินค้านี้ราคาลด';
    }
    db_run('UPDATE price_alerts SET stopped_at = ? WHERE id = ?', [now(), $row['id']]);
    return 'ยกเลิกการแจ้งเตือนสินค้านี้แล้ว';
}

/**
 * Emails everyone whose product is now at least ALERT_MIN_DROP below the price we last told them about,
 * then remembers the new price so the same drop is not sent twice. Run from the background loop.
 */
function alerts_send_due(): array
{
    $result = ['sent' => 0, 'failed' => 0];
    if (!alerts_enabled()) {
        return $result;
    }
    $due = db_all('SELECT a.*, p.name, p.slug, p.price AS now_price FROM price_alerts a JOIN products p ON p.id = a.product_id
                    WHERE a.confirmed_at IS NOT NULL AND a.stopped_at IS NULL AND p.active = 1 AND p.price <= a.price * ?
                    LIMIT 200', [1 - ALERT_MIN_DROP]);
    foreach ($due as $a) {
        $ok = mail_send($a['email'], 'ราคาลดแล้ว: ' . short_text($a['name'], 60) . ' เหลือ ' . baht($a['now_price']), alert_email(
            '<strong>' . e($a['name']) . '</strong> ตอนนี้ราคา <strong>' . e(baht($a['now_price'])) . '</strong> ลดจาก ' . e(baht($a['price']))
            . ' ที่เราแจ้งไว้ ราคาบนร้านเปลี่ยนได้ตลอด กดดูราคาล่าสุดก่อนสั่งซื้อ',
            'ดูสินค้าและราคาล่าสุด', abs_url('p/' . rawurlencode($a['slug'])) . '?utm_source=email', $a['token']));
        if ($ok) {
            db_run('UPDATE price_alerts SET price = ?, notified_at = ? WHERE id = ?', [$a['now_price'], now(), $a['id']]);
            $result['sent']++;
        } else {
            $result['failed']++;
        }
    }
    return $result;
}
