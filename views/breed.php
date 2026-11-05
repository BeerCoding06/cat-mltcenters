<?php
/**
 * @var array $breed
 * @var array $sections  [[heading, text, level], ...]
 * @var array|null $traits
 * @var array $deals
 * @var array $others
 */
$title = 'แมว' . (mb_strpos($breed['name_th'], 'แมว') === 0 ? mb_substr($breed['name_th'], 3) : $breed['name_th'])
    . ($breed['name_en'] !== '' ? ' (' . $breed['name_en'] . ')' : '') . ' นิสัย ลักษณะ การเลี้ยง';
$description = mb_substr(preg_replace('/\s+/u', ' ', $breed['intro']), 0, 150);
$canonical = abs_url('breed/' . rawurlencode($breed['slug']));
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => $title,
    'description' => $description,
    'dateModified' => date('c', strtotime($breed['fetched_at'])),
    'mainEntityOfPage' => $canonical,
    'isBasedOn' => $breed['wiki_url'],
    'license'   => 'https://creativecommons.org/licenses/by-sa/4.0/',
    'publisher' => ['@type' => 'Organization', 'name' => config('site_name')],
];
if (breed_photo_url($breed) !== '') {
    $ogImage = abs_url(ltrim(breed_photo_url($breed), '/'));
    $jsonLd['image'] = $ogImage;
}
?>
<nav class="crumbs">
  <a href="<?= e(url()) ?>">หน้าแรก</a> › <a href="<?= e(url('breeds')) ?>">สายพันธุ์แมว</a> › <?= e($breed['name_th']) ?>
</nav>

<article class="article breed">
  <header class="breed-head">
    <?php $credit = breed_photo_credit($breed); ?>
    <?php if (breed_photo_url($breed) !== ''): ?>
      <figure class="breed-photo">
        <img class="photo" src="<?= e(breed_photo_url($breed)) ?>" alt="แมว<?= e($breed['name_th']) ?>" width="800" height="600">
        <?php if ($credit): ?>
          <figcaption>ภาพ: <?= e($credit['artist']) ?> ·
            <?php if ($credit['license_url'] !== ''): ?><a href="<?= e($credit['license_url']) ?>" rel="nofollow noopener license" target="_blank"><?= e($credit['license']) ?></a><?php else: ?><?= e($credit['license']) ?><?php endif; ?>
            · <a href="<?= e($credit['source']) ?>" rel="nofollow noopener" target="_blank">Wikimedia Commons</a></figcaption>
        <?php endif; ?>
      </figure>
    <?php else: ?>
      <div class="breed-art"><?= svg_breed_cat(breed_look($breed), 'breed-art-cat') ?></div>
    <?php endif; ?>
    <div>
      <h1><?= e($breed['name_th']) ?></h1>
      <?php if ($breed['name_en'] !== ''): ?><p class="breed-en"><?= e($breed['name_en']) ?></p><?php endif; ?>
      <div class="breed-facts">
        <?php if ($breed['origin'] !== ''): ?><span class="fact">🌏 ต้นกำเนิด: <?= e($breed['origin']) ?></span><?php endif; ?>
        <?php if (!empty($traits['life_span'])): ?><span class="fact">⏳ อายุ <?= e($traits['life_span']) ?> ปี</span><?php endif; ?>
        <?php if (!empty($traits['weight_kg'])): ?><span class="fact">⚖️ <?= e($traits['weight_kg']) ?> กก.</span><?php endif; ?>
      </div>
    </div>
  </header>

  <?php if ($traits): ?>
  <section class="traits" aria-label="ลักษณะนิสัย">
    <?php foreach (BREED_TRAITS as $key => $label): if (!isset($traits[$key])) { continue; } ?>
      <div class="trait">
        <span><?= e($label) ?></span>
        <span class="trait-bar" role="img" aria-label="<?= (int) $traits[$key] ?> จาก 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $traits[$key] ? 'on' : '' ?>"></i><?php endfor; ?></span>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <div class="prose article-body">
    <?= breed_paragraphs($breed['intro']) ?>
    <?php foreach ($sections as $s): ?>
      <?php if ((int) ($s[2] ?? 2) > 2): ?>
        <h3><?= e($s[0]) ?></h3>
      <?php else: ?>
        <h2><?= e($s[0]) ?></h2>
      <?php endif; ?>
      <?= breed_paragraphs($s[1]) ?>
    <?php endforeach; ?>
  </div>

  <aside class="source-box">
    <strong>ที่มาของเนื้อหา</strong>
    เนื้อหาในหน้านี้มาจากบทความ
    <a href="<?= e($breed['wiki_url']) ?>" rel="noopener" target="_blank">“<?= e($breed['wiki_title']) ?>”</a>
    ใน Wikipedia ภาษาไทย โดยผู้ร่วมเขียน Wikipedia เผยแพร่ภายใต้สัญญาอนุญาต
    <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.th" rel="noopener" target="_blank">CC BY-SA 4.0</a>
    — เราตัดส่วนอ้างอิงออกและจัดรูปแบบใหม่ เนื้อหาส่วนนี้จึงใช้สัญญาอนุญาตเดียวกัน
    <?php if ($traits): ?>· ระดับนิสัยจาก <a href="https://thecatapi.com" rel="noopener" target="_blank">TheCatAPI</a><?php endif; ?>
    <span class="muted-note">· อัปเดต <?= e(thai_date($breed['fetched_at'])) ?> · ข้อมูลทั่วไป ไม่ใช่คำแนะนำทางสัตวแพทย์</span>
  </aside>
</article>

<?php if ($deals): ?>
<section class="section">
  <h2>ของใช้แมวขายดี</h2>
  <div class="grid">
    <?php foreach ($deals as $p): ?>
      <?php require APP_ROOT . '/views/partials/card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($others): ?>
<section class="section">
  <h2>สายพันธุ์อื่นๆ</h2>
  <div class="breed-grid">
    <?php foreach ($others as $b): ?>
      <?php require APP_ROOT . '/views/partials/breed-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
