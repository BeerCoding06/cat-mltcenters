<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$path = request_path();

if ($path === '') {
    page_listing($_GET);
} elseif (preg_match('#^c/(.+)$#u', $path, $m)) {
    page_listing($_GET, $m[1]);
} elseif (preg_match('#^p/(.+)$#u', $path, $m)) {
    page_product($m[1]);
} elseif (preg_match('#^top/(.+)$#u', $path, $m)) {
    page_top($m[1]);
} elseif ($path === 'breeds') {
    page_breeds();
} elseif (preg_match('#^breed/(.+)$#u', $path, $m)) {
    page_breed($m[1]);
} elseif ($path === 'news') {
    page_news($_GET);
} elseif ($path === 'blog') {
    page_blog($_GET);
} elseif (preg_match('#^a/(.+)$#u', $path, $m)) {
    page_article($m[1], $_GET);
} elseif (preg_match('#^go/(\d+)$#', $path, $m)) {
    page_go((int) $m[1]);
} elseif (preg_match('#^go/promo/(\d+)$#', $path, $m)) {
    page_go_promo((int) $m[1]);
} elseif (preg_match('#^alert/(\d+)$#', $path, $m)) {
    page_alert_signup((int) $m[1]);
} elseif (preg_match('#^alert/(confirm|stop)$#', $path, $m)) {
    render('alert', ['message' => alert_handle_link($m[1], (string) ($_GET['t'] ?? '')), 'product' => null]);
} elseif (preg_match('#^media/breeds/([^/]+\.(?:jpg|png|webp))$#u', $path, $m)) {
    page_media(BREED_PHOTO_DIR, $m[1]);
} elseif ($path === 'sitemap.xml') {
    page_sitemap();
} elseif ($path === 'robots.txt') {
    page_robots();
} elseif ($path === indexnow_key() . '.txt') {
    // IndexNow key file: proves to Bing & co. that submissions really come from this site.
    header('Content-Type: text/plain; charset=utf-8');
    echo indexnow_key();
} elseif ($path === 'about') {
    render('about');
} elseif ($path === 'privacy') {
    render('privacy');
} elseif ($path === 'game') {
    render('game', ['articles' => articles_published(3)]);
} elseif ($path === 'deals') {
    page_deals();
} elseif (preg_match('#^sale/([\d-]+)$#', $path, $m)) {
    page_campaign($m[1]);
} else {
    render('404', [], 404);
}

