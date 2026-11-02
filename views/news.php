<?php
/**
 * @var array $items
 * @var int $page
 * @var int $pages
 */
$title = 'ข่าวแมวล่าสุด';
$description = 'รวมหัวข้อข่าวแมวและทาสแมวล่าสุดจากสำนักข่าวไทย อัปเดตทุกวัน';
$canonical = abs_url('news') . ($page > 1 ? '?page=' . $page : '');
?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › ข่าวแมว</nav>
  <h1>ข่าวแมวล่าสุด</h1>
  <p class="lead">รวมหัวข้อข่าวเกี่ยวกับแมวจากสำนักข่าวไทย — กดเพื่ออ่านข่าวฉบับเต็มที่เว็บไซต์ต้นทาง</p>
</section>

<?php if ($items): ?>
  <ul class="news-list">
    <?php foreach ($items as $n): ?>
      <?php require APP_ROOT . '/views/partials/news-item.php'; ?>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p class="empty">ยังไม่มีข่าว — กด "ดึงข่าวแมว" ในหลังบ้าน</p>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="หน้า">
  <?php if ($page > 1): ?><a href="<?= e(query_url('news', ['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>">‹ ใหม่กว่า</a><?php endif; ?>
  <span>หน้า <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e(query_url('news', ['page' => $page + 1])) ?>">เก่ากว่า ›</a><?php endif; ?>
</nav>
<?php endif; ?>

<p class="source-note">หัวข้อข่าวและลิขสิทธิ์ของข่าวเป็นของสำนักข่าวต้นทาง เราแสดงเฉพาะหัวข้อพร้อมลิงก์ไปยังข่าวต้นฉบับ</p>
