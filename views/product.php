<?php
/**
 * @var array $product
 * @var array $history
 * @var array $summary
 * @var array $compare
 * @var array $related
 */
$platformLabel = PLATFORMS[$product['platform']];
$title = short_text($product['name'], 55) . ' ราคา ' . baht($product['price']);
$description = $product['name'] . ' ราคา ' . baht($product['price'])
    . ((int) $product['discount'] > 0 ? ' ลด ' . (int) $product['discount'] . '%' : '')
    . ' ที่ ' . $platformLabel . ' ดูกราฟราคาย้อนหลังและเทียบราคาก่อนซื้อ';
$canonical = abs_url('p/' . rawurlencode($product['slug']));
$ogImage = $product['image_url'] ?: null;
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => $product['description'] !== '' ? $product['description'] : $description,
    'offers'      => [
        '@type'         => 'Offer',
        'price'         => number_format((float) $product['price'], 2, '.', ''),
        'priceCurrency' => 'THB',
        'availability'  => 'https://schema.org/InStock',
        'url'           => $canonical,
        'seller'        => ['@type' => 'Organization', 'name' => $platformLabel],
    ],
];
if ($product['image_url'] !== '') {
    $jsonLd['image'] = $product['image_url'];
}
?>
<nav class="crumbs">
  <a href="<?= e(url()) ?>">หน้าแรก</a>
  <?php if ($product['category'] !== ''): ?> › <a href="<?= e(category_link($product['category'])) ?>"><?= e($product['category']) ?></a><?php endif; ?>
</nav>

<article class="product">
  <div class="product-media">
    <?php if ($product['image_url'] !== ''): ?>
      <img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>" referrerpolicy="no-referrer">
    <?php else: ?>
      <span class="img-placeholder"><?= svg_placeholder_cat() ?>ยังไม่มีรูป</span>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <span class="platform platform-<?= e($product['platform']) ?>"><?= e($platformLabel) ?></span>
    <h1><?= e($product['name']) ?></h1>

    <div class="price-row price-row-lg">
      <span class="price"><?= e(baht($product['price'])) ?></span>
      <?php if ((int) $product['discount'] > 0): ?>
        <s class="price-old"><?= e(baht($product['original_price'])) ?></s>
        <span class="badge-discount badge-inline">-<?= (int) $product['discount'] ?>%</span>
      <?php endif; ?>
    </div>

    <?php if ($summary['is_lowest']): ?>
      <p class="callout callout-good">✓ ราคาต่ำสุดในรอบ 30 วัน</p>
    <?php elseif ($summary['min'] < (float) $product['price']): ?>
      <p class="callout">ในรอบ 30 วันเคยลดเหลือ <?= e(baht($summary['min'])) ?> — ถ้าไม่รีบ อาจรอโปรรอบหน้า</p>
    <?php endif; ?>

    <div class="meta">
      <?php if ((float) $product['rating'] > 0): ?><span>★ <?= e(number_format((float) $product['rating'], 1)) ?></span><?php endif; ?>
      <?php if ((int) $product['sold'] > 0): ?><span>ขายแล้ว <?= e(number_format((int) $product['sold'])) ?> ชิ้น</span><?php endif; ?>
      <span>อัปเดต <?= e(thai_date($product['updated_at'])) ?></span>
    </div>

    <a class="btn btn-deal btn-lg" href="<?= e(product_go_link($product, 'product')) ?>" rel="sponsored nofollow noopener" target="_blank">ไปที่ร้านค้าบน <?= e($platformLabel) ?> →</a>
    <p class="fine">ราคาอาจเปลี่ยนแปลง โปรดตรวจสอบราคาบนหน้าร้านก่อนสั่งซื้อ</p>
    <?php if (alerts_enabled()): ?>
      <form class="price-alert" method="post" action="<?= e(url('alert/' . (int) $product['id'])) ?>">
        <label for="alert-email"><strong>🔔 แจ้งเตือนเมื่อราคาลด</strong> กรอกอีเมล เราจะส่งบอกเมื่อสินค้านี้ถูกลงจาก <?= e(baht($product['price'])) ?></label>
        <span class="price-alert-row">
          <input id="alert-email" type="email" name="email" required maxlength="200" placeholder="อีเมลของคุณ" autocomplete="email">
          <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
          <button class="btn btn-ghost">รับแจ้งเตือน</button>
        </span>
        <small>ใช้อีเมลนี้เพื่อแจ้งราคาสินค้านี้เท่านั้น ยกเลิกได้จากลิงก์ในทุกอีเมล</small>
      </form>
    <?php endif; ?>
    <?php
    $shareUrl = $canonical;
    $shareTitle = $product['name'] . ' ราคา ' . baht($product['price']);
    require APP_ROOT . '/views/partials/share.php';
    ?>
  </div>
</article>

<?php if ($articles): ?>
<section class="section">
  <h2>อ่านก่อนซื้อ</h2>
  <div class="article-grid">
    <?php foreach ($articles as $a): ?>
      <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($compare): ?>
<section class="section">
  <h2>เทียบราคาร้านอื่น</h2>
  <table class="compare">
    <thead><tr><th>แพลตฟอร์ม</th><th>สินค้า</th><th>ราคา</th><th></th></tr></thead>
    <tbody>
      <tr class="current">
        <td><span class="platform platform-<?= e($product['platform']) ?>"><?= e($platformLabel) ?></span></td>
        <td><?= e($product['name']) ?> <small>(หน้านี้)</small></td>
        <td class="num"><?= e(baht($product['price'])) ?></td>
        <td></td>
      </tr>
      <?php foreach ($compare as $c): ?>
      <tr>
        <td><span class="platform platform-<?= e($c['platform']) ?>"><?= e(PLATFORMS[$c['platform']]) ?></span></td>
        <td><a href="<?= e(product_link($c)) ?>"><?= e($c['name']) ?></a></td>
        <td class="num"><?= e(baht($c['price'])) ?></td>
        <td><a class="btn btn-small" href="<?= e(product_go_link($c, 'compare')) ?>" rel="sponsored nofollow noopener" target="_blank">ดูดีล</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<section class="section">
  <h2>กราฟราคาย้อนหลัง 90 วัน</h2>
  <?php require APP_ROOT . '/views/partials/price-chart.php'; ?>
</section>

<?php if ($product['description'] !== ''): ?>
<section class="section prose">
  <h2>รายละเอียด</h2>
  <?= nl2br(e($product['description'])) ?>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section">
  <h2>ดีลอื่นในหมวด<?= e($product['category']) ?></h2>
  <div class="grid">
    <?php foreach ($related as $p): ?>
      <?php require APP_ROOT . '/views/partials/card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
