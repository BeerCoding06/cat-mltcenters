<?php
$filters = ['q' => $search, 'platform' => $platform, 'min' => $minDiscount, 'sort' => $sort === 'discount' ? '' : $sort];
if ($category !== null) {
    $title = $category . ' ลดราคา ดีลล่าสุด ' . platforms_label(' / ');
    $description = 'รวมดีล' . $category . ' ลดราคาจาก ' . platforms_label() . ' เทียบราคาและดูกราฟราคาย้อนหลังก่อนซื้อ';
} elseif ($search !== '') {
    $title = 'ผลการค้นหา "' . $search . '"';
} elseif ($page === 1) {
    // Tells Google the site name and lets it show a search box for the site.
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'           => 'WebSite',
                'name'            => config('site_name'),
                'url'             => abs_url(),
                'inLanguage'      => 'th',
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => abs_url() . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type' => 'Organization',
                'name'  => config('site_name'),
                'url'   => abs_url(),
                'logo'  => abs_url('assets/og-default.jpg'),
            ],
        ],
    ];
}
if ($page > 1) {
    // Page 2, 3… need their own title and description or Google treats them as copies of page 1.
    $title = ($title ?? 'ดีลของใช้แมวทั้งหมด') . ' หน้า ' . $page;
    $description = ($description ?? (string) config('site_tagline')) . ' (หน้า ' . $page . ')';
}
$canonical = abs_url($basePath) . ($page > 1 ? '?page=' . $page : '');
?>
<?php if ($category !== null): ?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › <?= e($category) ?></nav>
  <h1 class="with-icon"><?= svg_category_icon($category) ?><?= e($category) ?> ลดราคา</h1>
  <?php if (count(top_products($category)) >= TOP_MIN_PRODUCTS): ?>
    <a class="top-link" href="<?= e(top_link($category)) ?>">🏆 <?= e($category) ?> ยี่ห้อไหนดี ดูอันดับขายดี →</a>
  <?php endif; ?>
</section>
<?php elseif ($search !== ''): ?>
<section class="page-head">
  <h1>ผลการค้นหา “<?= e($search) ?>”</h1>
</section>
<?php endif; ?>

<?php if ($category === null && $search === ''): ?>
<section class="hero">
  <div>
    <span class="hero-kicker"><?= svg_paw() ?> อัปเดตดีลทุกเช้า</span>
    <h1>ดีลคุ้มๆ<br><span class="hl">สำหรับทาสแมว</span></h1>
    <p><?= e(config('site_tagline')) ?> รู้ก่อนซื้อว่าราคาวันนี้ถูกจริงไหม</p>
    <div class="hero-stats">
      <span class="hero-stat"><strong><?= number_format($total) ?></strong>ดีลวันนี้</span>
      <?php $visits = visit_stats(); ?>
      <span class="hero-stat"><strong><?= number_format($visits['today']) ?></strong>คนเข้าชมวันนี้</span>
      <span class="hero-stat"><strong><?= number_format($visits['total']) ?></strong>ผู้เข้าชมทั้งหมด</span>
      <span class="hero-stat"><strong>90</strong>วัน ประวัติราคา</span>
    </div>
  </div>
  <div class="hero-art"><?= svg_cat_scene() ?></div>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/views/partials/promo-banners.php'; ?>

<?php if (!empty($topCats)): ?>
<nav class="top-links" aria-label="อันดับขายดีแต่ละหมวด">
  <span>🏆 อันดับขายดี:</span>
  <?php foreach ($topCats as $c): ?>
    <a href="<?= e(top_link($c['category'])) ?>"><?= e($c['category']) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($categories): ?>
<nav class="chips" aria-label="หมวดหมู่">
  <a class="chip chip-all<?= $category === null ? ' active' : '' ?>" href="<?= e(url()) ?>">ทั้งหมด</a>
  <?php foreach ($categories as $c): ?>
    <a class="chip<?= $category === $c['category'] ? ' active' : '' ?>" href="<?= e(category_link($c['category'])) ?>"><?= svg_category_icon($c['category']) ?><?= e($c['category']) ?> <small><?= (int) $c['n'] ?></small></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($topSellers): ?>
