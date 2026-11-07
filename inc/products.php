<?php

const PLATFORMS = ['lazada' => 'Lazada', 'shopee' => 'Shopee'];

/** Fields an admin form or CSV row can set. */
const PRODUCT_FIELDS = [
    'platform', 'name', 'category', 'group_key', 'product_url', 'affiliate_url',
    'image_url', 'price', 'original_price', 'rating', 'sold', 'description', 'active',
];

function discount_sql(): string
{
    return 'CASE WHEN original_price > price THEN CAST(ROUND((1 - price / original_price) * 100) AS INTEGER) ELSE 0 END';
}

function product_columns(): string
{
    return '*, ' . discount_sql() . ' AS discount';
}

function product_link(array $product): string
{
    return url('p/' . rawurlencode($product['slug']));
}

function product_go_link(array $product): string
{
    return url('go/' . (int) $product['id']);
}

function category_link(string $category): string
{
    return url('c/' . rawurlencode($category));
}

function parse_number($value): float
{
    $clean = str_replace([',', '฿', ' ', 'บาท'], '', trim((string) $value));
    return is_numeric($clean) ? (float) $clean : 0.0;
}

/**
 * Normalises raw input (form post or CSV row) into a product record.
 * Returns [$data, $errors].
 */
function product_validate(array $in): array
{
    $d = [];
    $errors = [];

    $d['platform'] = strtolower(trim((string) ($in['platform'] ?? '')));
    if (!isset(PLATFORMS[$d['platform']])) {
        $errors[] = 'platform ต้องเป็น lazada หรือ shopee';
    }

    $d['name'] = trim((string) ($in['name'] ?? ''));
    if ($d['name'] === '' || mb_strlen($d['name']) > 300) {
        $errors[] = 'ต้องมีชื่อสินค้า (ไม่เกิน 300 ตัวอักษร)';
    }

    $d['category'] = mb_substr(trim((string) ($in['category'] ?? '')), 0, 60);
    $d['group_key'] = mb_substr(mb_strtolower(trim((string) ($in['group_key'] ?? ''))), 0, 100);
    $d['description'] = mb_substr(trim((string) ($in['description'] ?? '')), 0, 5000);

    $d['affiliate_url'] = trim((string) ($in['affiliate_url'] ?? ''));
    if (!is_allowed_affiliate_url($d['affiliate_url'])) {
        $errors[] = 'affiliate_url ต้องเป็นลิงก์ https ของ ' . implode(', ', (array) config('allowed_affiliate_hosts', []));
    }

    foreach (['product_url', 'image_url'] as $field) {
        $d[$field] = trim((string) ($in[$field] ?? ''));
        if ($d[$field] !== '' && !is_http_url($d[$field])) {
            $errors[] = $field . ' ไม่ใช่ URL ที่ถูกต้อง';
        }
    }

    $d['price'] = parse_number($in['price'] ?? '');
    if ($d['price'] <= 0) {
        $errors[] = 'price ต้องมากกว่า 0';
    }
    $d['original_price'] = max(0.0, parse_number($in['original_price'] ?? ''));
    $d['rating'] = min(5.0, max(0.0, parse_number($in['rating'] ?? '')));
    $d['sold'] = max(0, (int) parse_number($in['sold'] ?? ''));
    $d['active'] = (isset($in['active']) && (string) $in['active'] !== '') ? ((int) (bool) $in['active']) : 1;

    return [$d, $errors];
}

/** Ranking pages (/top/<category>) list this many products, and need at least TOP_MIN_PRODUCTS to exist. */
const TOP_LIMIT = 10;
const TOP_MIN_PRODUCTS = 3;

function top_link(string $category): string
{
    return url('top/' . rawurlencode($category));
}

