<?php /** @var array $promos running campaigns from promos_live() */ ?>
<?php if (!empty($promos)): ?>
<section class="promo-banners" aria-label="โปรโมชันตอนนี้">
  <?php foreach ($promos as $promo): ?>
    <?php $daysLeft = (int) ceil((strtotime($promo['ends_at']) - time()) / 86400); ?>
    <a class="promo-banner" href="<?= e(promo_link($promo)) ?>" rel="sponsored nofollow noopener" target="_blank">
      <span class="promo-banner-tag"><?= svg_paw() ?> โปรตอนนี้</span>
      <span class="promo-banner-text">
        <strong><?= e($promo['title']) ?></strong>
        <?php if ($promo['subtitle'] !== ''): ?><span><?= e($promo['subtitle']) ?></span><?php endif; ?>
        <small><?= $daysLeft <= 1 ? 'วันสุดท้าย!' : 'ถึง ' . e(thai_date($promo['ends_at'])) . ' · เหลือ ' . $daysLeft . ' วัน' ?></small>
      </span>
      <span class="btn btn-deal promo-banner-btn">ดูโปร ↗</span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>
