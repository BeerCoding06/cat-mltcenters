<?php

/** False for empty user agents and the usual bots, crawlers and scripts. */
function is_human_request(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $ua !== '' && !preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|python/i', $ua);
}

/**
 * Visitor's IP. Behind Cloudflare and Traefik, REMOTE_ADDR is the proxy, so every visitor would look the same;
 * the forwarded headers carry the real address. Only used inside the salted hash below, never stored.
 */
function client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Salted hash that changes every day, so visitors are counted without keeping their IP. */
function visitor_hash(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return hash('sha256', config('click_salt') . '|' . client_ip() . '|' . $ua . '|' . date('Y-m-d'));
}

/** Daily visitor goal shown in the admin dashboard. */
const VISIT_GOAL = 300;

/** Known referrers by host, matched in order. utm_source=… on a link wins over the referrer. */
const VISIT_SOURCES = [
    'google' => 'Google', 'bing' => 'Bing', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'line.' => 'LINE',
    'tiktok' => 'TikTok', 'pantip' => 'Pantip', 'youtube' => 'YouTube', 'instagram' => 'Instagram', 'yahoo' => 'Yahoo',
    'duckduckgo' => 'DuckDuckGo', 'shopee' => 'Shopee', 'lazada' => 'Lazada', 't.co' => 'X',
];

/** Where this request came from, in a few words: 'Google', 'LINE', 'เว็บอื่น: example.com' or 'เข้าตรง'. */
function visit_source(): string
{
    $utm = strtolower(trim((string) ($_GET['utm_source'] ?? '')));
    $host = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
    $ownHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    foreach (array_unique(VISIT_SOURCES) as $name) {
        if ($utm === strtolower($name)) {
            return $name;
        }
    }
    foreach ([$utm, $host] as $hint) {
        if ($hint === '') {
            continue;
        }
        foreach (VISIT_SOURCES as $needle => $name) {
            if (strpos($hint, $needle) !== false) {
                return $name;
            }
        }
    }
    if ($utm !== '') {
        return mb_substr($utm, 0, 30);
    }
    if ($host === '' || $host === $ownHost) {
        return 'เข้าตรง';
    }
    return 'เว็บอื่น: ' . mb_substr(preg_replace('/^www\./', '', $host), 0, 40);
}

/** Counts a public page view: one row per visitor per day, remembering how their first view arrived. */
function log_visit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !is_human_request()) {
        return;
    }
    try {
        db_run(
            'INSERT INTO visits (day, visitor_hash, views, source, landing) VALUES (?, ?, 1, ?, ?)
             ON CONFLICT (day, visitor_hash) DO UPDATE SET views = views + 1',
            [date('Y-m-d'), visitor_hash(), visit_source(), mb_substr('/' . request_path(), 0, 120)]
        );
    } catch (PDOException $e) {
        // A busy database must never break the page; this view just goes uncounted.
    }
}

/** ['today' => visitors today, 'total' => visitors summed over all days]. */
function visit_stats(): array
{
    $row = db_one(
        'SELECT COUNT(*) AS total, SUM(day = ?) AS today FROM visits',
        [date('Y-m-d')]
    );
    return ['today' => (int) ($row['today'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
}
