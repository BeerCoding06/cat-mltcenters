<?php

/** Fields the admin form can set. */
const PROMO_FIELDS = ['title', 'subtitle', 'url', 'starts_at', 'ends_at', 'show_home', 'show_articles'];

/** Shopping-event articles, where promo banners also show: "โปร", "ลดราคา", or a double date like 11.11 / 12-12. */
const PROMO_ARTICLE_PATTERN = '/โปร|ลดราคา|\b(\d{1,2})[.-]\1\b/u';

/** Promos running right now, newest start first. $where is 'home' or 'articles'. */
function promos_live(string $where): array
{
    $flag = $where === 'home' ? 'show_home' : 'show_articles';
    $now = now();
    return db_all("SELECT * FROM promos WHERE $flag = 1 AND starts_at <= ? AND ends_at > ? ORDER BY starts_at DESC LIMIT 3", [$now, $now]);
}

function promo_get(int $id)
{
    return db_one('SELECT * FROM promos WHERE id = ?', [$id]);
}

function promo_link(array $promo): string
{
    return url('go/promo/' . (int) $promo['id']);
}

function promo_is_live(array $promo): bool
{
    $now = now();
    return $promo['starts_at'] <= $now && $promo['ends_at'] > $now;
}

function article_is_promo(array $article): bool
{
    return (bool) preg_match(PROMO_ARTICLE_PATTERN, $article['title'] . ' ' . $article['slug']);
}

/** Datetime-local form value ("2026-11-11T00:00") or stored value -> "Y-m-d H:i:s", or '' if unreadable. */
function promo_time(string $value): string
{
    $ts = strtotime(str_replace('T', ' ', trim($value)));
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
}

/** Returns [$data, $errors]. */
function promo_validate(array $in): array
{
    $d = [];
    $errors = [];
    $d['title'] = trim((string) ($in['title'] ?? ''));
    if ($d['title'] === '' || mb_strlen($d['title']) > 80) {
        $errors[] = 'ต้องมีชื่อแคมเปญ (ไม่เกิน 80 ตัวอักษร)';
    }
    $d['subtitle'] = mb_substr(trim((string) ($in['subtitle'] ?? '')), 0, 140);
    $d['url'] = trim((string) ($in['url'] ?? ''));
    if (!is_allowed_affiliate_url($d['url'])) {
        $errors[] = 'ลิงก์ต้องเป็นลิงก์ https ของ ' . implode(', ', (array) config('allowed_affiliate_hosts', []));
    }
    $d['starts_at'] = promo_time((string) ($in['starts_at'] ?? ''));
    $d['ends_at'] = promo_time((string) ($in['ends_at'] ?? ''));
    if ($d['starts_at'] === '' || $d['ends_at'] === '') {
        $errors[] = 'ต้องใส่วันเริ่มและวันจบ';
    } elseif ($d['ends_at'] <= $d['starts_at']) {
        $errors[] = 'วันจบต้องอยู่หลังวันเริ่ม';
    }
    $d['show_home'] = empty($in['show_home']) ? 0 : 1;
    $d['show_articles'] = empty($in['show_articles']) ? 0 : 1;
    return [$d, $errors];
}

function promo_save(array $d, $id = null): int
{
    $now = now();
    $params = [];
    foreach (PROMO_FIELDS as $c) {
        $params[] = $d[$c];
    }
    if ($id === null) {
        db_run('INSERT INTO promos (' . implode(', ', PROMO_FIELDS) . ', created_at, updated_at) VALUES ('
            . implode(', ', array_fill(0, count(PROMO_FIELDS), '?')) . ', ?, ?)', array_merge($params, [$now, $now]));
        return (int) db()->lastInsertId();
    }
    db_run('UPDATE promos SET ' . implode(' = ?, ', PROMO_FIELDS) . ' = ?, updated_at = ? WHERE id = ?',
        array_merge($params, [$now, $id]));
    return (int) $id;
}

function log_promo_click(array $promo): void
{
    if (!is_human_request()) {
        return;
    }
    db_run('INSERT INTO promo_clicks (promo_id, clicked_at, visitor_hash, referer) VALUES (?, ?, ?, ?)',
        [$promo['id'], now(), visitor_hash(), click_referer()]);
}
