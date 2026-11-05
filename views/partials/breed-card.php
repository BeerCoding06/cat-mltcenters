<?php /** @var array $b */ ?>
<a class="breed-card" href="<?= e(breed_link($b)) ?>">
  <span class="breed-card-art<?= breed_photo_url($b, true) !== '' ? ' has-photo' : '' ?>"><?php if (breed_photo_url($b, true) !== ''): ?><img class="photo" src="<?= e(breed_photo_url($b, true)) ?>" alt="" loading="lazy" width="400" height="300"><?php else: ?><?= svg_breed_cat(breed_look($b)) ?><?php endif; ?></span>
  <span class="breed-card-body">
    <strong><?= e($b['name_th']) ?></strong>
    <?php if ($b['origin'] !== ''): ?><small>ต้นกำเนิด: <?= e($b['origin']) ?></small><?php endif; ?>
  </span>
</a>
