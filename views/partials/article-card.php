<?php /** @var array $a */ ?>
<article class="article-card">
  <a class="article-card-media" href="<?= e(article_link($a)) ?>" aria-label="<?= e($a['title']) ?>" tabindex="-1">
    <?php if ($a['cover_image'] !== ''): ?>
      <img class="photo" src="<?= e(article_cover_url($a)) ?>" alt="" loading="lazy" width="1200" height="675" referrerpolicy="no-referrer">
    <?php else: ?>
      <span class="img-placeholder"><?= svg_logo() ?></span>
    <?php endif; ?>
  </a>
  <div class="article-card-body">
    <?php if ($a['category'] !== ''): ?><span class="eyebrow"><?= e($a['category']) ?></span><?php endif; ?>
    <h3><a href="<?= e(article_link($a)) ?>"><?= e($a['title']) ?></a></h3>
    <?php if ($a['excerpt'] !== ''): ?><p><?= e($a['excerpt']) ?></p><?php endif; ?>
  </div>
</article>
