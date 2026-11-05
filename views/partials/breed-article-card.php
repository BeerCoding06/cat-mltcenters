<?php /** @var array $b  a breed, shown as an article card on the blog page */ ?>
<article class="article-card">
  <a class="article-card-media article-card-breed" href="<?= e(breed_link($b)) ?>" aria-label="<?= e($b['name_th']) ?>" tabindex="-1"><?= svg_breed_cat(breed_look($b)) ?></a>
  <div class="article-card-body">
    <span class="eyebrow">สายพันธุ์แมว</span>
    <h3><a href="<?= e(breed_link($b)) ?>"><?= e(mb_strpos($b['name_th'], 'แมว') === 0 ? $b['name_th'] : 'แมว' . $b['name_th']) ?> นิสัย ลักษณะ และการเลี้ยง</a></h3>
    <p><?= e(mb_substr(preg_replace('/\s+/u', ' ', $b['intro']), 0, 140)) ?></p>
  </div>
</article>
