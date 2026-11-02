<?php /** @var array $n */ ?>
<li class="news-item">
  <a href="<?= e($n['url']) ?>" rel="nofollow noopener" target="_blank">
    <span class="news-title"><?= e($n['title']) ?></span>
    <span class="news-meta"><span class="news-source"><?= e($n['source']) ?></span> · <?= e(thai_date($n['published_at'])) ?> ↗</span>
  </a>
</li>
