<?php
/**
 * @var array $article
 * @var array $rendered  ['html' => …, 'toc' => …]
 * @var bool  $isPreview
 * @var array $deals
 * @var array $more
 */
$title = $article['title'];
$description = $article['excerpt'] !== '' ? $article['excerpt'] : mb_substr(trim(strip_tags($rendered['html'])), 0, 155);
$canonical = abs_url('a/' . rawurlencode($article['slug']));
$noindex = $isPreview;
$coverUrl = article_cover_url($article);
$ogImage = $coverUrl !== '' ? ($coverUrl[0] === '/' ? abs_url(ltrim($coverUrl, '/')) : $coverUrl) : article_first_product_image($article['body']);
$ogType = 'article';
$updated = $article['updated_at'];
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'         => 'Article',
            'headline'      => $article['title'],
            'description'   => $description,
            'datePublished' => date('c', strtotime($article['published_at'] ?: $article['created_at'])),
            'dateModified'  => date('c', strtotime($updated)),
            'mainEntityOfPage' => $canonical,
            'author'        => ['@type' => 'Organization', 'name' => config('site_name')],
            'publisher'     => ['@type' => 'Organization', 'name' => config('site_name')],
        ] + ($coverUrl !== '' ? ['image' => $ogImage] : []),
        [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก', 'item' => abs_url('')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'บทความ', 'item' => abs_url('blog')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title']],
            ],
        ],
    ],
];
?>
<?php if ($isPreview): ?>
  <p class="preview-bar">ตัวอย่างฉบับร่าง — คนทั่วไปยังมองไม่เห็นหน้านี้ และ Google จะไม่เก็บหน้านี้</p>
<?php endif; ?>

<nav class="crumbs">
  <a href="<?= e(url()) ?>">หน้าแรก</a> › <a href="<?= e(url('blog')) ?>">บทความ</a>
  <?php if ($article['category'] !== ''): ?> › <a href="<?= e(category_link($article['category'])) ?>"><?= e($article['category']) ?></a><?php endif; ?>
</nav>

<article class="article">
  <header class="article-head">
    <h1><?= e($article['title']) ?></h1>
    <p class="meta">
      <span>อัปเดต <?= e(thai_date($updated)) ?></span>
      <span>อ่าน <?= reading_minutes($article['body']) ?> นาที</span>
    </p>
    <?php if ($article['cover_image'] !== ''): ?>
      <img class="article-cover photo" src="<?= e($coverUrl) ?>" alt="<?= e($article['title']) ?>" width="1200" height="675" fetchpriority="high" referrerpolicy="no-referrer">
      <?php if ($credit = article_cover_credit($article)): ?>
        <p class="article-cover-credit">ภาพ: <a href="<?= e($credit['source']) ?>" rel="nofollow noopener" target="_blank"><?= e($credit['author']) ?> / Unsplash</a></p>
      <?php endif; ?>
    <?php endif; ?>
    <p class="disclosure-inline">บทความนี้มีลิงก์ Affiliate — ถ้าซื้อผ่านลิงก์ เราอาจได้ค่าคอมมิชชันเล็กน้อย โดยคุณไม่ต้องจ่ายเพิ่ม</p>
  </header>

  <?php require APP_ROOT . '/views/partials/promo-banners.php'; ?>

  <?php if (count($rendered['toc']) >= 3): ?>
  <nav class="toc" aria-label="สารบัญ">
    <strong>สารบัญ</strong>
    <ol>
      <?php foreach ($rendered['toc'] as $item): ?>
        <li><a href="#<?= e($item[0]) ?>"><?= e(strip_tags(md_inline($item[1]))) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </nav>
  <?php endif; ?>

  <div class="prose article-body">
    <?= $rendered['html'] ?>
  </div>

  <?php if (count($mentioned) >= 2): ?>
  <section class="deal-box" aria-labelledby="deal-box-title">
    <h2 id="deal-box-title">สรุปสินค้าในบทความนี้</h2>
    <p class="deal-box-note">รวมของที่พูดถึงไว้ที่เดียว ราคาอัปเดตอัตโนมัติพร้อมช่วงราคา 30 วันจากที่เราบันทึกไว้ กดดูดีลเพื่อเช็กราคาล่าสุดบนร้าน</p>
    <ol class="deal-box-list">
      <?php foreach ($mentioned as $p): ?>
        <li>
          <a class="deal-box-media" href="<?= e(product_link($p)) ?>" tabindex="-1">
            <?php if ($p['image_url'] !== ''): ?><img src="<?= e(image_thumb($p['image_url'])) ?>" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
          </a>
          <span class="deal-box-name"><a href="<?= e(product_link($p)) ?>"><?= e($p['name']) ?></a>
            <span class="deal-box-price"><?= e(baht($p['price'])) ?><?php if ((int) $p['discount'] > 0): ?> <small>-<?= (int) $p['discount'] ?>%</small><?php endif; ?></span>
            <?php $sum = price_summary($p); ?>
            <?php if ($sum['is_lowest']): ?>
              <span class="deal-box-low best">ราคาต่ำสุดใน 30 วัน</span>
            <?php elseif ($sum['max'] > $sum['min'] + 0.009): ?>
              <span class="deal-box-low">30 วันที่ผ่านมา <?= e(baht($sum['min'])) ?>–<?= e(baht($sum['max'])) ?></span>
            <?php endif; ?></span>
          <a class="btn btn-deal" href="<?= e(product_go_link($p)) ?>" rel="sponsored nofollow noopener" target="_blank">ดูดีล</a>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if ($article['category'] !== '' && count(top_products($article['category'])) >= TOP_MIN_PRODUCTS): ?>
      <a class="top-link" href="<?= e(top_link($article['category'])) ?>">🏆 ดูอันดับ<?= e($article['category']) ?>ขายดีทั้งหมด →</a>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php
  $shareUrl = $canonical;
  $shareTitle = $article['title'];
  require APP_ROOT . '/views/partials/share.php';
  ?>
</article>

<?php if ($deals): ?>
<section class="section">
  <h2>ดีล<?= e($article['category']) ?>ล่าสุด</h2>
  <div class="grid">
    <?php foreach ($deals as $p): ?>
      <?php require APP_ROOT . '/views/partials/card.php'; ?>
    <?php endforeach; ?>
  </div>
  <p><a href="<?= e(category_link($article['category'])) ?>">ดูดีล<?= e($article['category']) ?>ทั้งหมด →</a></p>
</section>
<?php endif; ?>

<?php if ($more): ?>
<section class="section">
  <h2>บทความอื่นที่น่าสนใจ</h2>
  <div class="article-grid">
    <?php foreach ($more as $a): ?>
      <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
