<?php
/** @var string $content */
$siteName = (string) config('site_name');
$pageTitle = isset($title) ? $title . ' | ' . $siteName : $siteName . ' — ' . config('site_tagline');
$pageDescription = $description ?? (string) config('site_tagline');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <?php if (!empty($noindex)): ?>
  <meta name="robots" content="noindex, follow">
  <?php endif; ?>
  <?php if (!empty($canonical)): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <?php if (!empty($ogImage)): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Fonts: Mali + IBM Plex Sans Thai Looped, both SIL Open Font License (free for commercial use) -->
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai+Looped:wght@400;500;600;700&family=Mali:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
  <?php if (!empty($jsonLd)): ?>
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <?php endif; ?>
</head>
<body>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="logo" href="<?= e(url()) ?>"><span class="logo-mark"><?= svg_logo() ?></span><?= e($siteName) ?></a>
      <nav class="main-nav">
        <a href="<?= e(url()) ?>">ดีลวันนี้</a>
        <a href="<?= e(url('breeds')) ?>">สายพันธุ์</a>
        <a href="<?= e(url('news')) ?>">ข่าวแมว</a>
        <a href="<?= e(url('blog')) ?>">บทความ</a>
      </nav>
      <form class="search" action="<?= e(url()) ?>" method="get" role="search">
        <input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="<?= e(config('search_placeholder', 'ค้นหาสินค้า')) ?>" aria-label="ค้นหาสินค้า">
        <button type="submit">ค้นหา</button>
      </form>
    </div>
  </header>

  <main class="wrap">
    <?= $content ?>
  </main>

  <footer class="site-footer">
    <div class="footer-cat"><?= svg_footer_cat() ?></div>
    <div class="wrap">
      <p class="footer-brand"><?= svg_logo() ?><?= e($siteName) ?></p>
      <p class="disclosure">
        เว็บไซต์นี้เข้าร่วมโปรแกรม Affiliate ของ Lazada และ Shopee เราอาจได้รับค่าคอมมิชชันเมื่อคุณซื้อสินค้าผ่านลิงก์ของเรา
        โดยคุณไม่ต้องจ่ายเพิ่ม ราคาและโปรโมชันอาจเปลี่ยนแปลง โปรดตรวจสอบราคาจริงบนหน้าร้านก่อนสั่งซื้อ
      </p>
      <p class="footer-links">
        <a href="<?= e(url('about')) ?>">เกี่ยวกับเรา</a> ·
        <a href="<?= e(url('sitemap.xml')) ?>">แผนผังเว็บไซต์</a> ·
        © <?= date('Y') ?> <?= e($siteName) ?>
      </p>
      <p class="credits">ภาพประกอบแมว: <a href="https://www.freepik.com" rel="nofollow noopener" target="_blank">Freepik</a> ·
        ดาว: <a href="https://pixabay.com/vectors/cat-6940169/" rel="nofollow noopener" target="_blank">VectorBy / Pixabay</a></p>
    </div>
  </footer>

  <script src="<?= e(asset('pet-cats.js')) ?>" data-sprites="<?= e(asset('cat-sprites.svg')) ?>" defer></script>
</body>
</html>