function page_listing(array $q, $category = null): void
{
    $sorts = [
        'discount'   => 'discount DESC, sold DESC',
        'popular'    => 'sold DESC',
        'price_asc'  => 'price ASC',
        'price_desc' => 'price DESC',
        'newest'     => 'id DESC',
    ];
    $platform = isset(PLATFORMS[$q['platform'] ?? '']) ? (string) $q['platform'] : '';
    $search = mb_substr(trim((string) ($q['q'] ?? '')), 0, 100);
    $sort = isset($sorts[$q['sort'] ?? '']) ? (string) $q['sort'] : 'discount';
    $minDiscount = min(90, max(0, (int) ($q['min'] ?? 0)));
    $page = max(1, (int) ($q['page'] ?? 1));
    $perPage = (int) config('per_page', 24);

    $where = ['active = 1'];
    $params = [];
    if ($category !== null) {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    if ($platform !== '') {
        $where[] = 'platform = ?';
        $params[] = $platform;
    }
    if ($search !== '') {
        $where[] = "name LIKE ? ESCAPE '\\'";
        $params[] = '%' . addcslashes($search, '%_\\') . '%';
    }
    if ($minDiscount > 0) {
        $where[] = discount_sql() . ' >= ?';
        $params[] = $minDiscount;
    }
    $whereSql = implode(' AND ', $where);

    $total = (int) db_value("SELECT COUNT(*) FROM products WHERE $whereSql", $params);
    if ($category !== null && $total === 0 && $platform === '' && $search === '' && $minDiscount === 0) {
        render('404', [], 404);
        return;
    }

    $products = db_all(
        'SELECT ' . product_columns() . " FROM products WHERE $whereSql ORDER BY {$sorts[$sort]} LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, ($page - 1) * $perPage])
    );
    $categories = db_all(
        "SELECT category, COUNT(*) AS n FROM products WHERE active = 1 AND category <> '' GROUP BY category ORDER BY n DESC, category"
    );

    $isFrontPage = $category === null && $platform === '' && $search === '' && $minDiscount === 0 && $page === 1;

    render('listing', [
        'news'        => $isFrontPage ? news_latest(5) : [],
        'promos'      => $isFrontPage ? promos_live('home') : [],
        'topCats'     => $isFrontPage ? top_categories() : [],
        // Thai breeds first on the front page
        'breeds'      => $isFrontPage ? db_all("SELECT * FROM breeds ORDER BY CASE WHEN origin LIKE '%ไทย%' THEN 0 ELSE 1 END, name_th LIMIT 4") : [],
        'topSellers'  => $isFrontPage
            ? db_all('SELECT ' . product_columns() . ' FROM products WHERE active = 1 AND top_rank IS NOT NULL ORDER BY top_rank')
            : [],
        'articles'    => $isFrontPage ? articles_published(3) : ($category !== null && $page === 1 ? articles_published(3, $category) : []),
        'products'    => $products,
        'categories'  => $categories,
        'category'    => $category,
        'platform'    => $platform,
        'search'      => $search,
        'sort'        => $sort,
        'minDiscount' => $minDiscount,
        'page'        => $page,
        'pages'       => max(1, (int) ceil($total / $perPage)),
        'total'       => $total,
        'basePath'    => $category !== null ? 'c/' . rawurlencode($category) : '',
        // Filtered or searched views are thin duplicates of the main pages.
        'noindex'     => $platform !== '' || $search !== '' || $minDiscount > 0 || $sort !== 'discount',
    ]);
}

function page_product(string $slug): void
{
    $product = product_by_slug($slug);
    if (!$product) {
        render('404', [], 404);
        return;
    }

    $compare = [];
    if ($product['group_key'] !== '') {
        $compare = db_all(
            'SELECT ' . product_columns() . ' FROM products WHERE group_key = ? AND id <> ? AND active = 1 ORDER BY price',
            [$product['group_key'], $product['id']]
        );
    }
    $related = db_all(
        'SELECT ' . product_columns() . ' FROM products WHERE category = ? AND id <> ? AND active = 1 ORDER BY discount DESC LIMIT 4',
        [$product['category'], $product['id']]
    );

    render('product', [
        'product' => $product,
        'history' => product_price_history($product, 90),
        'summary' => price_summary($product),
        'compare'  => $compare,
        'related'  => $related,
        'articles' => $product['category'] !== '' ? articles_published(3, $product['category']) : [],
    ]);
}

/** "Best sellers" ranking for one category: answers "<category> ยี่ห้อไหนดี" searches with live prices. */
function page_top(string $category): void
{
    $products = top_products($category);
    if (count($products) < TOP_MIN_PRODUCTS) {
        render('404', [], 404);
        return;
    }
    render('top', [
        'category' => $category,
        'products' => $products,
        'articles' => articles_published(3, $category),
        'others'   => top_categories(),
    ]);
}

function page_deals(): void
{
    $drops = deals_price_drops();
    $byCategory = deals_by_category();
    render('deals', [
        'week'       => deals_week_range(),
        'drops'      => $drops,
        'byCategory' => $byCategory,
        'summaries'  => price_summaries(array_merge($drops, ...array_values($byCategory))),
        'campaign'   => campaign_next(),
        'articles'   => articles_published(3),
    ]);
}

function page_campaign(string $slug): void
{
    $campaign = campaign_get($slug);
    if (!$campaign) {
        render('404', [], 404);
        return;
    }
    $byCategory = deals_by_category(4);
    $articles = [];
    // Shopping-event articles first, then the latest ones; each once.
    foreach (array_merge(array_filter(articles_published(30), 'article_is_promo'), articles_published(6)) as $a) {
        $articles[$a['id']] = $a;
    }
    render('campaign', [
        'campaign'   => $campaign,
        'promos'     => promos_live('home'),
        'byCategory' => $byCategory,
        'summaries'  => price_summaries(array_merge(...array_values($byCategory))),
        'articles'   => array_slice(array_values($articles), 0, 3),
    ]);
}

function page_breeds(): void
{
    render('breeds', ['breeds' => breeds_all()]);
}

function page_breed(string $slug): void
{
    $breed = breed_by_slug($slug);
    if (!$breed) {
        render('404', [], 404);
        return;
    }
    render('breed', [
        'breed'    => $breed,
        'sections' => json_decode($breed['sections'], true) ?: [],
        'traits'   => $breed['traits'] ? json_decode($breed['traits'], true) : null,
        'deals'    => db_all('SELECT ' . product_columns() . ' FROM products WHERE active = 1 ORDER BY top_rank IS NULL, top_rank, discount DESC LIMIT 8'),
        'others'   => db_all('SELECT * FROM breeds WHERE id <> ? ORDER BY RANDOM() LIMIT 4', [$breed['id']]),
    ]);
}

function page_news(array $q): void
{
    $perPage = 30;
    $page = max(1, (int) ($q['page'] ?? 1));
    $total = (int) db_value('SELECT COUNT(*) FROM news_items');
    render('news', [
        'items' => news_latest($perPage, ($page - 1) * $perPage),
        'page'  => $page,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ]);
}

function page_blog(array $q): void
{
    // Written articles first, then the automatic breed pages (Thai breeds first) — so the page is never empty.
    $entries = [];
    foreach (db_all('SELECT * FROM articles WHERE ' . ARTICLE_LIVE_SQL . ' ORDER BY published_at DESC', [now()]) as $a) {
        $entries[] = ['type' => 'article', 'row' => $a];
    }
    foreach (db_all("SELECT * FROM breeds ORDER BY CASE WHEN origin LIKE '%ไทย%' THEN 0 ELSE 1 END, name_th") as $b) {
        $entries[] = ['type' => 'breed', 'row' => $b];
    }
    $perPage = 12;
    $pages = max(1, (int) ceil(count($entries) / $perPage));
    $page = min($pages, max(1, (int) ($q['page'] ?? 1)));
    render('blog', [
        'entries' => array_slice($entries, ($page - 1) * $perPage, $perPage),
        'page'    => $page,
        'pages'   => $pages,
    ]);
}

function page_article(string $slug, array $q): void
{
    $article = article_by_slug($slug);
    $isPreview = $article && isset($q['preview']) && hash_equals(article_preview_token($article), (string) $q['preview']);
    if (!$article || (!article_is_live($article) && !$isPreview)) {
        render('404', [], 404);
        return;
    }

    render('article', [
        'article'   => $article,
        'rendered'  => article_render($article['body']),
        'isPreview' => !article_is_live($article),
        'deals'     => $article['category'] !== ''
            ? db_all('SELECT ' . product_columns() . ' FROM products WHERE category = ? AND active = 1 ORDER BY discount DESC LIMIT 4', [$article['category']])
            : [],
        'more'      => articles_related($article),
        'promos'    => article_is_promo($article) ? promos_live('articles') : [],
        'mentioned' => article_products($article['body']),
    ]);
}

function page_go(int $id): void
{
    $product = product_get($id);
    if (!$product || !(int) $product['active'] || !is_allowed_affiliate_url($product['affiliate_url'])) {
        render('404', [], 404);
        return;
    }
    log_click($product);
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Location: ' . $product['affiliate_url'], true, 302);
}

/** Price-alert form on a product page (POST only, same site, honeypot left empty). */
function page_alert_signup(int $id): void
{
    $product = product_get($id);
    $origin = (string) parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    $ownHost = (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!$product || !alerts_enabled() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $origin !== $ownHost) {
        render('404', [], 404);
        return;
    }
    $message = (string) ($_POST['website'] ?? '') !== ''
        ? 'ส่งลิงก์ยืนยันแล้ว'
        : alert_signup($product, (string) ($_POST['email'] ?? ''));
    render('alert', ['message' => $message, 'product' => $product]);
}

/** Promo banner click: count it, then on to the campaign's affiliate link (also after it ends, for cached pages). */
function page_go_promo(int $id): void
{
    $promo = promo_get($id);
    if (!$promo || !is_allowed_affiliate_url($promo['url'])) {
        render('404', [], 404);
        return;
    }
    log_promo_click($promo);
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Location: ' . $promo['url'], true, 302);
}

/** Serve a downloaded photo from the data volume (data/ itself is not web-accessible). */
function page_media(string $dir, string $file): void
{
    $path = $dir . '/' . $file;
    if (strpos($file, '..') !== false || !is_file($path)) {
        render('404', [], 404);
        return;
    }
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    header('Content-Type: ' . $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))]);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=2592000');
    readfile($path);
}