/** Best sellers first (Shopee's sold count), then rating, then discount. */
function top_products(string $category): array
{
    return db_all('SELECT ' . product_columns() . ' FROM products WHERE active = 1 AND category = ?
                   ORDER BY sold DESC, rating DESC, discount DESC LIMIT ' . TOP_LIMIT, [$category]);
}

/** Categories with enough products for a ranking page, with their latest update time. */
function top_categories(): array
{
    return db_all("SELECT category, COUNT(*) AS n, MAX(updated_at) AS updated_at FROM products
                   WHERE active = 1 AND category <> '' GROUP BY category HAVING n >= " . TOP_MIN_PRODUCTS . ' ORDER BY n DESC, category');
}

function product_get(int $id)
{
    return db_one('SELECT ' . product_columns() . ' FROM products WHERE id = ?', [$id]);
}

function product_by_slug(string $slug)
{
    return db_one('SELECT ' . product_columns() . ' FROM products WHERE slug = ? AND active = 1', [$slug]);
}

/** Finds a product that a CSV row refers to: same product_url, else same affiliate_url. */
function product_find_existing(array $d)
{
    if ($d['product_url'] !== '') {
        $id = db_value('SELECT id FROM products WHERE product_url = ? LIMIT 1', [$d['product_url']]);
        if ($id) {
            return (int) $id;
        }
    }
    $id = db_value('SELECT id FROM products WHERE affiliate_url = ? LIMIT 1', [$d['affiliate_url']]);
    return $id ? (int) $id : null;
}

/** Inserts or updates a validated product and records a price point when the price changes. */
function product_save(array $d, $id = null): int
{
    $pdo = db();
    $now = now();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $cols = PRODUCT_FIELDS;
            $sql = 'INSERT INTO products (' . implode(', ', $cols) . ', slug, created_at, updated_at) VALUES ('
                . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?, ?)';
            $params = [];
            foreach ($cols as $c) {
                $params[] = $d[$c];
            }
            $params[] = 'tmp-' . bin2hex(random_bytes(8));
            $params[] = $now;
            $params[] = $now;
            db_run($sql, $params);
            $id = (int) $pdo->lastInsertId();
            // Short slug: Thai letters grow about nine-fold once URL-encoded.
            db_run('UPDATE products SET slug = ? WHERE id = ?', [slugify(mb_substr($d['name'], 0, 40)) . '-' . $id, $id]);
            db_run('INSERT INTO price_history (product_id, price, recorded_at) VALUES (?, ?, ?)', [$id, $d['price'], $now]);
        } else {
            $oldPrice = db_value('SELECT price FROM products WHERE id = ?', [$id]);
            if ($oldPrice === false) {
                throw new RuntimeException('Product not found: ' . $id);
            }
            $sets = [];
            $params = [];
            foreach (PRODUCT_FIELDS as $c) {
                $sets[] = $c . ' = ?';
                $params[] = $d[$c];
            }
            $params[] = $now;
            $params[] = $id;
            db_run('UPDATE products SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            if (abs((float) $oldPrice - $d['price']) > 0.001) {
                db_run('INSERT INTO price_history (product_id, price, recorded_at) VALUES (?, ?, ?)', [$id, $d['price'], $now]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return (int) $id;
}

/** Price points for the last $days days, plus the price in effect when the window started. */
function product_price_history(array $product, int $days = 90): array
{
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $points = db_all(
        'SELECT price, recorded_at FROM price_history WHERE product_id = ? AND recorded_at >= ? ORDER BY recorded_at',
        [$product['id'], $since]
    );
    $before = db_one(
        'SELECT price FROM price_history WHERE product_id = ? AND recorded_at < ? ORDER BY recorded_at DESC LIMIT 1',
        [$product['id'], $since]
    );
    if ($before) {
        array_unshift($points, ['price' => $before['price'], 'recorded_at' => $since]);
    }
    return $points;
}

/** Summary used on the product page: lowest/highest in 30 days and whether today is the lowest. */
function price_summary(array $product): array
{
    $points = product_price_history($product, 30);
    $prices = array_map('floatval', array_column($points, 'price'));
    $prices[] = (float) $product['price'];
    $min = min($prices);
    return [
        'min'       => $min,
        'max'       => max($prices),
        'is_lowest' => count(array_unique($prices)) > 1 && (float) $product['price'] <= $min,
    ];
}

function log_click(array $product): void
{
    if (!is_human_request()) {
        return;
    }
    db_run(
        'INSERT INTO clicks (product_id, clicked_at, visitor_hash, referer) VALUES (?, ?, ?, ?)',
        [$product['id'], now(), visitor_hash(), click_referer()]
    );
}

/** Where a click came from (host, or path for our own pages) — never full query strings. */
function click_referer(): string
{
    if (empty($_SERVER['HTTP_REFERER'])) {
        return '';
    }
    $parts = parse_url((string) $_SERVER['HTTP_REFERER']);
    $host = $parts['host'] ?? '';
    $ownHost = (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    return mb_substr(($host === $ownHost) ? rawurldecode($parts['path'] ?? '/') : $host, 0, 200);
}

/** Imports products from an open CSV handle. Updates rows that match an existing product. */
function import_csv($handle): array
{
    $result = ['created' => 0, 'updated' => 0, 'errors' => []];

    $header = fgetcsv($handle);
    if (!$header) {
        $result['errors'][1] = ['ไฟล์ว่างหรืออ่านไม่ได้'];
        return $result;
    }
    $header = array_map(function ($h) {
        return strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
    }, $header);
    $missing = array_diff(['platform', 'name', 'affiliate_url', 'price'], $header);
    if ($missing) {
        $result['errors'][1] = ['ไม่มีคอลัมน์: ' . implode(', ', $missing)];
        return $result;
    }

    // An optional top_rank column replaces today's best-seller list (the "ขายดีที่สุดวันนี้" section), the same
    // way bin/fetch-top-products.php does with the API — for when the list is picked by hand from the portal.
    $rankCol = array_search('top_rank', $header, true);
    if ($rankCol !== false) {
        db_run('UPDATE products SET top_rank = NULL WHERE top_rank IS NOT NULL');
    }

    $line = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $line++;
        if (count($row) === 1 && trim((string) $row[0]) === '') {
            continue;
        }
        $in = [];
        foreach ($header as $i => $col) {
            if (in_array($col, PRODUCT_FIELDS, true)) {
                $in[$col] = $row[$i] ?? '';
            }
        }

        list($status, $idOrErrors) = product_upsert($in);
        if ($status === 'error') {
            $result['errors'][$line] = $idOrErrors;
        } else {
            $result[$status]++;
            $rank = $rankCol !== false ? (int) parse_number($row[$rankCol] ?? '') : 0;
            if ($rank > 0) {
                db_run('UPDATE products SET top_rank = ? WHERE id = ?', [$rank, $idOrErrors]);
            }
        }
    }
    return $result;
}

/**
 * Creates a product, or updates the one with the same product_url / affiliate_url.
 * Fields missing from $in, or left empty, keep their current values on update.
 * Returns ['created'|'updated', $id] or ['error', $errors].
 */
function product_upsert(array $in): array
{
    list($d, $errors) = product_validate($in);
    if ($errors) {
        return ['error', $errors];
    }
    $id = product_find_existing($d);
    if ($id === null) {
        return ['created', product_save($d)];
    }
    $merged = array_merge(product_get($id), array_filter($in, function ($v) {
        return trim((string) $v) !== '';
    }));
    list($d, $errors) = product_validate($merged);
    if ($errors) {
        return ['error', $errors];
    }
    return ['updated', product_save($d, $id)];
}
