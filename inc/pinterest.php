<?php

/** Pins live articles that have a cover to one Pinterest board (bin/post-pinterest.php runs this every few minutes). */

function pinterest_config(): array
{
    return (array) config('pinterest', []);
}

function pinterest_configured(): bool
{
    $p = pinterest_config();
    return ($p['app_id'] ?? '') !== '' && ($p['app_secret'] ?? '') !== '' && ($p['refresh_token'] ?? '') !== '' && ($p['board_id'] ?? '') !== '';
}

function pinterest_status_file(): string
{
    return dirname((string) config('db_path')) . '/last-pinterest.json';
}

/**
 * A fresh access token from the refresh token (Pinterest access tokens last about a month, refresh tokens a year).
 * Returns [token|null, error|null].
 */
function pinterest_access_token(): array
{
    $p = pinterest_config();
    list($status, $data) = http_post_json(rtrim($p['api_url'], '/') . '/oauth/token', [
        'grant_type' => 'refresh_token', 'refresh_token' => $p['refresh_token'],
    ], 20, ['Authorization: Basic ' . base64_encode($p['app_id'] . ':' . $p['app_secret'])]);
    if ($status === 200 && !empty($data['access_token'])) {
        return [$data['access_token'], null];
    }
    return [null, 'ขอ access token ไม่ได้ (refresh token หมดอายุหรือผิด): ' . mb_substr((string) ($data['message'] ?? ('HTTP ' . $status)), 0, 200)];
}

/**
 * The next article to pin: live, has a cover, not pinned yet, newest first so fresh posts go out before the backlog.
 * Null while the last pin is younger than gap_minutes.
 */
function pinterest_due_article()
{
    $gap = max(10, (int) (pinterest_config()['gap_minutes'] ?? 120));
    $last = (string) db_value('SELECT MAX(pinned_at) FROM articles');
    if ($last !== '' && strtotime($last) > time() - 60 * $gap) {
        return null;
    }
    return db_one('SELECT * FROM articles WHERE ' . ARTICLE_LIVE_SQL . " AND cover_image <> '' AND pin_id IS NULL
                   ORDER BY pin_error IS NOT NULL, published_at DESC LIMIT 1", [now()]);
}

/** Pin fields: Pinterest allows a 100-character title and a 500-character description. */
function pinterest_pin(array $article): array
{
    $cover = article_cover_url($article);
    return [
        'board_id'     => (string) pinterest_config()['board_id'],
        'title'        => mb_substr(trim($article['title']), 0, 100),
        'description'  => mb_substr(trim($article['excerpt'] !== '' ? $article['excerpt'] : $article['title']) . ' #ทาสแมว #คัดมาเหมียว', 0, 500),
        'link'         => abs_url('a/' . rawurlencode($article['slug'])) . '?utm_source=pinterest',
        'alt_text'     => mb_substr(trim($article['title']), 0, 500),
        'media_source' => ['source_type' => 'image_url', 'url' => $cover !== '' && $cover[0] === '/' ? abs_url(ltrim($cover, '/')) : $cover],
    ];
}

/** Pins at most one due article. Returns ['pinned' => [...], 'errors' => [...], 'stopped' => reason|null]. */
function pinterest_post_due(): array
{
    $result = ['pinned' => [], 'errors' => [], 'stopped' => null];
    if (!pinterest_configured()) {
        $result['stopped'] = 'ยังไม่ได้ตั้งค่า PINTEREST_*';
        return $result;
    }
    $article = pinterest_due_article();
    if (!$article) {
        return $result;
    }
    try {
        list($token, $error) = pinterest_access_token();
    } catch (Throwable $ex) {
        list($token, $error) = [null, $ex->getMessage()];
    }
    if (!$token) {
        $result['stopped'] = $error;
        return $result;
    }
    try {
        list($status, $data) = http_post_json_body(rtrim(pinterest_config()['api_url'], '/') . '/pins', pinterest_pin($article),
            ['Authorization: Bearer ' . $token]);
    } catch (Throwable $ex) {
        $status = 0;
        $data = ['message' => $ex->getMessage()];
    }
    if ($status >= 200 && $status < 300 && !empty($data['id'])) {
        db_run('UPDATE articles SET pin_id = ?, pinned_at = ?, pin_error = NULL WHERE id = ?', [$data['id'], now(), $article['id']]);
        $result['pinned'][] = $article['slug'];
        return $result;
    }
    $message = mb_substr((string) ($data['message'] ?? ('HTTP ' . $status)), 0, 300);
    // Remember the failure time too, so a pin Pinterest keeps refusing does not block the gap or loop every run.
    db_run('UPDATE articles SET pin_error = ?, pinned_at = ? WHERE id = ?', [$message, now(), $article['id']]);
    $result['errors'][$article['slug']] = $message;
    return $result;
}