function page_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [['loc' => abs_url(''), 'lastmod' => null], ['loc' => abs_url('blog'), 'lastmod' => null],
             ['loc' => abs_url('breeds'), 'lastmod' => null], ['loc' => abs_url('news'), 'lastmod' => null],
             ['loc' => abs_url('game'), 'lastmod' => null], ['loc' => abs_url('deals'), 'lastmod' => date('Y-m-d')]];
    foreach (array_keys(CAMPAIGNS) as $slug) {
        $urls[] = ['loc' => abs_url('sale/' . $slug), 'lastmod' => null];
    }
    foreach (db_all('SELECT slug, fetched_at FROM breeds ORDER BY name_th') as $row) {
        $urls[] = ['loc' => abs_url('breed/' . rawurlencode($row['slug'])), 'lastmod' => substr($row['fetched_at'], 0, 10)];
    }
    foreach (db_all('SELECT slug, updated_at FROM articles WHERE ' . ARTICLE_LIVE_SQL . ' ORDER BY published_at DESC', [now()]) as $row) {
        $urls[] = ['loc' => abs_url('a/' . rawurlencode($row['slug'])), 'lastmod' => substr($row['updated_at'], 0, 10)];
    }
    foreach (db_all("SELECT DISTINCT category FROM products WHERE active = 1 AND category <> ''") as $row) {
        $urls[] = ['loc' => abs_url('c/' . rawurlencode($row['category'])), 'lastmod' => null];
    }
    foreach (top_categories() as $row) {
        $urls[] = ['loc' => abs_url('top/' . rawurlencode($row['category'])), 'lastmod' => substr($row['updated_at'], 0, 10)];
    }
    if (config('sitemap_products', true)) {
        foreach (db_all('SELECT slug, updated_at FROM products WHERE active = 1 ORDER BY id') as $row) {
            $urls[] = ['loc' => abs_url('p/' . rawurlencode($row['slug'])), 'lastmod' => substr($row['updated_at'], 0, 10)];
        }
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        echo '  <url><loc>' . e($u['loc']) . '</loc>' . ($u['lastmod'] ? '<lastmod>' . e($u['lastmod']) . '</lastmod>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
}

function page_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    echo 'Disallow: ' . url('go/') . "\n";
    echo 'Disallow: ' . url('alert/') . "\n";
    echo 'Disallow: ' . url('admin/') . "\n";
    echo "\nSitemap: " . abs_url('sitemap.xml') . "\n";
}
