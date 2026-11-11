<?php
/**
 * @var string     $message
 * @var array|null $product
 */
$title = 'แจ้งเตือนราคา';
$noindex = true;
?>
<section class="empty-state">
  <h1>แจ้งเตือนราคา</h1>
  <p class="lead"><?= e($message) ?></p>
  <?php if ($product): ?>
    <a class="btn btn-deal" href="<?= e(product_link($product)) ?>">กลับไปหน้าสินค้า</a>
  <?php else: ?>
    <a class="btn btn-deal" href="<?= e(url()) ?>">ไปหน้าแรก</a>
  <?php endif; ?>
</section>
