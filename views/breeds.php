<?php
/** @var array $breeds */
$title = 'สายพันธุ์แมว รวมข้อมูลแมวไทยและแมวต่างประเทศ';
$description = 'รู้จักสายพันธุ์แมว ' . count($breeds) . ' สายพันธุ์ ทั้งแมวไทยอย่างวิเชียรมาศ ขาวมณี มาเลศ และแมวต่างประเทศ พร้อมดีลของใช้แมว';
$canonical = abs_url('breeds');
?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › สายพันธุ์แมว</nav>
  <h1>สายพันธุ์แมว</h1>
  <p class="lead">ข้อมูลแมวไทยและแมวต่างประเทศ <?= count($breeds) ?> สายพันธุ์ อัปเดตจาก Wikipedia ภาษาไทย</p>
</section>

<?php if ($breeds): ?>
  <div class="breed-grid">
    <?php foreach ($breeds as $b): ?>
      <?php require APP_ROOT . '/views/partials/breed-card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="empty">ยังไม่มีข้อมูลสายพันธุ์ — กด "ดึงข้อมูลสายพันธุ์" ในหลังบ้าน</p>
<?php endif; ?>

<p class="source-note">ข้อมูลสายพันธุ์จาก <a href="https://www.wikidata.org" rel="nofollow noopener" target="_blank">Wikidata</a>
  และ <a href="https://th.wikipedia.org" rel="nofollow noopener" target="_blank">Wikipedia ภาษาไทย</a>
  (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.th" rel="nofollow noopener" target="_blank">CC BY-SA 4.0</a>)</p>
