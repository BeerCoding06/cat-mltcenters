<?php
/**
 * Share buttons for LINE / X plus copy-link. Uses plain share URLs — no third-party scripts or tracking.
 * @var string $shareUrl
 * @var string $shareTitle
 */
?>
<div class="share">
  <span class="share-label">แชร์ให้เพื่อนทาสแมว</span>
  <a class="share-btn share-line" href="https://social-plugins.line.me/lineit/share?url=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener">LINE</a>
  <a class="share-btn share-x" href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&amp;text=<?= e(rawurlencode($shareTitle)) ?>" target="_blank" rel="noopener">X</a>
  <button type="button" class="share-btn share-copy" data-url="<?= e($shareUrl) ?>">คัดลอกลิงก์</button>
</div>
<script>
document.querySelectorAll('.share-copy').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var done = function () { btn.textContent = 'คัดลอกแล้ว ✓'; setTimeout(function () { btn.textContent = 'คัดลอกลิงก์'; }, 2000); };
    if (navigator.clipboard) { navigator.clipboard.writeText(btn.dataset.url).then(done, function () { window.prompt('คัดลอกลิงก์นี้', btn.dataset.url); }); }
    else { window.prompt('คัดลอกลิงก์นี้', btn.dataset.url); }
  });
});
</script>
