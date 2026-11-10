<?php

/** IndexNow (Bing, Yandex, Naver, Seznam; not Google): tells search engines about an article the moment it goes live. */
const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

/** The site's IndexNow key, derived from the click salt so it needs no extra secret. It is public by design. */
function indexnow_key(): string
{
    return substr(hash('sha256', 'indexnow|' . config('click_salt')), 0, 32);
}

/** Only a public https site can be verified by the search engines (skips local copies). */
function indexnow_enabled(): bool
{
    $site = (string) config('site_url', '');
    $host = (string) parse_url($site, PHP_URL_HOST);
    return strpos($site, 'https://') === 0 && $host !== '' && $host !== 'localhost' && strpos($host, '.') !== false
        && (string) config('click_salt') !== '';
}

function indexnow_status_file(): string
{
    return dirname((string) config('db_path')) . '/last-indexnow.json';
}

/**
 * Submits live articles not sent yet, in one request. Returns ['submitted' => [...slugs], 'error' => null|string].
 * On failure nothing is marked, so the next run tries the same articles again.
 */
function indexnow_submit_due(): array
{
    $result = ['submitted' => [], 'error' => null];
    if (!indexnow_enabled()) {
        $result['error'] = 'site_url is not a public https address';
        return $result;
    }
    $articles = db_all('SELECT id, slug FROM articles WHERE ' . ARTICLE_LIVE_SQL . ' AND indexnow_at IS NULL ORDER BY published_at LIMIT 500', [now()]);
    if (!$articles) {
        return $result;
    }
    $key = indexnow_key();
    $payload = [
        'host'        => (string) parse_url((string) config('site_url'), PHP_URL_HOST),
        'key'         => $key,
        'keyLocation' => abs_url($key . '.txt'),
        'urlList'     => array_map(function ($a) { return abs_url('a/' . rawurlencode($a['slug'])); }, $articles),
    ];
    try {
        $status = http_post_raw_json((string) config('indexnow_endpoint', INDEXNOW_ENDPOINT), $payload);
    } catch (Throwable $ex) {
        $result['error'] = $ex->getMessage();
        return $result;
    }
    // 200 = received, 202 = received, key check pending. Anything else (400/403/422/429) is retried next run.
    if ($status !== 200 && $status !== 202) {
        $result['error'] = 'IndexNow answered HTTP ' . $status;
        return $result;
    }
    $now = now();
    foreach ($articles as $a) {
        db_run('UPDATE articles SET indexnow_at = ? WHERE id = ?', [$now, $a['id']]);
        $result['submitted'][] = $a['slug'];
    }
    return $result;
}
