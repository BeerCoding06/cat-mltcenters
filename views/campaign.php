<?php
/**
 * @var array $campaign    campaign_get()
 * @var array $promos      running promo banners
 * @var array $byCategory  category => biggest discounts
 * @var array $summaries   product id => price_summary()
 * @var array $articles
 */
$name = $campaign['name'];
$year = $campaign['year'];
$dateText = thai_date($campaign['date']);
$title = 'โปร ' . $name . ' ' . $year . ' ของใช้แมว ควรตุนอะไร? รวมดีลอาหารแมว ทรายแมว';
$description = 'รวมดีลของใช้แมวช่วงโปร ' . $name . ' (' . $dateText . ') อาหารแมว ทรายแมว ขนมแมว จาก ' . platforms_label()
    . ' พร้อมราคาต่ำสุด 30 วันให้เช็กก่อนกดซื้อ';
$canonical = abs_url('sale/' . $campaign['slug']);
// Questions people search before a shopping day; also sent as FAQPage data so Google can show them in results.
$faq = [
    ['โปร ' . $name . ' เริ่มกี่โมง?',
     'ร้านส่วนใหญ่บน ' . platforms_label() . ' เริ่มลดตั้งแต่ 00:00 น. ของวันที่ ' . $dateText . ' และบางร้านเปิดดีลก่อนวันจริง 1–2 วัน ใส่ของลงตะกร้าและเก็บโค้ดไว้ก่อนจะได้กดจ่ายทันที'],
    ['ของใช้แมวอะไรน่าตุนช่วง ' . $name . '?',
     'ของที่ใช้ทุกวันและเก็บได้นาน เช่น อาหารเม็ด ทรายแมว ขนมแมวเลีย และอาหารเปียกแบบยกลัง ของชิ้นใหญ่อย่างคอนโดแมวหรือน้ำพุแมวก็คุ้มถ้ารออยู่แล้ว'],
    ['รู้ได้ยังไงว่าลดราคาจริง?',
     'เปิดหน้าสินค้าบนเว็บเราเพื่อดูกราฟราคาย้อนหลังและราคาต่ำสุดใน 30 วัน ถ้าราคาวันโปรไม่ต่ำกว่าช่วงปกติ แปลว่าส่วนลดไม่ได้พิเศษจริง'],
    ['ตุนอาหารแมวเม็ดได้นานแค่ไหน?',
     'ถุงที่ยังไม่เปิดให้ดูวันหมดอายุบนถุง ส่วนถุงที่เปิดแล้วควรใช้ให้หมดใน 4–6 สัปดาห์ และเก็บในที่แห้ง ปิดถุงให้สนิท จะได้ไม่ชื้นและไม่หืน'],
];
$jsonLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(function ($qa) {
        return ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]];
    }, $faq),
];
if ($campaign['state'] === 'upcoming') {
    $status = 'อีก ' . (int) $campaign['days_left'] . ' วันถึงโปร ' . $name . ' (' . $dateText . ')';
} elseif ($campaign['state'] === 'live') {
    $status = 'วันนี้โปร ' . $name . ' แล้ว!';
} else {
    $status = 'โปร ' . $name . ' ปีนี้จบแล้ว ดีลด้านล่างยังอัปเดตราคาทุกวัน';
}
?>
<section class="page-head campaign-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › โปร <?= e($name) ?></nav>
  <h1>โปร <?= e($name) ?> <?= e((string) $year) ?> ของใช้แมว ควรตุนอะไร?</h1>
  <p class="campaign-status"><?= e($status) ?></p>
  <p class="lead">รวมดีลของแมวที่ลดเยอะสุดแต่ละหมวด พร้อมราคาต่ำสุดใน 30 วันจากประวัติราคาที่เราเก็บไว้ ใช้เช็กว่าลดจริงก่อนกดซื้อ</p>
</section>

<?php require APP_ROOT . '/views/partials/promo-banners.php'; ?>

<section class="section campaign-tips">
  <h2>เตรียมตัวก่อน <?= e($name) ?></h2>
  <ol>
    <li><strong>ใส่ของลงตะกร้าไว้ก่อน</strong> ของที่ใช้ประจำอย่างอาหารและทราย จะได้กดจ่ายทันทีตอนโปรเริ่ม</li>
    <li><strong>เก็บโค้ดส่วนลดล่วงหน้า</strong> ทั้งโค้ดร้านและโค้ดแพลตฟอร์ม บางโค้ดมีจำนวนจำกัด</li>
    <li><strong>เช็กราคาย้อนหลัง</strong> กดเข้าหน้าสินค้าบนเว็บเราเพื่อดูกราฟราคา ถ้าราคาวันโปรไม่ต่ำกว่าปกติ รอรอบหน้าก็ได้</li>
    <li><strong>ตุนพอดีใช้</strong> อาหารเม็ดเปิดถุงแล้วควรใช้ให้หมดใน 4–6 สัปดาห์ ซื้อเกินอาจเหม็นหืน</li>
  </ol>
</section>

<?php foreach ($byCategory as $category => $products): ?>
  <section class="section">
    <div class="section-head">
      <h2 class="with-icon"><?= svg_category_icon($category) ?><?= e($category) ?></h2>
      <a href="<?= e(category_link($category)) ?>">ดูทั้งหมด</a>
    </div>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php require APP_ROOT . '/views/partials/card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<section class="section campaign-faq">
  <h2>คำถามที่พบบ่อยเรื่องโปร <?= e($name) ?></h2>
  <?php foreach ($faq as $qa): ?>
    <details>
      <summary><?= e($qa[0]) ?></summary>
      <p><?= e($qa[1]) ?></p>
    </details>
  <?php endforeach; ?>
</section>

<p class="top-note">ราคาบนหน้านี้คือราคาที่เราบันทึกล่าสุด ส่วนลดวันโปรจริงขึ้นกับร้านและโค้ด กดปุ่มดูราคาบนการ์ดเพื่อเช็กราคาล่าสุดบนร้าน
  · <a href="<?= e(url('deals')) ?>">ดูดีลประจำสัปดาห์</a></p>

<?php if (config('line_oa_url')): ?>
  <section class="line-cta">
    <p><strong>อยากได้ดีลเด็ดวัน <?= e($name) ?>?</strong> เพิ่มเพื่อน LINE เราส่งสรุปให้ในวันโปร</p>
    <a class="btn btn-line" href="<?= e((string) config('line_oa_url')) ?>" rel="noopener" target="_blank">เพิ่มเพื่อนใน LINE</a>
  </section>
<?php endif; ?>

<?php if ($articles): ?>
  <section class="section">
    <div class="section-head"><h2>อ่านก่อนตุนของ</h2><a href="<?= e(url('blog')) ?>">ดูทั้งหมด</a></div>
    <div class="article-grid">
      <?php foreach ($articles as $a): ?>
        <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
