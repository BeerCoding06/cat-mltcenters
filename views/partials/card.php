<?php /** @var array $p */ ?>
<article class="card">
  <a class="card-media" href="<?= e(product_link($p)) ?>">
    <?php if ($p['image_url'] !== ''): ?>
      <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" referrerpolicy="no-referrer">
    <?php else: ?>
      <span class="img-placeholder"><?= svg_placeholder_cat() ?>ยังไม่มีรูป</span>
    <?php endif; ?>
    <?php if ((int) $p['discount'] > 0): ?>
      <span class="badge-discount">-<?= (int) $p['discount'] ?>%</span>
    <?php endif; ?>
    <?php if (!empty($p['top_rank'])): ?>
      <span class="badge-rank">ขายดี #<?= (int) $p['top_rank'] ?></span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <span class="platform platform-<?= e($p['platform']) ?>"><?= e(PLATFORMS[$p['platform']]) ?></span>
    <h3 class="card-title"><a href="<?= e(product_link($p)) ?>"><?= e($p['name']) ?></a></h3>
    <div class="price-row">
      <span class="price"><?= e(baht($p['price'])) ?></span>
      <?php if ((int) $p['discount'] > 0): ?>
        <s class="price-old"><?= e(baht($p['original_price'])) ?></s>
      <?php endif; ?>
    </div>
    <div class="meta">
      <?php if ((float) $p['rating'] > 0): ?><span>★ <?= e(number_format((float) $p['rating'], 1)) ?></span><?php endif; ?>
      <?php if ((int) $p['sold'] > 0): ?><span>ขายแล้ว <?= e(number_format((int) $p['sold'])) ?></span><?php endif; ?>
    </div>
    <a class="btn btn-deal" href="<?= e(product_go_link($p)) ?>" rel="sponsored nofollow noopener" target="_blank">ดูดีลที่ <?= e(PLATFORMS[$p['platform']]) ?></a>
  </div>
</article>
