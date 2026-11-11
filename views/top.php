<?php
/** @var string $category @var array $products @var array $articles @var array $others */
$year = date('Y');
$updated = max(array_column($products, 'updated_at'));
$title = $category . ' ยี่ห้อไหนดี ' . $year . ' จัดอันดับขายดี ' . count($products) . ' อันดับ';
$description = 'จัดอันดับ' . $category . 'ขายดี ' . count($products) . ' อันดับจากยอดขายจริงบน ' . platforms_label()
    . ' พร้อมราคาวันนี้ ส่วนลด และคะแนนรีวิว อัปเดตราคาอัตโนมัติ';
$canonical = abs_url('top/' . rawurlencode($category));
$ogImage = $products[0]['image_url'] !== '' ? $products[0]['image_url'] : null;
$jsonLd = [
    '@context'        => 'https://schema.org',
    '@type'           => 'ItemList',
    'name'            => $title,
    'numberOfItems'   => count($products),
    'itemListElement' => array_map(function ($p, $i) {
        return ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p['name'], 'url' => abs_url('p/' . rawurlencode($p['slug']))];
    }, $products, array_keys($products)),
];
?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › <a href="<?= e(category_link($category)) ?>"><?= e($category) ?></a> › อันดับขายดี</nav>
  <h1 class="with-icon"><?= svg_category_icon($category) ?><?= e($category) ?> ยี่ห้อไหนดี <?= e($year) ?></h1>
  <p class="lead">จัดอันดับ<?= e($category) ?>ขายดี <?= count($products) ?> อันดับ เรียงตามยอดขายบน <?= e(platforms_label()) ?>
    ถ้ายอดขายใกล้กันจะดูคะแนนรีวิวต่อ · อัปเดต <?= e(thai_date($updated)) ?></p>
  <p class="top-note">ราคาและส่วนลดเปลี่ยนตามโปรโมชันของร้าน ดูราคาล่าสุดได้ที่ปุ่ม "ดูดีล" อันดับนี้มาจากยอดขาย ไม่ใช่ผลการทดสอบของเรา</p>
</section>

<ol class="top-list">
  <?php foreach ($products as $i => $p): ?>
    <li class="top-item">
      <span class="top-rank<?= $i < 3 ? ' top-rank-' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
      <a class="top-media" href="<?= e(product_link($p)) ?>">
        <?php if ($p['image_url'] !== ''): ?>
          <img src="<?= e(image_thumb($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" width="120" height="120" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" referrerpolicy="no-referrer">
        <?php else: ?>
          <span class="img-placeholder"><?= svg_placeholder_cat() ?></span>
        <?php endif; ?>
      </a>
      <div class="top-body">
        <h2 class="top-name"><a href="<?= e(product_link($p)) ?>"><?= e($p['name']) ?></a></h2>
        <div class="meta">
          <span class="platform platform-<?= e($p['platform']) ?>"><?= e(PLATFORMS[$p['platform']]) ?></span>
          <?php if ((int) $p['sold'] > 0): ?><span>ขายแล้ว <?= e(number_format((int) $p['sold'])) ?></span><?php endif; ?>
          <?php if ((float) $p['rating'] > 0): ?><span>★ <?= e(number_format((float) $p['rating'], 1)) ?></span><?php endif; ?>
        </div>
        <div class="price-row">
          <span class="price"><?= e(baht($p['price'])) ?></span>
          <?php if ((int) $p['discount'] > 0): ?>
            <s class="price-old"><?= e(baht($p['original_price'])) ?></s>
            <span class="badge-discount badge-inline">-<?= (int) $p['discount'] ?>%</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="top-actions">
        <a class="btn btn-deal" href="<?= e(product_go_link($p, 'rank')) ?>" rel="sponsored nofollow noopener" target="_blank">ดูราคาล่าสุดใน <?= e(PLATFORMS[$p['platform']]) ?></a>
        <a class="top-history" href="<?= e(product_link($p)) ?>">ดูประวัติราคา</a>
      </div>
    </li>
  <?php endforeach; ?>
</ol>

<p><a href="<?= e(category_link($category)) ?>">ดู<?= e($category) ?>ลดราคาทั้งหมด →</a></p>

<?php if ($articles): ?>
  <section class="section">
    <div class="section-head"><h2>อ่านก่อนเลือก<?= e($category) ?></h2><a href="<?= e(url('blog')) ?>">ดูทั้งหมด</a></div>
    <div class="article-grid">
      <?php foreach ($articles as $a): ?>
        <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php $others = array_filter($others, function ($o) use ($category) { return $o['category'] !== $category; }); ?>
<?php if ($others): ?>
  <section class="section">
    <h2>อันดับขายดีหมวดอื่น</h2>
    <nav class="chips">
      <?php foreach ($others as $o): ?>
        <a class="chip" href="<?= e(top_link($o['category'])) ?>"><?= svg_category_icon($o['category']) ?><?= e($o['category']) ?> ยี่ห้อไหนดี</a>
      <?php endforeach; ?>
    </nav>
  </section>
<?php endif; ?>
