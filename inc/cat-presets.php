<?php
/**
 * Cat colour presets for server-rendered cats (breed pages). Same data and colour logic as PRESETS / paint()
 * in assets/pet-cats.js — keep the two in step when adding a preset.
 */

const CAT_PRESETS = [
    'ginger-tabby' => ['base' => '#f6a65a', 'accent' => '#c8692a', 'pattern' => 'tabby', 'eye' => '#8cc63f', 'collar' => '#e2768c'],
    'silver-tabby' => ['base' => '#b7c0c9', 'accent' => '#5d6874', 'pattern' => 'tabby', 'eye' => '#7cc36b', 'collar' => '#6aa6dc'],
    'black'        => ['base' => '#3a3433', 'accent' => '#262120', 'pattern' => 'solid', 'eye' => '#ffd166', 'collar' => '#e2768c'],
    'khao-manee'   => ['base' => '#fbf7f1', 'accent' => '#e8ddd0', 'pattern' => 'solid', 'eye' => '#6aa6dc', 'collar' => '#f2c14e'],
    'korat'        => ['base' => '#8e9aa6', 'accent' => '#6f7b87', 'pattern' => 'solid', 'eye' => '#7cc36b', 'collar' => '#f9c1cc'],
    'suphalak'     => ['base' => '#a0623d', 'accent' => '#7f4a2c', 'pattern' => 'solid', 'eye' => '#f2c14e', 'collar' => '#5bb38a'],
    'siamese'      => ['base' => '#f3e6d0', 'accent' => '#5a3d30', 'pattern' => 'points', 'eye' => '#6aa6dc', 'collar' => '#e2768c'],
    'calico'       => ['base' => '#f0974f', 'accent' => '#3a3433', 'pattern' => 'calico', 'eye' => '#f2b134', 'collar' => '#5bb38a'],
    'tuxedo'       => ['base' => '#2f2a2a', 'accent' => '#1f1b1b', 'pattern' => 'socks', 'eye' => '#9bd36a', 'collar' => '#e2768c'],
    'cream'        => ['base' => '#f1dcc0', 'accent' => '#d8b48a', 'pattern' => 'solid', 'eye' => '#e8a23b', 'collar' => '#6aa6dc'],
];

function cat_hex_mix(string $a, string $b, float $t): string
{
    $x = sscanf($a, '#%02x%02x%02x');
    $y = sscanf($b, '#%02x%02x%02x');
    $out = '#';
    for ($i = 0; $i < 3; $i++) {
        $out .= sprintf('%02x', (int) round($x[$i] + ($y[$i] - $x[$i]) * $t));
    }
    return $out;
}

/** CSS custom properties (as an inline style string) that paint the cat frames in a preset's colours. */
function cat_paint_style(string $preset): string
{
    $c = CAT_PRESETS[$preset] ?? CAT_PRESETS['ginger-tabby'];
    $base = $c['base'];
    $v = ['--ginger' => $base, '--cat-light' => cat_hex_mix($base, '#ffffff', .3), '--cat-mid' => cat_hex_mix($base, '#000000', .18),
          '--cat-shade' => cat_hex_mix($base, '#000000', .12), '--paw' => $base];
    if ($c['pattern'] === 'tabby') {
        $v['--cat-mid'] = $c['accent'];
        $v['--paw'] = cat_hex_mix($base, '#ffffff', .6);
    } elseif ($c['pattern'] === 'socks') {
        $v['--cat-light'] = '#fffaf3';
        $v['--cat-mid'] = cat_hex_mix($base, '#000000', .2);
        $v['--paw'] = '#fffaf3';
    } elseif ($c['pattern'] === 'points') {
        $v['--cat-light'] = cat_hex_mix($base, '#ffffff', .4);
        $v['--cat-mid'] = $c['accent'];
        $v['--cat-shade'] = cat_hex_mix($base, '#000000', .1);
        $v['--paw'] = $c['accent'];
    } elseif ($c['pattern'] === 'calico') {
        $v['--ginger'] = '#fffaf3';
        $v['--cat-light'] = $base;
        $v['--cat-mid'] = $c['accent'];
        $v['--cat-shade'] = '#ece0d0';
        $v['--paw'] = '#fffaf3';
    }
    $v['--eye'] = $c['eye'];
    $v['--collar'] = $c['collar'];
    $rgb = sscanf($v['--ginger'], '#%02x%02x%02x');
    $v['--ink'] = (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255 < 0.3 ? '#d8ccc2' : '#4f3b2c';

    $style = '';
    foreach ($v as $k => $val) {
        $style .= $k . ':' . $val . ';';
    }
    return $style;
}

/** A sitting cat in a preset's colours, cropped to the cat. */
function svg_breed_cat(string $preset, string $class = '', string $frame = 'cs1'): string
{
    return '<svg viewBox="88 6 164 144" class="' . e($class) . '" style="' . e(cat_paint_style($preset)) . '" aria-hidden="true">'
        . '<use href="' . e(asset('cat-sprites.svg')) . '#' . e($frame) . '" width="320" height="150"/></svg>';
}
