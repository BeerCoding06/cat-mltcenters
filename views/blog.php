<?php
/**
 * @var array $entries  [['type' => 'article'|'breed', 'row' => [...]], ...]
 * @var int $page
 * @var int $pages
 */
$title = 'บทความเรื่องแมว สายพันธุ์ นิสัย และการเลี้ยง';
$description = 'บทความเรื่องแมว รู้จักสายพันธุ์แมวไทยและแมวต่างประเทศ นิสัย ลักษณะ การเลี้ยง พร้อมดีลของใช้แมว';
$canonical = abs_url('blog') . ($page > 1 ? '?page=' . $page : '');
?>
<section class="page-head">
  <nav class="crumbs"><a href="<?= e(url()) ?>">หน้าแรก</a> › บทความ</nav>
  <h1>บทความเรื่องแมว</h1>
  <p class="lead">รู้จักสายพันธุ์แมว นิสัย และการเลี้ยง พร้อมดีลของใช้แมวราคาดี</p>
</section>

<?php if ($entries): ?>
  <div class="article-grid">
    <?php foreach ($entries as $entry): ?>
      <?php if ($entry['type'] === 'breed'): $b = $entry['row']; ?>
        <?php require APP_ROOT . '/views/partials/breed-article-card.php'; ?>
      <?php else: $a = $entry['row']; ?>
        <?php require APP_ROOT . '/views/partials/article-card.php'; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="empty">ยังไม่มีบทความ เร็วๆ นี้</p>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="หน้า">
  <?php if ($page > 1): ?><a href="<?= e(query_url('blog', ['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>">‹ ก่อนหน้า</a><?php endif; ?>
  <span>หน้า <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e(query_url('blog', ['page' => $page + 1])) ?>">ถัดไป ›</a><?php endif; ?>
</nav>
<?php endif; ?>