<section class="section section-tight">
  <div class="section-head">
    <h2>🔥 ขายดีที่สุดวันนี้</h2>
    <span class="muted-note">อัปเดตทุกเช้าจากยอดขายบน Shopee</span>
  </div>
  <div class="grid">
    <?php foreach ($topSellers as $p): ?>
      <?php require APP_ROOT . '/views/partials/card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($category === null && $search === '' && $topSellers): ?>
<div class="section-head section-head-list">
  <h2>ดีลทั้งหมด</h2>
</div>
<?php else: ?>
<h2 class="sr-only">รายการดีล</h2><?php // keeps headings in order: h1 → h2 → card h3 ?>
<?php endif; ?>

<form class="filters" method="get" action="<?= e(url($basePath)) ?>">
  <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
  <label>แพลตฟอร์ม
    <select name="platform">
      <option value="">ทั้งหมด</option>
      <?php foreach (PLATFORMS as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $platform === $key ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>ส่วนลดขั้นต่ำ
    <select name="min">
      <?php foreach ([0 => 'ทั้งหมด', 20 => '20% ขึ้นไป', 40 => '40% ขึ้นไป', 50 => '50% ขึ้นไป', 70 => '70% ขึ้นไป'] as $val => $label): ?>
        <option value="<?= $val ?>"<?= $minDiscount === $val ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>เรียงตาม
    <select name="sort">
      <?php foreach (['discount' => 'ลดเยอะสุด', 'popular' => 'ขายดี', 'price_asc' => 'ราคาต่ำ → สูง', 'price_desc' => 'ราคาสูง → ต่ำ', 'newest' => 'มาใหม่'] as $val => $label): ?>
        <option value="<?= e($val) ?>"<?= $sort === $val ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn-ghost">กรอง</button>
</form>

<p class="result-count"><?= number_format($total) ?> รายการ</p>

<?php if ($products): ?>
  <div class="grid">
    <?php foreach ($products as $p): ?>
      <?php require APP_ROOT . '/views/partials/card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="empty">ยังไม่มีดีลที่ตรงกับเงื่อนไขนี้ ลองเปลี่ยนตัวกรองดูนะ</p>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="หน้า">
  <?php if ($page > 1): ?>
    <a href="<?= e(query_url($basePath, $filters + ['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>">‹ ก่อนหน้า</a>
  <?php endif; ?>
  <span>หน้า <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?>
    <a href="<?= e(query_url($basePath, $filters + ['page' => $page + 1])) ?>">ถัดไป ›</a>
  <?php endif; ?>
</nav>
<?php endif; ?>

<?php // Reading content goes after the deals so it does not interrupt shopping ?>
<?php if (!empty($breeds) || !empty($news)): ?>
<div class="home-duo">
  <?php if (!empty($breeds)): ?>
  <section class="section section-tight">
    <div class="section-head">
      <h2>รู้จักสายพันธุ์แมว</h2>
      <a href="<?= e(url('breeds')) ?>">ดูทั้งหมด →</a>
    </div>
    <div class="breed-grid breed-grid-sm">
      <?php foreach ($breeds as $b): ?>
        <?php require APP_ROOT . '/views/partials/breed-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php if (!empty($news)): ?>
  <section class="section section-tight">
    <div class="section-head">
      <h2>ข่าวแมวล่าสุด</h2>
      <a href="<?= e(url('news')) ?>">ดูทั้งหมด →</a>
    </div>
    <ul class="news-list news-list-sm">
      <?php foreach ($news as $n): ?>
        <?php require APP_ROOT . '/views/partials/news-item.php'; ?>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($articles): ?>
<section class="section section-tight">
  <div class="section-head">
    <h2><?= $category !== null ? 'คู่มือเลือก' . e($category) : 'บทความแนะนำ' ?></h2>
    <a href="<?= e(url('blog')) ?>">ดูทั้งหมด →</a>
  </div>
  <div class="article-grid">
    <?php foreach ($articles as $a): ?>
      <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
