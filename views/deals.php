<?php
/**
 * @var array $week        [monday, sunday]
 * @var array $drops       products cheaper than earlier this week
 * @var array $byCategory  category => biggest discounts
 * @var array $summaries   product id => price_summary()
 * @var array|null $campaign
 * @var array $articles
 */
$range = thai_date($week[0]) . ' – ' . thai_date($week[1]);
$title = 'ดีลแมวประจำสัปดาห์ ' . $range . ' อาหารแมว ทรายแมว ลดราคา';
$description = 'รวมดีลของใช้แมวประจำสัปดาห์ ' . $range . ' สินค้าที่ราคาลดลงจริงจากประวัติราคา และส่วนลดสูงสุดแต่ละหมวด จาก '
    . platforms_label() . ' อัปเดตทุกวัน';
$canonical = abs_url('deals');
$catCount = count($byCategory);
?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › ดีลประจำสัปดาห์</nav>
  <h1>ดีลแมวประจำสัปดาห์</h1>
  <p class="lead"><?= e($range) ?> · รวมของที่ราคาลงจริงจากประวัติราคาที่เราเก็บไว้ และส่วนลดสูงสุดของ <?= $catCount ?> หมวด
    อัปเดตทุกวัน</p>
  <?php if ($campaign): ?>
    <a class="campaign-strip" href="<?= e(campaign_link($campaign)) ?>">
      <?= $campaign['state'] === 'live' ? 'วันนี้' : 'อีก ' . (int) $campaign['days_left'] . ' วัน' ?> โปร <?= e($campaign['name']) ?> · ดูของแมวที่ควรตุน →
    </a>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head"><h2>ราคาลดลงสัปดาห์นี้</h2></div>
  <?php if ($drops): ?>
    <p class="section-note">ราคาวันนี้ต่ำกว่าราคาสูงสุดใน 7 วันที่ผ่านมา เทียบจากราคาที่เราบันทึกเอง ไม่ใช่ราคาตั้งของร้าน</p>
    <div class="grid">
      <?php foreach ($drops as $p): ?>
        <?php require APP_ROOT . '/views/partials/card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="section-note">สัปดาห์นี้ยังไม่มีสินค้าที่ราคาลดลงจากที่เราบันทึกไว้ ดูส่วนลดสูงสุดแต่ละหมวดด้านล่างก่อนได้</p>
  <?php endif; ?>
</section>

<?php foreach ($byCategory as $category => $products): ?>
  <section class="section">
    <div class="section-head">
      <h2 class="with-icon"><?= svg_category_icon($category) ?><?= e($category) ?> ลดเยอะสุด</h2>
      <a href="<?= e(category_link($category)) ?>">ดูทั้งหมด</a>
    </div>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php require APP_ROOT . '/views/partials/card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if (config('line_oa_url')): ?>
  <section class="line-cta">
    <p><strong>ไม่อยากพลาดดีล?</strong> รับสรุปดีลแมวประจำสัปดาห์ทาง LINE ทุกวันจันทร์</p>
    <a class="btn btn-line" href="<?= e((string) config('line_oa_url')) ?>" rel="noopener" target="_blank">เพิ่มเพื่อนใน LINE</a>
  </section>
<?php endif; ?>

<p class="top-note">ราคาและส่วนลดเปลี่ยนตามโปรโมชันของร้าน กดปุ่มดูราคาบนการ์ดเพื่อเช็กราคาล่าสุดบนร้านก่อนสั่งซื้อ</p>

<?php if ($articles): ?>
  <section class="section">
    <div class="section-head"><h2>อ่านก่อนซื้อ</h2><a href="<?= e(url('blog')) ?>">ดูทั้งหมด</a></div>
    <div class="article-grid">
      <?php foreach ($articles as $a): ?>
        <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
