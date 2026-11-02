<?php /** @var array $b */ ?>
<a class="breed-card" href="<?= e(breed_link($b)) ?>">
  <span class="breed-card-art"><?= svg_breed_cat(breed_look($b)) ?></span>
  <span class="breed-card-body">
    <strong><?= e($b['name_th']) ?></strong>
    <?php if ($b['origin'] !== ''): ?><small>ต้นกำเนิด: <?= e($b['origin']) ?></small><?php endif; ?>
  </span>
</a>
