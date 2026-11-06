<?php

/** Shares newly published articles on the Facebook Page (bin/post-facebook.php runs this every few minutes). */

function facebook_configured(): bool
{
    $fb = (array) config('facebook', []);
    return ($fb['page_id'] ?? '') !== '' && ($fb['page_token'] ?? '') !== '';
}

function facebook_status_file(): string
{
    return dirname((string) config('db_path')) . '/last-facebook.json';
}

/** Live articles not shared yet, published recently enough that a post still makes sense. Oldest first. */
function facebook_due_articles(): array
{
    $fb = (array) config('facebook', []);
    $since = date('Y-m-d H:i:s', time() - 3600 * (int) ($fb['max_age_hours'] ?? 24));
    return db_all('SELECT * FROM articles WHERE ' . ARTICLE_LIVE_SQL . ' AND published_at >= ? AND fb_post_id IS NULL
                   ORDER BY published_at LIMIT ' . max(1, (int) ($fb['per_run'] ?? 1)), [now(), $since]);
}

/** The post text: title, summary, link, and the affiliate notice the site shows on every article. */
function facebook_message(array $article): string
{
    $tags = ['#ทาสแมว', '#คัดมาเหมียว'];
    if ($article['category'] !== '') {
        array_unshift($tags, '#' . preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '', $article['category']));
    }
    return trim($article['title']) . "\n\n"
        . ($article['excerpt'] !== '' ? trim($article['excerpt']) . "\n\n" : '')
        . '👉 อ่านต่อ: ' . abs_url('a/' . rawurlencode($article['slug'])) . "\n\n"
        . implode(' ', $tags) . "\n"
        . '(บทความนี้มีลิงก์ Affiliate)';
}

/**
 * Posts each due article once. Returns ['posted' => [...], 'errors' => [...], 'stopped' => reason|null].
 * A failed article keeps fb_post_id empty and is tried again next run (until it is older than max_age_hours);
 * an invalid or expired token stops the run, since every other post would fail the same way.
 */
function facebook_post_due(): array
{
    $result = ['posted' => [], 'errors' => [], 'stopped' => null];
    if (!facebook_configured()) {
        $result['stopped'] = 'ยังไม่ได้ตั้งค่า FB_PAGE_TOKEN';
        return $result;
    }
    $fb = (array) config('facebook');
    foreach (facebook_due_articles() as $article) {
        try {
            list($status, $data) = http_post_json(rtrim($fb['graph_url'], '/') . '/' . rawurlencode($fb['page_id']) . '/feed', [
                'message'      => facebook_message($article),
                'link'         => abs_url('a/' . rawurlencode($article['slug'])),
                'access_token' => $fb['page_token'],
            ]);
        } catch (Throwable $ex) {
            $status = 0;
            $data = ['error' => ['message' => $ex->getMessage()]];
        }
        if ($status === 200 && !empty($data['id'])) {
            db_run('UPDATE articles SET fb_post_id = ?, fb_posted_at = ?, fb_error = NULL WHERE id = ?', [$data['id'], now(), $article['id']]);
            $result['posted'][] = $article['slug'];
            continue;
        }
        // Never keep the token in logs: Facebook's messages do not echo it, but trim them anyway.
        $message = mb_substr((string) ($data['error']['message'] ?? ('HTTP ' . $status)), 0, 300);
        db_run('UPDATE articles SET fb_error = ? WHERE id = ?', [$message, $article['id']]);
        $result['errors'][$article['slug']] = $message;
        if ((int) ($data['error']['code'] ?? 0) === 190 || $status === 401) {
            $result['stopped'] = 'token ใช้ไม่ได้หรือหมดอายุ — สร้าง Page Access Token ใหม่แล้วใส่ใน FB_PAGE_TOKEN';
            break;
        }
    }
    return $result;
}
