<?php

const ARTICLE_FIELDS = ['title', 'slug', 'category', 'excerpt', 'cover_image', 'body', 'status', 'published_at'];

/** Where-clause for articles the public may see: published, and the scheduled time has come. */
const ARTICLE_LIVE_SQL = "status = 'published' AND published_at <= ?";

/** Folder of article files (front matter + body) that "import" turns into scheduled drafts. */
const ARTICLE_FILES_DIR = APP_ROOT . '/content/articles';

function article_link(array $article): string
{
    return url('a/' . rawurlencode($article['slug']));
}

/** Secret link that lets the admin view a draft before publishing. */
function article_preview_token(array $article): string
{
    return substr(hash_hmac('sha256', 'preview:' . $article['id'], (string) config('click_salt')), 0, 20);
}

function article_get(int $id)
{
    return db_one('SELECT * FROM articles WHERE id = ?', [$id]);
}

function article_by_slug(string $slug)
{
    return db_one('SELECT * FROM articles WHERE slug = ?', [$slug]);
}

function articles_published(int $limit, ?string $category = null, int $excludeId = 0): array
{
    $sql = 'SELECT * FROM articles WHERE ' . ARTICLE_LIVE_SQL . ' AND id <> ?';
    $params = [now(), $excludeId];
    if ($category !== null) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    $params[] = $limit;
    return db_all($sql . ' ORDER BY published_at DESC LIMIT ?', $params);
}

/** Returns [$data, $errors]. */
function article_validate(array $in): array
{
    $d = [];
    $errors = [];
    $d['title'] = trim((string) ($in['title'] ?? ''));
    if ($d['title'] === '' || mb_strlen($d['title']) > 200) {
        $errors[] = 'ต้องมีชื่อบทความ (ไม่เกิน 200 ตัวอักษร)';
    }
    $d['slug'] = trim((string) ($in['slug'] ?? ''));
    $d['category'] = mb_substr(trim((string) ($in['category'] ?? '')), 0, 60);
    $d['excerpt'] = mb_substr(trim((string) ($in['excerpt'] ?? '')), 0, 300);
    $d['cover_image'] = trim((string) ($in['cover_image'] ?? ''));
    // A full URL, or a file on this site such as /assets/img/articles/x.webp
    if ($d['cover_image'] !== '' && !is_http_url($d['cover_image']) && !preg_match('#^/[\w./-]+$#', $d['cover_image'])) {
        $errors[] = 'ลิงก์รูปปกไม่ถูกต้อง';
    }
    $d['body'] = str_replace("\r\n", "\n", (string) ($in['body'] ?? ''));
    $d['status'] = ($in['status'] ?? '') === 'published' ? 'published' : 'draft';
    // Optional publish time (datetime-local "2026-11-06T08:00" or "2026-11-06 08:00"); empty = when published.
    $d['published_at'] = null;
    $when = trim((string) ($in['published_at'] ?? ''));
    if ($when !== '') {
        $ts = strtotime(str_replace('T', ' ', $when));
        if ($ts === false) {
            $errors[] = 'เวลาเผยแพร่ไม่ถูกต้อง';
        } else {
            $d['published_at'] = date('Y-m-d H:i:s', $ts);
        }
    }
    return [$d, $errors];
}

/** Picks a slug from the requested one (or the title) that no other article uses. */
function article_unique_slug(string $wanted, int $id): string
{
    $base = slugify($wanted);
    $taken = db_value('SELECT COUNT(*) FROM articles WHERE slug = ? AND id <> ?', [$base, $id]);
    return $taken ? $base . '-' . $id : $base;
}

function article_save(array $d, $id = null): int
{
    $pdo = db();
    $now = now();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            db_run(
                'INSERT INTO articles (title, slug, category, excerpt, cover_image, body, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$d['title'], 'tmp-' . bin2hex(random_bytes(8)), $d['category'], $d['excerpt'], $d['cover_image'], $d['body'], $d['status'], $now, $now]
            );
            $id = (int) $pdo->lastInsertId();
            $publishedAt = null;
        } else {
            $publishedAt = db_value('SELECT published_at FROM articles WHERE id = ?', [$id]);
        }
        if ($d['published_at'] !== null) {
            $publishedAt = $d['published_at'];
        } elseif ($d['status'] === 'published' && !$publishedAt) {
            $publishedAt = $now;
        }
        db_run(
            'UPDATE articles SET title = ?, slug = ?, category = ?, excerpt = ?, cover_image = ?, body = ?, status = ?,
                                 published_at = ?, updated_at = ? WHERE id = ?',
            [$d['title'], article_unique_slug($d['slug'] !== '' ? $d['slug'] : $d['title'], $id), $d['category'],
             $d['excerpt'], $d['cover_image'], $d['body'], $d['status'], $publishedAt ?: null, $now, $id]
        );
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return (int) $id;
}

