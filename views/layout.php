<?php
/** @var string $content */
$siteName = (string) config('site_name');
$pageTitle = isset($title) ? $title . ' | ' . $siteName : $siteName . ' — ' . config('home_title', config('site_tagline'));
$pageDescription = $description ?? (string) config('site_tagline');
// Pages without their own picture share the site card, so links in LINE / X always get a preview image.
$shareImage = !empty($ogImage) ? $ogImage : abs_url('assets/og-default.jpg');
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
  <?php else: ?>
  <?php // Lets Google show large cover images (needed for Discover and image-rich results). ?>
  <meta name="robots" content="max-image-preview:large">
  <?php endif; ?>
  <?php if (!empty($canonical)): ?>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php endif; ?>
  <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:locale" content="th_TH">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <?php if (!empty($canonical)): ?>
  <meta property="og:url" content="<?= e($canonical) ?>">
  <?php endif; ?>
  <meta property="og:image" content="<?= e($shareImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= e($shareImage) ?>">
  <!-- Fonts: Mali + IBM Plex Sans Thai Looped (SIL Open Font License), served from this site (assets/fonts, @font-face
       inlined below). font-display: optional — text is drawn at once and never swaps font later (no layout shift).
       No <link rel=preload> on purpose: Chrome holds the first paint for preloaded optional fonts, so a font that
       is slow to arrive (e.g. not yet in Cloudflare's cache) left the page blank for seconds. -->
  <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
  <?php // Inlined rather than linked: saves a render-blocking round trip (≈7 KB once compressed) ?>
  <style><?= file_get_contents(APP_ROOT . '/assets/fonts/fonts.css') . file_get_contents(APP_ROOT . '/assets/style.css') ?></style>
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
        <a href="<?= e(url('deals')) ?>">ดีลสัปดาห์</a>
        <a href="<?= e(url('breeds')) ?>">สายพันธุ์</a>
        <a href="<?= e(url('news')) ?>">ข่าวแมว</a>
        <a href="<?= e(url('blog')) ?>">บทความ</a>
        <a href="<?= e(url('game')) ?>">เกมแมว</a>
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
        เว็บไซต์นี้เข้าร่วมโปรแกรม Affiliate ของ <?= e(platforms_label()) ?> เราอาจได้รับค่าคอมมิชชันเมื่อคุณซื้อสินค้าผ่านลิงก์ของเรา
        โดยคุณไม่ต้องจ่ายเพิ่ม ราคาและโปรโมชันอาจเปลี่ยนแปลง โปรดตรวจสอบราคาจริงบนหน้าร้านก่อนสั่งซื้อ
      </p>
      <p class="footer-links">
        <a href="<?= e(url('about')) ?>">เกี่ยวกับเรา</a> ·
        <a href="<?= e(url('privacy')) ?>">นโยบายความเป็นส่วนตัว</a> ·
        <a href="<?= e(url('sitemap.xml')) ?>">แผนผังเว็บไซต์</a> ·
        <a href="<?= e((string) config('facebook_url')) ?>" rel="noopener me" target="_blank">เพจ Facebook</a> ·
        <?php if (config('line_oa_url')): ?><a href="<?= e((string) config('line_oa_url')) ?>" rel="noopener" target="_blank">รับดีลทาง LINE</a> ·<?php endif; ?>
        © <?= date('Y') ?> <?= e($siteName) ?>
      </p>
      <p class="credits">ภาพประกอบแมว: <a href="https://www.freepik.com" rel="nofollow noopener" target="_blank">Freepik</a> ·
        ดาว: <a href="https://pixabay.com/vectors/cat-6940169/" rel="nofollow noopener" target="_blank">VectorBy / Pixabay</a></p>
    </div>
  </footer>

  <script src="<?= e(asset('pet-cats.js')) ?>" data-sprites="<?= e(asset('cat-sprites.svg')) ?>" defer></script>
</body>
</html>
