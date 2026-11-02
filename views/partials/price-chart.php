<?php
/**
 * Step chart of $history (price points) ending at today's price.
 * @var array $history
 * @var array $product
 */
$points = [];
foreach ($history as $h) {
    $points[] = [strtotime($h['recorded_at']), (float) $h['price']];
}
$points[] = [time(), (float) $product['price']];

$w = 640;
$h = 200;
$padL = 8;
$padR = 8;
$padT = 16;
$padB = 24;

$t0 = $points[0][0];
$t1 = max($t0 + 1, end($points)[0]);
$prices = array_column($points, 1);
$pMin = min($prices);
$pMax = max($prices);
$range = $pMax - $pMin;
if ($range < 0.01) {
    $range = max(1.0, $pMax * 0.1);
    $pMin -= $range / 2;
}

$x = function ($t) use ($t0, $t1, $w, $padL, $padR) {
    return round($padL + ($t - $t0) / ($t1 - $t0) * ($w - $padL - $padR), 1);
};
$y = function ($p) use ($pMin, $range, $h, $padT, $padB) {
    return round($padT + (1 - ($p - $pMin) / $range) * ($h - $padT - $padB), 1);
};

$path = '';
foreach ($points as $i => $pt) {
    if ($i === 0) {
        $path .= 'M' . $x($pt[0]) . ' ' . $y($pt[1]);
    } else {
        $path .= ' H' . $x($pt[0]) . ' V' . $y($pt[1]);
    }
}
$area = $path . ' V' . ($h - $padB) . ' H' . $x($t0) . ' Z';
$last = end($points);
?>
<figure class="chart">
  <svg viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="กราฟราคาย้อนหลัง ต่ำสุด <?= e(baht(min($prices))) ?> สูงสุด <?= e(baht($pMax)) ?>">
    <line class="chart-grid" x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= $y(min($prices)) ?>" y2="<?= $y(min($prices)) ?>"/>
    <line class="chart-grid" x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= $y($pMax) ?>" y2="<?= $y($pMax) ?>"/>
    <path class="chart-area" d="<?= $area ?>"/>
    <path class="chart-line" d="<?= $path ?>"/>
    <circle class="chart-dot" cx="<?= $x($last[0]) ?>" cy="<?= $y($last[1]) ?>" r="4"/>
    <text class="chart-label" x="<?= $padL ?>" y="<?= $h - 6 ?>"><?= e(thai_date(date('Y-m-d', $t0))) ?></text>
    <text class="chart-label" x="<?= $w - $padR ?>" y="<?= $h - 6 ?>" text-anchor="end">วันนี้</text>
  </svg>
  <figcaption>
    <span>สูงสุด <strong><?= e(baht($pMax)) ?></strong></span>
    <span>ต่ำสุด <strong><?= e(baht(min($prices))) ?></strong></span>
  </figcaption>
</figure>