function reading_minutes(string $body): int
{
    // Thai has no spaces between words, so estimate from characters (~900 per minute).
    return max(1, (int) ceil(mb_strlen(strip_tags($body)) / 900));
}

/**
 * Renders the article body. Supported syntax (one per line):
 *   ## Heading / ### Sub-heading      - list item / 1. numbered item      > tip box
 *   **bold**   [link text](https://… or /path)
 *   [product:12]                      one product card
 *   [products:ทรายแมว:4]               top deals in a category (limit optional, max 12)
 *   [compare:tofu-litter-6l]          price table for one group_key
 * Returns ['html' => string, 'toc' => [[id, text], …]].
 */
/** Products embedded with [product:ID] lines, in the order the article mentions them (active ones only). */
function article_products(string $body): array
{
    preg_match_all('/^\[product:(\d+)\]\s*$/mu', $body, $m);
    $ids = array_values(array_unique(array_map('intval', $m[1])));
    if (!$ids) {
        return [];
    }
    $rows = db_all('SELECT ' . product_columns() . ' FROM products WHERE active = 1 AND id IN (' . implode(',', $ids) . ')');
    $byId = array_column($rows, null, 'id');
    return array_values(array_filter(array_map(function ($id) use ($byId) {
        return $byId[$id] ?? null;
    }, $ids)));
}

function article_render(string $body): array
{
    $html = '';
    $toc = [];
    $para = [];
    $list = null;

    $flush = function () use (&$html, &$para, &$list) {
        if ($list !== null) {
            $html .= "</$list>\n";
            $list = null;
        }
        if ($para) {
            $html .= '<p>' . md_inline(implode(' ', $para)) . "</p>\n";
            $para = [];
        }
    };

    foreach (explode("\n", $body) as $raw) {
        $line = rtrim($raw);
        if (trim($line) === '') {
            $flush();
        } elseif (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $m)) {
            $flush();
            $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
            if ($tag === 'h2') {
                $id = 'section-' . (count($toc) + 1);
                $toc[] = [$id, $m[2]];
                $html .= '<h2 id="' . $id . '">' . md_inline($m[2]) . "</h2>\n";
            } else {
                $html .= '<h3>' . md_inline($m[2]) . "</h3>\n";
            }
        } elseif (preg_match('/^\[(product|products|compare):([^\]]+)\]$/u', trim($line), $m)) {
            $flush();
            $html .= article_embed($m[1], $m[2]);
        } elseif (preg_match('/^>\s?(.*)$/u', $line, $m)) {
            $flush();
            $html .= '<aside class="tip">' . md_inline($m[1]) . "</aside>\n";
        } elseif (preg_match('/^(?:[-*]|(\d+)\.)\s+(.+)$/u', $line, $m)) {
            $type = $m[1] !== '' ? 'ol' : 'ul';
            if ($para || $list !== $type) {
                $flush();
                $html .= "<$type>\n";
                $list = $type;
            }
            $html .= '<li>' . md_inline($m[2]) . "</li>\n";
        } else {
            if ($list !== null) {
                $flush();
            }
            $para[] = trim($line);
        }
    }
    $flush();

    return ['html' => $html, 'toc' => $toc];
}

function md_inline(string $text): string
{
    $out = e($text);
    $out = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $out);
    return (string) preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]+)\)/u', function ($m) {
        $href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if ($href[0] === '/') {
            return '<a href="' . e(url($href)) . '">' . $m[1] . '</a>';
        }
        return '<a href="' . e($href) . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>';
    }, $out);
}

function article_embed(string $type, string $arg): string
{
    $arg = trim($arg);
    if ($type === 'product') {
        $p = db_one('SELECT ' . product_columns() . ' FROM products WHERE id = ? AND active = 1', [(int) $arg]);
        $products = $p ? [$p] : [];
    } elseif ($type === 'products') {
        $parts = explode(':', $arg);
        $limit = min(12, max(1, (int) ($parts[1] ?? 4)));
        $products = db_all(
            'SELECT ' . product_columns() . ' FROM products WHERE category = ? AND active = 1 ORDER BY discount DESC, sold DESC LIMIT ?',
            [trim($parts[0]), $limit]
        );
    } else {
        $products = db_all(
            'SELECT ' . product_columns() . ' FROM products WHERE group_key = ? AND active = 1 ORDER BY price',
            [mb_strtolower($arg)]
        );
        if ($products) {
            ob_start();
            require APP_ROOT . '/views/partials/compare-table.php';
            return (string) ob_get_clean();
        }
    }
    if (!$products) {
        return '';
    }
    ob_start();
    echo '<div class="grid grid-embed">';
    foreach ($products as $p) {
        require APP_ROOT . '/views/partials/card.php';
    }
    echo "</div>\n";
    return (string) ob_get_clean();
}

