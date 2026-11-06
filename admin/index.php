<?php
declare(strict_types=1);

require dirname(__DIR__) . '/inc/bootstrap.php';
require __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
admin_session_start();

$action = (string) ($_GET['a'] ?? '');

if ($action === 'login' || !is_logged_in()) {
    action_login();
    exit;
}

switch ($action) {
    case '':
        action_dashboard();
        break;
    case 'products':
        action_products();
        break;
    case 'edit':
        action_edit();
        break;
    case 'toggle':
        require_post();
        db_run('UPDATE products SET active = 1 - active, updated_at = ? WHERE id = ?', [now(), (int) $_POST['id']]);
        flash('อัปเดตสถานะแล้ว');
        admin_redirect('products');
        break;
    case 'delete':
        require_post();
        db_run('DELETE FROM products WHERE id = ?', [(int) $_POST['id']]);
        flash('ลบสินค้าแล้ว');
        admin_redirect('products');
        break;
    case 'import':
        action_import();
        break;
    case 'import_articles':
        require_post();
        $result = articles_import_files();
        flash('นำเข้าบทความจากไฟล์: ใหม่ ' . $result['created'] . ' · มีอยู่แล้ว ' . $result['skipped']
            . ($result['errors'] ? ' · ผิดพลาด ' . count($result['errors']) . ' (' . implode(', ', array_keys($result['errors'])) . ')' : ''));
        admin_redirect('articles');
        break;
    case 'approve_scheduled':
        require_post();
        flash('อนุมัติแล้ว ' . articles_approve_scheduled() . ' บทความ — จะขึ้นเว็บเองตามเวลาที่ตั้งไว้');
        admin_redirect('articles');
        break;
    case 'template':
        action_template();
        break;
    case 'fetch_now':
        require_post();
        $result = fetch_top_products();
        $result['finished_at'] = now();
        file_put_contents(fetch_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flash('ดึงสินค้าขายดีแล้ว: ' . count($result['top']) . ' อันดับ' . ($result['errors'] ? ' (มีข้อผิดพลาด ' . count($result['errors']) . ')' : ''));
        admin_redirect();
        break;
    case 'fetch_breeds':
    case 'fetch_news':
        require_post();
        set_time_limit(300); // Wikipedia is fetched about one page a second
        $isBreeds = $action === 'fetch_breeds';
        $result = $isBreeds ? fetch_breeds() : fetch_news();
        $result['finished_at'] = now();
        file_put_contents(dirname((string) config('db_path')) . ($isBreeds ? '/last-breeds.json' : '/last-news.json'),
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flash($isBreeds
            ? 'อัปเดตสายพันธุ์แล้ว ' . $result['saved'] . ' สายพันธุ์' . ($result['errors'] ? ' (ผิดพลาด ' . count($result['errors']) . ')' : '')
            : 'ดึงข่าวแล้ว ได้ข่าวใหม่ ' . $result['added'] . ' ข่าว' . ($result['errors'] ? ' (ผิดพลาด ' . count($result['errors']) . ')' : ''));
        admin_redirect();
        break;
    case 'articles':
        action_articles();
        break;
    case 'article':
        action_article_edit();
        break;
    case 'article_delete':
        require_post();
        db_run('DELETE FROM articles WHERE id = ?', [(int) $_POST['id']]);
        flash('ลบบทความแล้ว');
        admin_redirect('articles');
        break;
    case 'facebook_now':
        require_post();
        $result = facebook_post_due();
        $result['finished_at'] = now();
        file_put_contents(facebook_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flash($result['stopped'] ?: 'โพสต์ลงเพจแล้ว ' . count($result['posted']) . ' บทความ' . ($result['errors'] ? ' (ผิดพลาด ' . count($result['errors']) . ')' : ''));
        admin_redirect();
        break;
    case 'clicks':
        action_clicks();
        break;
    case 'promos':
        action_promos();
        break;
    case 'promo_delete':
        require_post();
        db_run('DELETE FROM promos WHERE id = ?', [(int) $_POST['id']]);
        flash('ลบแบนเนอร์แล้ว');
        admin_redirect('promos');
        break;
    case 'logout':
        require_post();
        $_SESSION = [];
        session_destroy();
        admin_redirect();
        break;
    default:
        http_response_code(404);
        admin_header('ไม่พบหน้า');
        echo '<p>ไม่พบหน้านี้</p>';
        admin_footer();
}

function action_login(): void
{
    $hash = (string) config('admin_password_hash', '');
    $error = null;

    if ($hash !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            admin_redirect();
        }
        sleep(1); // slow down guessing
        $error = 'รหัสผ่านไม่ถูกต้อง';
    } elseif (is_logged_in()) {
        admin_redirect();
    }

    admin_header('เข้าสู่ระบบ');
    ?>
    <section class="panel narrow">
      <h1>เข้าสู่ระบบหลังบ้าน</h1>
      <?php if ($hash === ''): ?>
        <p class="error">ยังไม่ได้ตั้งรหัสผ่าน — รันคำสั่ง <code>php bin/setup.php</code> ในโฟลเดอร์โปรเจกต์ก่อน</p>
      <?php else: ?>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <form method="post" action="<?= e(admin_url('login')) ?>" class="form">
          <label>รหัสผ่าน <input type="password" name="password" autocomplete="current-password" required autofocus></label>
          <button class="btn btn-deal">เข้าสู่ระบบ</button>
        </form>
      <?php endif; ?>
    </section>
    <?php
    admin_footer();
}

function action_dashboard(): void
{
    $today = date('Y-m-d 00:00:00');
    $d7 = date('Y-m-d 00:00:00', strtotime('-6 days'));
    $d30 = date('Y-m-d 00:00:00', strtotime('-29 days'));

    $stats = [
        'วันนี้'     => db_one('SELECT COUNT(*) AS clicks, COUNT(DISTINCT visitor_hash) AS visitors FROM clicks WHERE clicked_at >= ?', [$today]),
        '7 วัน'     => db_one('SELECT COUNT(*) AS clicks, COUNT(DISTINCT visitor_hash) AS visitors FROM clicks WHERE clicked_at >= ?', [$d7]),
        '30 วัน'    => db_one('SELECT COUNT(*) AS clicks, COUNT(DISTINCT visitor_hash) AS visitors FROM clicks WHERE clicked_at >= ?', [$d30]),
    ];
    $productCount = db_one('SELECT COUNT(*) AS total, SUM(active) AS active FROM products');

    $daily = [];
    foreach (db_all('SELECT substr(clicked_at, 1, 10) AS day, COUNT(*) AS n FROM clicks WHERE clicked_at >= ? GROUP BY day', [date('Y-m-d 00:00:00', strtotime('-13 days'))]) as $row) {
        $daily[$row['day']] = (int) $row['n'];
    }
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $days[$day] = $daily[$day] ?? 0;
    }
    $maxDay = max(1, max($days));

    $top = db_all(
        'SELECT p.id, p.name, p.platform, p.slug, COUNT(c.id) AS clicks
           FROM clicks c JOIN products p ON p.id = c.product_id
          WHERE c.clicked_at >= ?
          GROUP BY p.id ORDER BY clicks DESC LIMIT 10',
        [$d30]
    );
    $referers = db_all(
        "SELECT CASE WHEN referer = '' THEN '(ไม่ทราบ)' ELSE referer END AS source, COUNT(*) AS n
           FROM clicks WHERE clicked_at >= ? GROUP BY source ORDER BY n DESC LIMIT 8",
        [$d30]
    );

    $lastFetch = is_file(fetch_status_file()) ? json_decode((string) file_get_contents(fetch_status_file()), true) : null;
    $dataDir = dirname((string) config('db_path'));
    $lastBreeds = is_file($dataDir . '/last-breeds.json') ? json_decode((string) file_get_contents($dataDir . '/last-breeds.json'), true) : null;
    $lastNews = is_file($dataDir . '/last-news.json') ? json_decode((string) file_get_contents($dataDir . '/last-news.json'), true) : null;
    $lastFacebook = is_file(facebook_status_file()) ? json_decode((string) file_get_contents(facebook_status_file()), true) : null;
    $fbRecent = db_all('SELECT title, slug, fb_posted_at, fb_error FROM articles WHERE fb_posted_at IS NOT NULL OR fb_error IS NOT NULL
                        ORDER BY COALESCE(fb_posted_at, published_at) DESC LIMIT 5');
    $breedCount = (int) db_value('SELECT COUNT(*) FROM breeds');
    $newsCount = (int) db_value('SELECT COUNT(*) FROM news_items');

    admin_header('ภาพรวม');
    ?>
    <h1>ภาพรวม</h1>
    <section class="panel fetch-panel">
      <div>
        <h2>สินค้าขายดีประจำวัน (Shopee)</h2>
        <?php if (!shopee_configured()): ?>
          <p class="muted">ยังไม่ได้ตั้งค่า Shopee Affiliate API — เมื่อได้ App ID และ Secret แล้ว ใส่ใน <code>config.php</code> (ส่วน <code>shopee_api</code>)</p>
        <?php elseif ($lastFetch): ?>
          <p>ดึงล่าสุด <strong><?= e($lastFetch['finished_at']) ?></strong> ·
             ได้ <?= count($lastFetch['top']) ?> อันดับ · เพิ่มใหม่ <?= (int) $lastFetch['created'] ?> · อัปเดต <?= (int) $lastFetch['updated'] ?>
             <?= $lastFetch['hidden'] ? ' · ซ่อนสินค้าเก่า ' . (int) $lastFetch['hidden'] : '' ?></p>
          <?php if ($lastFetch['errors']): ?>
            <ul class="error"><?php foreach ($lastFetch['errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        <?php else: ?>
          <p class="muted">ยังไม่เคยดึงข้อมูล</p>
        <?php endif; ?>
      </div>
      <?php if (shopee_configured()): ?>
        <form method="post" action="<?= e(admin_url('fetch_now')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">ดึงตอนนี้</button></form>
      <?php endif; ?>
    </section>
    <section class="panel fetch-panel">
      <div>
        <h2>เนื้อหาอัตโนมัติ</h2>
        <p>สายพันธุ์แมว <strong><?= $breedCount ?></strong> หน้า<?= $lastBreeds ? ' · อัปเดต ' . e($lastBreeds['finished_at']) : '' ?>
           · ข่าวแมว <strong><?= $newsCount ?></strong> ข่าว<?= $lastNews ? ' · ดึงล่าสุด ' . e($lastNews['finished_at']) : '' ?></p>
        <?php $errs = array_merge($lastBreeds['errors'] ?? [], $lastNews['errors'] ?? []); if ($errs): ?>
          <ul class="error"><?php foreach ($errs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <p class="muted small">สายพันธุ์: Wikidata + Wikipedia ภาษาไทย (เดือนละครั้งพอ ใช้เวลาประมาณ 30 วินาที) · ข่าว: RSS จากแหล่งใน <code>config.php</code> (วันละ 2–3 ครั้ง)</p>
      </div>
      <div class="studio-row">
        <form method="post" action="<?= e(admin_url('fetch_news')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">ดึงข่าวแมว</button></form>
        <form method="post" action="<?= e(admin_url('fetch_breeds')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">ดึงข้อมูลสายพันธุ์</button></form>
      </div>
    </section>

    <section class="panel fetch-panel">
      <div>
        <h2>โพสต์บทความลงเพจ Facebook</h2>
        <?php if (!facebook_configured()): ?>
          <p class="muted">ยังไม่ได้ตั้งค่า — ใส่ Page Access Token ใน Dokploy › Environment เป็น <code>FB_PAGE_TOKEN</code> แล้ว deploy ใหม่
             บทความที่ขึ้นเว็บหลังจากนั้นจะโพสต์ลงเพจเอง (เช็กทุก 10 นาที)</p>
        <?php else: ?>
          <p>เปิดใช้งานแล้ว · เช็กทุก 10 นาที<?= $lastFacebook ? ' · ล่าสุด ' . e($lastFacebook['finished_at']) : '' ?></p>
          <?php if (!empty($lastFacebook['stopped'])): ?><p class="error"><?= e($lastFacebook['stopped']) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($fbRecent): ?>
          <ul class="small">
            <?php foreach ($fbRecent as $r): ?>
              <li><?= e($r['title']) ?> — <?= $r['fb_posted_at'] ? 'โพสต์แล้ว ' . e($r['fb_posted_at']) : '<span class="error">' . e($r['fb_error']) . '</span>' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <?php if (facebook_configured()): ?>
        <form method="post" action="<?= e(admin_url('facebook_now')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">โพสต์ตอนนี้</button></form>
      <?php endif; ?>
    </section>

    <div class="stat-row">
      <?php foreach ($stats as $label => $s): ?>
        <div class="stat">
          <span class="stat-label">คลิกไปร้านค้า · <?= e($label) ?></span>
          <strong class="stat-value"><?= number_format((int) $s['clicks']) ?></strong>
          <span class="stat-sub"><?= number_format((int) $s['visitors']) ?> ผู้เยี่ยมชมไม่ซ้ำ</span>
        </div>
      <?php endforeach; ?>
      <div class="stat">
        <span class="stat-label">สินค้าที่แสดงอยู่</span>
        <strong class="stat-value"><?= number_format((int) $productCount['active']) ?></strong>
        <span class="stat-sub">จากทั้งหมด <?= number_format((int) $productCount['total']) ?></span>
      </div>
    </div>

    <section class="panel">
      <h2>คลิก 14 วันล่าสุด</h2>
      <div class="bars">
        <?php foreach ($days as $day => $n): ?>
          <div class="bar" title="<?= e(thai_date($day)) ?>: <?= $n ?> คลิก">
            <span class="bar-fill" style="height: <?= round($n / $maxDay * 100) ?>%"></span>
            <span class="bar-n"><?= $n ?: '' ?></span>
            <span class="bar-day"><?= (int) substr($day, 8) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="two-col">
      <section class="panel">
        <h2>สินค้าที่ถูกคลิกมากสุด (30 วัน)</h2>
        <?php if ($top): ?>
          <table class="table">
            <thead><tr><th>สินค้า</th><th class="num">คลิก</th></tr></thead>
            <tbody>
              <?php foreach ($top as $t): ?>
                <tr>
                  <td><span class="platform platform-<?= e($t['platform']) ?>"><?= e(PLATFORMS[$t['platform']]) ?></span>
                      <a href="<?= e(admin_url('edit', ['id' => $t['id']])) ?>"><?= e($t['name']) ?></a></td>
                  <td class="num"><?= number_format((int) $t['clicks']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted">ยังไม่มีคลิก</p>
        <?php endif; ?>
      </section>
      <section class="panel">
        <h2>คลิกมาจากหน้าไหน (30 วัน)</h2>
        <?php if ($referers): ?>
          <table class="table">
            <tbody>
              <?php foreach ($referers as $r): ?>
                <tr><td><?= e($r['source']) ?></td><td class="num"><?= number_format((int) $r['n']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted">ยังไม่มีข้อมูล</p>
        <?php endif; ?>
      </section>
    </div>
    <p class="muted small">ยอดขายและค่าคอมมิชชันจริงดูได้ในหน้า Affiliate ของ Lazada / Shopee — หน้านี้นับเฉพาะคลิกจากเว็บของเรา (ไม่นับบอท)</p>
    <?php
    admin_footer();
}

function action_products(): void
{
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $params = [date('Y-m-d 00:00:00', strtotime('-29 days'))];
    $where = '';
    if ($q !== '') {
        $where = "WHERE p.name LIKE ? ESCAPE '\\' OR p.category LIKE ? ESCAPE '\\'";
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $products = db_all(
        'SELECT p.*, ' . discount_sql() . " AS discount,
                (SELECT COUNT(*) FROM clicks c WHERE c.product_id = p.id AND c.clicked_at >= ?) AS clicks30
           FROM products p $where ORDER BY p.id DESC LIMIT 500",
        $params
    );

    admin_header('สินค้า');
    ?>
    <div class="toolbar">
      <h1>สินค้า</h1>
      <form method="get" class="inline-search">
        <input type="hidden" name="a" value="products">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อหรือหมวด">
      </form>
      <a class="btn btn-deal" href="<?= e(admin_url('edit')) ?>">+ เพิ่มสินค้า</a>
    </div>
    <div class="panel table-wrap">
      <table class="table">
        <thead><tr><th>สินค้า</th><th>หมวด</th><th class="num">ราคา</th><th class="num">ลด</th><th class="num">คลิก 30 วัน</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr class="<?= (int) $p['active'] ? '' : 'inactive' ?>">
            <td><span class="platform platform-<?= e($p['platform']) ?>"><?= e(PLATFORMS[$p['platform']]) ?></span>
                <a href="<?= e(admin_url('edit', ['id' => $p['id']])) ?>"><?= e($p['name']) ?></a> <small class="muted">#<?= (int) $p['id'] ?></small></td>
            <td><?= e($p['category']) ?></td>
            <td class="num"><?= e(baht($p['price'])) ?></td>
            <td class="num"><?= (int) $p['discount'] ? (int) $p['discount'] . '%' : '–' ?></td>
            <td class="num"><?= number_format((int) $p['clicks30']) ?></td>
            <td>
              <form method="post" action="<?= e(admin_url('toggle')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="linklike"><?= (int) $p['active'] ? 'แสดงอยู่' : 'ซ่อนอยู่' ?></button>
              </form>
            </td>
            <td class="actions">
              <a href="<?= e(product_link($p)) ?>" target="_blank">ดู ↗</a>
              <form method="post" action="<?= e(admin_url('delete')) ?>" onsubmit="return confirm('ลบสินค้านี้และประวัติราคาทั้งหมด?')"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="linklike danger">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
          <tr><td colspan="7" class="muted">ยังไม่มีสินค้า — <a href="<?= e(admin_url('edit')) ?>">เพิ่มสินค้า</a> หรือ <a href="<?= e(admin_url('import')) ?>">นำเข้า CSV</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    admin_footer();
}

function action_edit(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $product = $id ? product_get($id) : null;
    if ($id && !$product) {
        flash('ไม่พบสินค้า');
        admin_redirect('products');
    }

    $values = $product ?: ['platform' => 'lazada', 'active' => 1];
    $errors = [];

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        $input = array_intersect_key($_POST, array_flip(PRODUCT_FIELDS));
        $input['active'] = isset($_POST['active']) ? '1' : '0';
        list($data, $errors) = product_validate($input);
        if (!$errors) {
            $savedId = product_save($data, $id);
            flash($id ? 'บันทึกแล้ว' : 'เพิ่มสินค้าแล้ว');
            admin_redirect('edit', ['id' => $savedId]);
        }
        $values = array_merge($values, $input);
    }

    $categories = db_all("SELECT DISTINCT category FROM products WHERE category <> '' ORDER BY category");
    $v = function ($key) use ($values) {
        return e($values[$key] ?? '');
    };

    admin_header($id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า');
    ?>
    <h1><?= $id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า' ?></h1>
    <?php if ($errors): ?>
      <ul class="error"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" class="panel form form-grid">
      <?= csrf_field() ?>
      <label>แพลตฟอร์ม *
        <select name="platform">
          <?php foreach (PLATFORMS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= ($values['platform'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>หมวดหมู่
        <input name="category" value="<?= $v('category') ?>" list="categories" placeholder="เช่น อาหารแมวเม็ด">
        <datalist id="categories"><?php foreach ($categories as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?></datalist>
      </label>
      <label class="span-2">ชื่อสินค้า *
        <input name="name" value="<?= $v('name') ?>" required maxlength="300">
      </label>
      <label class="span-2">ลิงก์ Affiliate * <small>ลิงก์ที่สร้างจากหน้า Affiliate ของ Lazada / Shopee</small>
        <input name="affiliate_url" value="<?= $v('affiliate_url') ?>" required type="url" placeholder="https://s.lazada.co.th/... หรือ https://s.shopee.co.th/...">
      </label>
      <label class="span-2">ลิงก์หน้าสินค้า (ปกติ) <small>ใช้จับคู่ตอนนำเข้า CSV ซ้ำ</small>
        <input name="product_url" value="<?= $v('product_url') ?>" type="url">
      </label>
      <label class="span-2">ลิงก์รูปภาพ
        <input name="image_url" value="<?= $v('image_url') ?>" type="url">
      </label>
      <label>ราคาตอนนี้ (บาท) *
        <input name="price" value="<?= $v('price') ?>" required inputmode="decimal">
      </label>
      <label>ราคาเต็ม (บาท)
        <input name="original_price" value="<?= $v('original_price') ?>" inputmode="decimal">
      </label>
      <label>คะแนนรีวิว (0–5)
        <input name="rating" value="<?= $v('rating') ?>" inputmode="decimal">
      </label>
      <label>ขายแล้ว (ชิ้น)
        <input name="sold" value="<?= $v('sold') ?>" inputmode="numeric">
      </label>
      <label class="span-2">รหัสจับคู่สินค้า <small>ใส่ค่าเดียวกันให้สินค้าตัวเดียวกันใน Lazada และ Shopee เพื่อแสดงตารางเทียบราคา เช่น <code>tofu-litter-6l</code></small>
        <input name="group_key" value="<?= $v('group_key') ?>">
      </label>
      <label class="span-2">รายละเอียด / รีวิวสั้น <small>เขียนเองจะช่วย SEO มากกว่าก็อปจากหน้าร้าน</small>
        <textarea name="description" rows="6"><?= $v('description') ?></textarea>
      </label>
      <label class="check span-2"><input type="checkbox" name="active" value="1"<?= !empty($values['active']) ? ' checked' : '' ?>> แสดงบนเว็บ</label>
      <div class="span-2 form-actions">
        <button class="btn btn-deal">บันทึก</button>
        <?php if ($product): ?><a href="<?= e(product_link($product)) ?>" target="_blank">ดูหน้าสินค้า ↗</a><?php endif; ?>
      </div>
    </form>

    <?php if ($product): ?>
      <section class="panel">
        <h2>ประวัติราคา</h2>
        <table class="table">
          <tbody>
          <?php foreach (array_reverse(db_all('SELECT price, recorded_at FROM price_history WHERE product_id = ? ORDER BY recorded_at DESC LIMIT 30', [$id])) as $h): ?>
            <tr><td><?= e($h['recorded_at']) ?></td><td class="num"><?= e(baht($h['price'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>
    <?php
    admin_footer();
}

function action_import(): void
{
    $result = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => [0 => ['อัปโหลดไฟล์ไม่สำเร็จ']]];
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $result = ['created' => 0, 'updated' => 0, 'errors' => [0 => ['ไฟล์ใหญ่เกิน 5 MB']]];
        } else {
            $fh = fopen($file['tmp_name'], 'r');
            $result = import_csv($fh);
            fclose($fh);
        }
    }

    admin_header('นำเข้า CSV');
    ?>
    <h1>นำเข้าสินค้าจาก CSV</h1>
    <?php if ($result !== null): ?>
      <div class="panel">
        <p><strong>เพิ่มใหม่ <?= (int) $result['created'] ?> · อัปเดต <?= (int) $result['updated'] ?> · ผิดพลาด <?= count($result['errors']) ?></strong></p>
        <?php if ($result['errors']): ?>
          <ul class="error">
            <?php foreach ($result['errors'] as $line => $errs): ?>
              <li><?= $line ? 'แถว ' . (int) $line . ': ' : '' ?><?= e(implode(' / ', $errs)) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="panel form">
      <?= csrf_field() ?>
      <label>ไฟล์ CSV (UTF-8) <input type="file" name="csv" accept=".csv,text/csv" required></label>
      <button class="btn btn-deal">นำเข้า</button>
    </form>
    <section class="panel">
      <h2>รูปแบบไฟล์</h2>
      <p>แถวแรกเป็นชื่อคอลัมน์ ต้องมี <code>platform</code>, <code>name</code>, <code>affiliate_url</code>, <code>price</code>
         ส่วนคอลัมน์อื่นใส่หรือไม่ใส่ก็ได้: <code>category</code>, <code>original_price</code>, <code>image_url</code>, <code>product_url</code>,
         <code>rating</code>, <code>sold</code>, <code>group_key</code>, <code>description</code>, <code>active</code></p>
      <p>ถ้า <code>product_url</code> (หรือ <code>affiliate_url</code>) ตรงกับสินค้าที่มีอยู่ ระบบจะ<strong>อัปเดตราคา</strong>และบันทึกประวัติราคาให้อัตโนมัติ
         — นำเข้าไฟล์เดิมซ้ำทุกวันเพื่อให้กราฟราคาเดินได้</p>
      <a class="btn btn-ghost" href="<?= e(admin_url('template')) ?>">ดาวน์โหลดไฟล์ตัวอย่าง</a>
    </section>
    <?php
    admin_footer();
}

function action_template(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products-template.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens Thai text correctly
    fputcsv($out, ['platform', 'name', 'category', 'price', 'original_price', 'affiliate_url', 'product_url', 'image_url', 'rating', 'sold', 'group_key', 'description']);
    fputcsv($out, ['lazada', 'ตัวอย่าง ทรายแมวเต้าหู้ 6 ลิตร', 'ทรายแมว', '129', '199', 'https://s.lazada.co.th/xxxx', 'https://www.lazada.co.th/products/xxxx.html', '', '4.8', '1200', 'tofu-litter-6l', '']);
    fclose($out);
}

function action_articles(): void
{
    $articles = db_all('SELECT id, title, slug, category, status, published_at, updated_at FROM articles
                        ORDER BY CASE WHEN published_at IS NULL THEN 1 ELSE 0 END, published_at DESC, updated_at DESC');
    $waiting = (int) db_value("SELECT COUNT(*) FROM articles WHERE status = 'draft' AND published_at IS NOT NULL");
    $hasFiles = (bool) glob(ARTICLE_FILES_DIR . '/*.md');

    admin_header('บทความ');
    ?>
    <div class="toolbar">
      <h1>บทความ</h1>
      <?php if ($hasFiles): ?>
        <form method="post" action="<?= e(admin_url('import_articles')) ?>" style="margin-left:auto"><?= csrf_field() ?>
          <button class="btn btn-ghost">นำเข้าบทความจากไฟล์</button>
        </form>
      <?php endif; ?>
      <?php if ($waiting): ?>
        <form method="post" action="<?= e(admin_url('approve_scheduled')) ?>"
              onsubmit="return confirm('ตรวจครบแล้วใช่ไหม? ฉบับร่าง <?= $waiting ?> บทความจะขึ้นเว็บเองตามเวลาที่ตั้งไว้')"><?= csrf_field() ?>
          <button class="btn btn-ghost">อนุมัติฉบับร่างที่ตั้งเวลาไว้ (<?= $waiting ?>)</button>
        </form>
      <?php endif; ?>
      <a class="btn btn-deal" href="<?= e(admin_url('article')) ?>"<?= $hasFiles || $waiting ? '' : ' style="margin-left:auto"' ?>>+ เขียนบทความ</a>
    </div>
    <div class="panel table-wrap">
      <table class="table">
        <thead><tr><th>ชื่อบทความ</th><th>หมวด</th><th>สถานะ</th><th>เวลาเผยแพร่</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><a href="<?= e(admin_url('article', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td>
            <td><?= e($a['category']) ?></td>
            <td><?php if (article_is_live($a)): ?><span class="pill pill-on">เผยแพร่แล้ว</span>
                <?php elseif ($a['status'] === 'published'): ?><span class="pill pill-on">ตั้งเวลาแล้ว</span>
                <?php else: ?><span class="pill">ฉบับร่าง</span><?php endif; ?></td>
            <td><?= $a['published_at'] ? e(date('j/n/Y H:i', strtotime($a['published_at']))) : '–' ?></td>
            <td class="actions">
              <a href="<?= e(article_link($a) . (article_is_live($a) ? '' : '?preview=' . article_preview_token($a))) ?>" target="_blank"><?= article_is_live($a) ? 'ดู ↗' : 'ดูตัวอย่าง ↗' ?></a>
              <form method="post" action="<?= e(admin_url('article_delete')) ?>" onsubmit="return confirm('ลบบทความนี้?')"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="linklike danger">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$articles): ?>
          <tr><td colspan="5" class="muted">ยังไม่มีบทความ — <a href="<?= e(admin_url('article')) ?>">เขียนบทความแรก</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small">บทความคือสิ่งที่ดึงคนจาก Google มากที่สุด — ตั้งเป้าสัปดาห์ละ 2–3 บทความ ดูหัวข้อที่แนะนำได้ใน <code>docs/content-plan.md</code></p>
    <?php
    admin_footer();
}

function action_article_edit(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $article = $id ? article_get($id) : null;
    if ($id && !$article) {
        flash('ไม่พบบทความ');
        admin_redirect('articles');
    }

    $values = $article ?: ['status' => 'draft'];
    $errors = [];

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        $input = array_intersect_key($_POST, array_flip(ARTICLE_FIELDS));
        list($data, $errors) = article_validate($input);
        if (!$errors) {
            $savedId = article_save($data, $id);
            flash($data['status'] === 'published' ? 'บันทึกและเผยแพร่แล้ว' : 'บันทึกฉบับร่างแล้ว');
            admin_redirect('article', ['id' => $savedId]);
        }
        $values = array_merge($values, $input);
    }

    $categories = db_all("SELECT DISTINCT category FROM products WHERE category <> '' ORDER BY category");
    $v = function ($key) use ($values) {
        return e($values[$key] ?? '');
    };

    admin_header($id ? 'แก้ไขบทความ' : 'เขียนบทความ');
    ?>
    <div class="toolbar">
      <h1><?= $id ? 'แก้ไขบทความ' : 'เขียนบทความ' ?></h1>
      <?php if ($article): ?>
        <a href="<?= e(article_link($article) . (article_is_live($article) ? '' : '?preview=' . article_preview_token($article))) ?>" target="_blank" style="margin-left:auto">
          <?= article_is_live($article) ? 'ดูหน้าบทความ ↗' : 'ดูตัวอย่าง ↗' ?></a>
      <?php endif; ?>
    </div>
    <?php if ($errors): ?>
      <ul class="error"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div class="editor-layout">
      <form method="post" class="panel form">
        <?= csrf_field() ?>
        <label>ชื่อบทความ * <small>ใส่คำที่คนค้นจริง เช่น "อาหารแมว ยี่ห้อไหนดี 2026"</small>
          <input name="title" value="<?= $v('title') ?>" required maxlength="200">
        </label>
        <label>คำอธิบายสั้น <small>แสดงใต้ชื่อใน Google — 1–2 ประโยค ไม่เกิน ~150 ตัวอักษร</small>
          <textarea name="excerpt" rows="2" maxlength="300"><?= $v('excerpt') ?></textarea>
        </label>
        <div class="form-grid">
          <label>หมวดหมู่ <small>ตรงกับหมวดสินค้า เพื่อลิงก์ถึงกัน</small>
            <input name="category" value="<?= $v('category') ?>" list="categories">
            <datalist id="categories"><?php foreach ($categories as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?></datalist>
          </label>
          <label>URL (slug) <small>เว้นว่าง = สร้างจากชื่อ</small>
            <input name="slug" value="<?= $v('slug') ?>">
          </label>
        </div>
        <label>ลิงก์รูปปก <small>ควรเป็นรูปที่ถ่ายเองหรือมีสิทธิ์ใช้</small>
          <input name="cover_image" value="<?= $v('cover_image') ?>" placeholder="https://… หรือ /assets/img/articles/….webp">
        </label>
        <label>เนื้อหา
          <textarea name="body" rows="28" class="mono"><?= $v('body') ?></textarea>
        </label>
        <label>เวลาเผยแพร่ <small>เว้นว่าง = ขึ้นทันทีที่เลือก "เผยแพร่" · ถ้าเป็นเวลาในอนาคต บทความจะขึ้นเองตอนนั้น</small>
          <input name="published_at" type="datetime-local" value="<?= !empty($values['published_at']) ? e(date('Y-m-d\\TH:i', strtotime((string) $values['published_at']))) : '' ?>">
        </label>
        <div class="form-actions">
          <select name="status">
            <option value="draft"<?= ($values['status'] ?? '') !== 'published' ? ' selected' : '' ?>>ฉบับร่าง (ยังไม่แสดง)</option>
            <option value="published"<?= ($values['status'] ?? '') === 'published' ? ' selected' : '' ?>>เผยแพร่</option>
          </select>
          <button class="btn btn-deal">บันทึก</button>
        </div>
      </form>

      <aside class="panel help">
        <h2>วิธีเขียน</h2>
        <table class="table">
          <tr><td><code>## หัวข้อ</code></td><td>หัวข้อใหญ่ (ขึ้นสารบัญ)</td></tr>
          <tr><td><code>### หัวข้อย่อย</code></td><td>หัวข้อย่อย</td></tr>
          <tr><td><code>**ตัวหนา**</code></td><td>ตัวหนา</td></tr>
          <tr><td><code>- รายการ</code></td><td>รายการแบบจุด</td></tr>
          <tr><td><code>1. รายการ</code></td><td>รายการแบบตัวเลข</td></tr>
          <tr><td><code>&gt; เคล็ดลับ</code></td><td>กล่องเคล็ดลับสีเขียว</td></tr>
          <tr><td><code>[ข้อความ](/c/ทรายแมว)</code></td><td>ลิงก์</td></tr>
        </table>
        <h2>ฝังสินค้า <small class="muted">(ราคาอัปเดตเอง)</small></h2>
        <table class="table">
          <tr><td><code>[product:12]</code></td><td>การ์ดสินค้า ID 12</td></tr>
          <tr><td><code>[products:ทรายแมว:4]</code></td><td>ดีลเด่น 4 ชิ้นในหมวด</td></tr>
          <tr><td><code>[compare:รหัสจับคู่]</code></td><td>ตารางเทียบราคา Lazada/Shopee</td></tr>
        </table>
        <p class="muted small">ดู ID สินค้าได้ที่หน้า "สินค้า" (ตัวเลขหลังเครื่องหมาย #)</p>
        <h2>เขียนยังไงให้ติด Google</h2>
        <ul class="small">
          <li>ตอบคำถามในชื่อบทความให้ชัดตั้งแต่ย่อหน้าแรก</li>
          <li>เล่าประสบการณ์จริง ใส่รูปที่ถ่ายเอง</li>
          <li>ลิงก์ไปหมวดสินค้าและบทความอื่นของเรา</li>
          <li>เรื่องสุขภาพแมว ให้แนะนำปรึกษาสัตวแพทย์</li>
          <li>ใช้ AI ช่วยร่างได้ แต่ต้องอ่านแก้เองทุกครั้ง</li>
        </ul>
      </aside>
    </div>
    <?php
    admin_footer();
}

/** Promo banners: list with click counts, plus the add / edit form (?id=). */
function action_promos(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $promo = $id ? promo_get($id) : null;
    if ($id && !$promo) {
        flash('ไม่พบแบนเนอร์');
        admin_redirect('promos');
    }
    $values = $promo ?: ['show_home' => 1, 'show_articles' => 1];
    $errors = [];

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        list($data, $errors) = promo_validate($_POST);
        if (!$errors) {
            promo_save($data, $id);
            flash($id ? 'บันทึกแบนเนอร์แล้ว' : 'เพิ่มแบนเนอร์แล้ว');
            admin_redirect('promos');
        }
        $values = array_merge($values, array_intersect_key($_POST, array_flip(PROMO_FIELDS)));
        $values['show_home'] = empty($_POST['show_home']) ? 0 : 1;
        $values['show_articles'] = empty($_POST['show_articles']) ? 0 : 1;
    }

    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    $promos = db_all('SELECT p.*, (SELECT COUNT(*) FROM promo_clicks c WHERE c.promo_id = p.id) AS clicks,
                             (SELECT COUNT(*) FROM promo_clicks c WHERE c.promo_id = p.id AND c.clicked_at >= ?) AS clicks_7d
                      FROM promos p ORDER BY p.ends_at DESC', [$since]);
    $v = function ($key) use ($values) {
        return e($values[$key] ?? '');
    };
    $dt = function ($key) use ($values) {
        return !empty($values[$key]) && ($t = promo_time((string) $values[$key])) !== '' ? e(date('Y-m-d\TH:i', strtotime($t))) : '';
    };

    admin_header('แบนเนอร์โปร');
    ?>
    <div class="toolbar"><h1>แบนเนอร์โปร</h1></div>
    <div class="panel table-wrap">
      <table class="table">
        <thead><tr><th>แคมเปญ</th><th>ช่วงเวลา</th><th>สถานะ</th><th>แสดงที่</th><th>คลิก (7 วัน / ทั้งหมด)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($promos as $p): ?>
          <tr>
            <td><a href="<?= e(admin_url('promos', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a></td>
            <td><?= e(date('j/n/Y H:i', strtotime($p['starts_at']))) ?> – <?= e(date('j/n/Y H:i', strtotime($p['ends_at']))) ?></td>
            <td><?php if (promo_is_live($p)): ?><span class="pill pill-on">กำลังแสดง</span>
                <?php elseif ($p['starts_at'] > now()): ?><span class="pill">รอเริ่ม</span>
                <?php else: ?><span class="pill">หมดเวลา</span><?php endif; ?></td>
            <td><?= implode(' · ', array_filter([$p['show_home'] ? 'หน้าแรก' : '', $p['show_articles'] ? 'บทความโปร' : ''])) ?: '–' ?></td>
            <td><?= number_format((int) $p['clicks_7d']) ?> / <?= number_format((int) $p['clicks']) ?></td>
            <td class="actions">
              <a href="<?= e($p['url']) ?>" target="_blank" rel="noopener noreferrer">ลิงก์ ↗</a>
              <form method="post" action="<?= e(admin_url('promo_delete')) ?>" onsubmit="return confirm('ลบแบนเนอร์นี้? ยอดคลิกจะหายไปด้วย')"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="linklike danger">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$promos): ?>
          <tr><td colspan="6" class="muted">ยังไม่มีแบนเนอร์ — เพิ่มอันแรกด้านล่าง</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h2 id="form"><?= $promo ? 'แก้ไข: ' . e($promo['title']) : 'เพิ่มแบนเนอร์' ?></h2>
    <?php if ($errors): ?>
      <ul class="error"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form method="post" action="<?= e(admin_url('promos', $id ? ['id' => $id] : [])) ?>" class="panel form">
      <?= csrf_field() ?>
      <label>ชื่อแคมเปญ * <small>ข้อความใหญ่บนแบนเนอร์ เช่น "Shopee 11.11 ของใช้แมวลดสูงสุด"</small>
        <input name="title" value="<?= $v('title') ?>" required maxlength="80">
      </label>
      <label>ข้อความรอง <small>ไม่บังคับ เช่น "โค้ดส่งฟรี + ลดเพิ่มเที่ยงคืน"</small>
        <input name="subtitle" value="<?= $v('subtitle') ?>" maxlength="140">
      </label>
      <label>ลิงก์ Affiliate * <small>สร้างจาก Shopee Affiliate › ลิงก์ที่กำหนดเอง (เช่น https://s.shopee.co.th/…) เพื่อให้ได้ค่าคอม</small>
        <input name="url" type="url" value="<?= $v('url') ?>" required placeholder="https://s.shopee.co.th/…">
      </label>
      <div class="form-grid">
        <label>วันเริ่ม * <input name="starts_at" type="datetime-local" value="<?= $dt('starts_at') ?>" required></label>
        <label>วันจบ * <small>เลยเวลานี้แบนเนอร์ซ่อนเอง</small><input name="ends_at" type="datetime-local" value="<?= $dt('ends_at') ?>" required></label>
      </div>
      <label class="check"><input type="checkbox" name="show_home" value="1"<?= !empty($values['show_home']) ? ' checked' : '' ?>> แสดงที่หน้าแรก (ใต้หัวเว็บ)</label>
      <label class="check"><input type="checkbox" name="show_articles" value="1"<?= !empty($values['show_articles']) ? ' checked' : '' ?>> แสดงในบทความโปร (บทความที่ชื่อมี "โปร", "ลดราคา", 11.11, 12.12 …)</label>
      <div class="form-actions">
        <?php if ($promo): ?><a href="<?= e(admin_url('promos')) ?>">ยกเลิก</a><?php endif; ?>
        <button class="btn btn-deal"><?= $promo ? 'บันทึก' : 'เพิ่มแบนเนอร์' ?></button>
      </div>
    </form>
    <p class="muted small">แสดงพร้อมกันได้สูงสุด 3 แคมเปญ (เริ่มล่าสุดขึ้นก่อน) · นับคลิกเฉพาะคน ไม่นับบอท · ลิงก์ผ่าน /go/promo/… เหมือนลิงก์สินค้า</p>
    <?php
    admin_footer();
}

/** Which kind of page a click came from, for the click report. Referers are paths for our own pages, hosts otherwise. */
function click_source_type(string $referer): string
{
    $base = url();
    $path = strpos($referer, $base) === 0 ? '/' . ltrim(substr($referer, strlen($base)), '/') : $referer;
    if ($referer === '') {
        return 'ไม่ทราบ';
    }
    if ($referer[0] !== '/') {
        return 'เว็บอื่น';
    }
    $types = ['/a/' => 'บทความ', '/p/' => 'หน้าสินค้า', '/c/' => 'หน้าหมวด', '/top/' => 'หน้าจัดอันดับ', '/breed' => 'สายพันธุ์',
              '/game' => 'หน้าเกม', '/blog' => 'หน้ารวมบทความ', '/news' => 'ข่าวแมว'];
    foreach ($types as $prefix => $label) {
        if (strpos($path, $prefix) === 0) {
            return $label;
        }
    }
    return $path === '/' ? 'หน้าแรก' : 'อื่นๆ';
}

/** Click report: where buyers click from, which articles earn clicks, which products and banners get them. */
function action_clicks(): void
{
    $days = (int) ($_GET['days'] ?? 30);
    $days = in_array($days, [7, 30, 90], true) ? $days : 30;
    $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

    $all = db_all("SELECT referer, 'product' AS kind FROM clicks WHERE clicked_at >= ?
                   UNION ALL SELECT referer, 'promo' FROM promo_clicks WHERE clicked_at >= ?", [$since, $since]);
    $byType = [];
    foreach ($all as $row) {
        $type = click_source_type($row['referer']);
        $byType[$type] = ($byType[$type] ?? 0) + 1;
    }
    arsort($byType);
    $total = count($all);
    $promoTotal = count(array_filter($all, function ($r) { return $r['kind'] === 'promo'; }));
    $visitors = (int) db_value('SELECT COUNT(DISTINCT visitor_hash) FROM (SELECT visitor_hash FROM clicks WHERE clicked_at >= ?
                                UNION ALL SELECT visitor_hash FROM promo_clicks WHERE clicked_at >= ?)', [$since, $since]);

    // Articles: clicks counted by the article page they came from; live articles with no clicks show at the bottom.
    $articleClicks = [];
    foreach ($all as $row) {
        if (preg_match('#/a/([^/?]+)#u', $row['referer'], $m)) {
            $articleClicks[$m[1]] = ($articleClicks[$m[1]] ?? 0) + 1;
        }
    }
    $articles = db_all('SELECT title, slug, category, published_at FROM articles WHERE ' . ARTICLE_LIVE_SQL, [now()]);
    foreach ($articles as &$a) {
        $a['clicks'] = $articleClicks[$a['slug']] ?? 0;
    }
    unset($a);
    usort($articles, function ($x, $y) { return $y['clicks'] <=> $x['clicks'] ?: strcmp((string) $y['published_at'], (string) $x['published_at']); });

    $products = db_all('SELECT p.id, p.name, p.slug, p.category, COUNT(c.id) AS clicks, COUNT(DISTINCT c.visitor_hash) AS people
                          FROM clicks c JOIN products p ON p.id = c.product_id WHERE c.clicked_at >= ?
                         GROUP BY p.id ORDER BY clicks DESC LIMIT 20', [$since]);
    $categories = db_all("SELECT CASE WHEN p.category = '' THEN '(ไม่มีหมวด)' ELSE p.category END AS category, COUNT(c.id) AS clicks
                            FROM clicks c JOIN products p ON p.id = c.product_id WHERE c.clicked_at >= ?
                           GROUP BY 1 ORDER BY clicks DESC", [$since]);
    $promos = db_all('SELECT p.id, p.title, COUNT(c.id) AS clicks FROM promo_clicks c JOIN promos p ON p.id = c.promo_id
                       WHERE c.clicked_at >= ? GROUP BY p.id ORDER BY clicks DESC', [$since]);
    $bar = function ($n, $max) {
        return '<span class="report-bar"><i style="width:' . ($max > 0 ? round($n / $max * 100) : 0) . '%"></i></span>';
    };

    admin_header('รายงานคลิก');
    ?>
    <div class="toolbar">
      <h1>รายงานคลิก</h1>
      <nav class="report-range" style="margin-left:auto">
        <?php foreach ([7, 30, 90] as $d): ?>
          <a class="btn <?= $d === $days ? 'btn-deal' : 'btn-ghost' ?>" href="<?= e(admin_url('clicks', ['days' => $d])) ?>"><?= $d ?> วัน</a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="stat-row">
      <div class="stat"><span class="stat-label">คลิกไปร้านค้า</span><strong class="stat-value"><?= number_format($total) ?></strong>
        <span class="stat-sub">สินค้า <?= number_format($total - $promoTotal) ?> · แบนเนอร์ <?= number_format($promoTotal) ?></span></div>
      <div class="stat"><span class="stat-label">คนที่กด</span><strong class="stat-value"><?= number_format($visitors) ?></strong>
        <span class="stat-sub">นับคนซ้ำได้ถ้ากดคนละวัน</span></div>
      <div class="stat"><span class="stat-label">บทความที่มีคนกด</span><strong class="stat-value"><?= count(array_filter($articles, function ($a) { return $a['clicks'] > 0; })) ?> / <?= count($articles) ?></strong>
        <span class="stat-sub">บทความที่เผยแพร่แล้ว</span></div>
    </div>

    <div class="report-grid">
      <section class="panel">
        <h2>คลิกมาจากหน้าแบบไหน</h2>
        <table class="table">
          <?php $max = $byType ? max($byType) : 0; ?>
          <?php foreach ($byType as $type => $n): ?>
            <tr><td><?= e($type) ?></td><td class="report-cell"><?= $bar($n, $max) ?></td><td class="num"><?= number_format($n) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byType): ?><tr><td class="muted">ยังไม่มีคลิกในช่วงนี้</td></tr><?php endif; ?>
        </table>
      </section>
      <section class="panel">
        <h2>หมวดสินค้าที่ถูกกด</h2>
        <table class="table">
          <?php $max = $categories ? (int) $categories[0]['clicks'] : 0; ?>
          <?php foreach ($categories as $c): ?>
            <tr><td><?= e($c['category']) ?></td><td class="report-cell"><?= $bar((int) $c['clicks'], $max) ?></td><td class="num"><?= number_format((int) $c['clicks']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$categories): ?><tr><td class="muted">ยังไม่มีคลิกในช่วงนี้</td></tr><?php endif; ?>
        </table>
        <?php if ($promos): ?>
          <h2>แบนเนอร์โปร</h2>
          <table class="table">
            <?php foreach ($promos as $p): ?>
              <tr><td><a href="<?= e(admin_url('promos', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a></td><td class="num"><?= number_format((int) $p['clicks']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </section>
    </div>

    <section class="panel table-wrap">
      <h2>บทความไหนพาคนไปซื้อ</h2>
      <p class="muted small">คลิกที่กดจากหน้าบทความนั้น (การ์ดสินค้า กล่องสรุปสินค้า แบนเนอร์) · บทความที่ยังไม่มีคนกด ลองเพิ่มการ์ดสินค้าให้ตรงเรื่องขึ้น หรือย้ายสินค้าขึ้นไปช่วงต้นบทความ</p>
      <table class="table">
        <thead><tr><th>บทความ</th><th>หมวด</th><th class="num">คลิก</th></tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><a href="<?= e(article_link($a)) ?>" target="_blank"><?= e($a['title']) ?></a></td>
            <td><?= e($a['category']) ?></td>
            <td class="num"><?= $a['clicks'] ? number_format($a['clicks']) : '<span class="muted">0</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$articles): ?><tr><td colspan="3" class="muted">ยังไม่มีบทความที่เผยแพร่</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="panel table-wrap">
      <h2>สินค้าที่ถูกกดมากที่สุด</h2>
      <table class="table">
        <thead><tr><th>สินค้า</th><th>หมวด</th><th class="num">คลิก</th><th class="num">คน</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><a href="<?= e(admin_url('edit', ['id' => $p['id']])) ?>">#<?= (int) $p['id'] ?> <?= e(mb_strimwidth($p['name'], 0, 70, '…')) ?></a></td>
            <td><?= e($p['category']) ?></td>
            <td class="num"><?= number_format((int) $p['clicks']) ?></td>
            <td class="num"><?= number_format((int) $p['people']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="4" class="muted">ยังไม่มีคลิกในช่วงนี้</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
    <p class="muted small">ยอดนี้คือคนที่กดไปร้านค้า ยอดสั่งซื้อและค่าคอมจริงดูได้ที่ Shopee Affiliate › รายงาน · ไม่นับบอทและไม่เก็บ IP</p>
    <?php
    admin_footer();
}
