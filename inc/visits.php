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

/** Counts a public page view: one row per visitor per day. */
function log_visit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !is_human_request()) {
        return;
    }
    try {
        db_run(
            'INSERT INTO visits (day, visitor_hash, views) VALUES (?, ?, 1)
             ON CONFLICT (day, visitor_hash) DO UPDATE SET views = views + 1',
            [date('Y-m-d'), visitor_hash()]
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