function article_is_live(array $article): bool
{
    return $article['status'] === 'published' && (string) $article['published_at'] <= now();
}

/**
 * Creates an article for every file in content/articles whose slug is not in the database yet, keeping its publish_at.
 * With $publish they are approved straight away and each goes live on its own at publish_at; otherwise they are drafts.
 * Existing articles are never touched, so edits made in the admin survive a re-import.
 * Returns ['created' => n, 'skipped' => n, 'errors' => [file => message]].
 */
function articles_import_files(string $dir = ARTICLE_FILES_DIR, bool $publish = false): array
{
    $result = ['created' => 0, 'skipped' => 0, 'errors' => []];
    foreach (glob($dir . '/*.md') ?: [] as $file) {
        $name = basename($file);
        $text = str_replace("\r\n", "\n", (string) file_get_contents($file));
        if (!preg_match('/^---\n(.*?)\n---\n(.*)$/s', $text, $m)) {
            $result['errors'][$name] = 'ไม่มีส่วนหัว --- ... ---';
            continue;
        }
        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/u', $line, $kv)) {
                $meta[$kv[1]] = trim($kv[2]);
            }
        }
        $in = [
            'title'        => $meta['title'] ?? '',
            'slug'         => $meta['slug'] ?? '',
            'category'     => $meta['category'] ?? '',
            'excerpt'      => $meta['excerpt'] ?? '',
            'cover_image'  => $meta['cover_image'] ?? '',
            'published_at' => $meta['publish_at'] ?? '',
            'body'         => trim($m[2]) . "\n",
            'status'       => $publish && ($meta['publish_at'] ?? '') !== '' ? 'published' : 'draft',
        ];
        if ($in['slug'] !== '' && article_by_slug(slugify($in['slug']))) {
            $result['skipped']++;
            continue;
        }
        list($d, $errors) = article_validate($in);
        if ($errors) {
            $result['errors'][$name] = implode(' / ', $errors);
            continue;
        }
        article_save($d);
        $result['created']++;
    }
    return $result;
}

/** Approves every draft that has a publish time: each goes live on its own when that time comes. */
function articles_approve_scheduled(): int
{
    return db_run(
        "UPDATE articles SET status = 'published', updated_at = ? WHERE status = 'draft' AND published_at IS NOT NULL",
        [now()]
    );
}

/** Image of the first [product:ID] in the body, used as the share picture when the article has no cover. */
function article_first_product_image(string $body)
{
    if (!preg_match('/^\[product:(\d+)\]$/m', $body, $m)) {
        return null;
    }
    $image = db_value('SELECT image_url FROM products WHERE id = ?', [(int) $m[1]]);
    return $image ? (string) $image : null;
}

/** File listing a stock photo for each article: {slug: {image, author, source}}. */
const ARTICLE_COVERS_FILE = APP_ROOT . '/content/article-covers.json';

/**
 * Give every article that has no cover yet the photo listed for it in content/article-covers.json
 * (articles already in the database included). A cover set by hand in the admin is never replaced.
 */
function articles_apply_covers(string $file = ARTICLE_COVERS_FILE): int
{
    $covers = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $count = 0;
    foreach (is_array($covers) ? $covers : [] as $slug => $c) {
        if (empty($c['image'])) {
            continue;
        }
        $count += db_run(
            "UPDATE articles SET cover_image = ?, cover_credit = ? WHERE slug = ? AND cover_image = ''",
            [(string) $c['image'], json_encode(['author' => (string) ($c['author'] ?? ''), 'source' => (string) ($c['source'] ?? '')],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $slug]
        );
    }
    return $count;
}

/** ['author' =>, 'source' =>] of a stock cover photo, or null. */
function article_cover_credit(array $article)
{
    $c = json_decode((string) ($article['cover_credit'] ?? ''), true);
    return is_array($c) && ($c['author'] ?? '') !== '' ? $c : null;
}

/** Cover as a URL for this page ('/assets/…' gets the base path) — or ''. */
function article_cover_url(array $article): string
{
    $cover = (string) $article['cover_image'];
    if (strpos($cover, '/assets/') === 0) {
        return asset(substr($cover, strlen('/assets/')));
    }
    return $cover !== '' && $cover[0] === '/' ? url(ltrim($cover, '/')) : $cover;
}
