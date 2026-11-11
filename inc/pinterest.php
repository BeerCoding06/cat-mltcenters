<?php

/** Pins live articles that have a cover to one Pinterest board (bin/post-pinterest.php runs this every few minutes). */

/** Where the admin "เชื่อม Pinterest" page keeps the refresh token and chosen board (data/ is not web-accessible). */
function pinterest_store_file(): string
{
    return dirname((string) config('db_path')) . '/pinterest.json';
}

function pinterest_store(): array
{
    $file = pinterest_store_file();
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    return is_array($data) ? $data : [];
}

function pinterest_store_save(array $changes): void
{
    file_put_contents(pinterest_store_file(), json_encode($changes + pinterest_store(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @chmod(pinterest_store_file(), 0600);
}

/** Settings from config/env, with the refresh token and board saved by the admin connect page filling any gaps. */
function pinterest_config(): array
{
    $p = (array) config('pinterest', []);
    foreach (pinterest_store() as $key => $value) {
        if (in_array($key, ['refresh_token', 'board_id'], true) && (string) ($p[$key] ?? '') === '') {
            $p[$key] = $value;
        }
    }
    return $p + ['api_url' => 'https://api.pinterest.com/v5'];
}

/** App ID and secret are set (env), so the admin can connect an account. */
function pinterest_app_ready(): bool
{
    $p = pinterest_config();
    return ($p['app_id'] ?? '') !== '' && ($p['app_secret'] ?? '') !== '';
}

function pinterest_configured(): bool
{
    $p = pinterest_config();
    return pinterest_app_ready() && ($p['refresh_token'] ?? '') !== '' && ($p['board_id'] ?? '') !== '';
}

/** OAuth return address; register exactly this as the app's redirect URI on developers.pinterest.com. */
function pinterest_redirect_uri(): string
{
    return abs_url('admin/pinterest.php');
}

function pinterest_authorize_url(string $state): string
{
    return 'https://www.pinterest.com/oauth/?' . http_build_query([
        'client_id'     => pinterest_config()['app_id'],
        'redirect_uri'  => pinterest_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'boards:read,pins:read,pins:write',
        'state'         => $state,
    ]);
}

function pinterest_basic_auth(): array
{
    $p = pinterest_config();
    return ['Authorization: Basic ' . base64_encode($p['app_id'] . ':' . $p['app_secret'])];
}

/** Swaps the code from the OAuth redirect for tokens and keeps the refresh token. Returns an error or null. */
function pinterest_connect(string $code): ?string
{
    list($status, $data) = http_post_json(rtrim(pinterest_config()['api_url'], '/') . '/oauth/token', [
        'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => pinterest_redirect_uri(),
    ], 20, pinterest_basic_auth());
    if ($status !== 200 || empty($data['refresh_token'])) {
        return 'เชื่อมไม่สำเร็จ: ' . mb_substr((string) ($data['message'] ?? ('HTTP ' . $status)), 0, 200);
    }
    pinterest_store_save([
        'refresh_token'      => $data['refresh_token'],
        'refresh_expires_at' => date('Y-m-d H:i:s', time() + (int) ($data['refresh_token_expires_in'] ?? 0)),
        'connected_at'       => now(),
    ]);
    return null;
}

/** The connected account's boards as [id => name], or [] with the error. */
function pinterest_boards(): array
{
    list($token, $error) = pinterest_access_token();
    if (!$token) {
        return [[], $error];
    }
    try {
        $data = http_get_json(rtrim(pinterest_config()['api_url'], '/') . '/boards?page_size=100', ['Authorization: Bearer ' . $token]);
    } catch (Throwable $ex) {
        return [[], $ex->getMessage()];
    }
    $boards = [];
    foreach ((array) ($data['items'] ?? []) as $b) {
        $boards[(string) $b['id']] = (string) $b['name'];
    }
    return [$boards, null];
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
    ], 20, pinterest_basic_auth());
    if ($status === 200 && !empty($data['access_token'])) {
        // Pinterest may hand out a new refresh token; keep it when the current one came from the connect page.
        if (!empty($data['refresh_token']) && (string) (((array) config('pinterest', []))['refresh_token'] ?? '') === '') {
            pinterest_store_save(['refresh_token' => $data['refresh_token']]);
        }
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
