<?php
/**
 * Hand-drawn SVG illustrations for the cat theme (original artwork, safe to use anywhere).
 * Colours come from CSS variables so the art follows the theme.
 */

function svg_logo(): string
{
    return <<<'SVG'
<svg viewBox="0 0 48 48" aria-hidden="true" class="ill-logo">
  <path d="M8 20 L10 5 L21 13 Q24 12.4 27 13 L38 5 L40 20 Q43 26 41 32 Q37 43 24 43 Q11 43 7 32 Q5 26 8 20Z" fill="var(--ginger)"/>
  <path d="M11.5 15 L12.5 9 L17 12.5Z M36.5 15 L35.5 9 L31 12.5Z" fill="var(--pink)"/>
  <path d="M19 15 q1.5 2.5 0 5 M24 14 q1.5 3 0 6 M29 15 q1.5 2.5 0 5" stroke="var(--ginger-dark)" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <ellipse cx="17.5" cy="27" rx="2.6" ry="3.4" fill="var(--ink)"/>
  <ellipse cx="30.5" cy="27" rx="2.6" ry="3.4" fill="var(--ink)"/>
  <circle cx="18.4" cy="25.8" r="1" fill="#fff"/><circle cx="31.4" cy="25.8" r="1" fill="#fff"/>
  <path d="M22.4 31.5 h3.2 l-1.6 1.8z" fill="var(--pink-deep)"/>
  <path d="M24 33.3 q-1.5 2.4 -3.6 1.4 M24 33.3 q1.5 2.4 3.6 1.4" stroke="var(--ink)" stroke-width="1.2" fill="none" stroke-linecap="round"/>
  <ellipse cx="13.5" cy="32" rx="2.6" ry="1.6" fill="var(--pink)" opacity=".7"/>
  <ellipse cx="34.5" cy="32" rx="2.6" ry="1.6" fill="var(--pink)" opacity=".7"/>
</svg>
SVG;
}

/*
 * Cats: frames from Freepik ("Flat design animation frames element collection" + "Hand drawn cat animation frames
 * element collection"), converted to assets/cat-sprites.svg — backgrounds removed and recoloured to the theme colour
 * variables, so a .breed-* class (or the colours the visitor picks in the cat studio) repaints them.
 * Frames: cw1-3 walk, cr1-3 run (facing right), cs1-8 sitting (cs2-3 yawn, cs6-8 dozing with a snot bubble).
 *
 * Stars: from "Cat, Sleeping Cat, Cartoon Cat" by VectorBy (Pixabay, Pixabay Content License),
 * https://pixabay.com/vectors/cat-6940169/ — recoloured.
 * Neither licence allows use as a logo, so the logo stays our own drawing (svg_logo).
 */

/** One cat frame from the sprite file, e.g. svg_cat_frame('cs1'). Drawn in a 320 x 150 box. */
function svg_cat_frame(string $frame, string $class = '', string $label = ''): string
{
    $a11y = $label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';
    return '<svg viewBox="0 0 320 150" class="' . e($class) . '"' . $a11y . '>'
        . '<use href="' . e(asset('cat-sprites.svg')) . '#' . e($frame) . '" width="320" height="150"/></svg>';
}

/** The six stars from the same artwork (used as sparkles), in a 760 x 240 box. */
function svg_geo_stars_parts(): string
{
    return <<<'SVG'
<g class="geo-stars">
    <path class="star star-1" d="M110.3,87.9 L73.2,84 L49,112.4 L41.2,75.9 L6.7,61.6 L39.1,42.9 L42,5.7 L69.7,30.7 L106,22 L90.8,56.1 Z"/>
    <path class="star star-2" d="M292.9,82.9 L276.2,79.1 L263.6,90.7 L262,73.7 L247.1,65.3 L262.9,58.5 L266.2,41.8 L277.5,54.6 L294.5,52.7 L285.7,67.4 Z"/>
    <path class="star star-3" d="M501.6,59 L478,57.9 L463.6,76.8 L457.3,53.9 L434.9,46.2 L454.8,33.1 L455.3,9.4 L473.8,24.2 L496.5,17.4 L488.1,39.6 Z"/>
    <path class="star star-4" d="M744.2,106.7 L708.6,102.1 L684.7,128.8 L678,93.6 L645.2,79.1 L676.7,61.9 L680.3,26.2 L706.4,50.8 L741.5,43.2 L726.2,75.7 Z"/>
    <path class="star star-5" d="M544.1,186.6 L524.9,179.5 L508.1,191.3 L508.9,170.8 L492.5,158.5 L512.3,153 L518.9,133.5 L530.3,150.6 L550.8,150.9 L538.1,167 Z"/>
    <path class="star star-6" d="M382.9,230.4 L366,222.1 L349.6,231.3 L352.3,212.6 L338.5,199.8 L357.1,196.7 L365,179.6 L373.7,196.3 L392.4,198.5 L379.2,212 Z"/>
</g>
SVG;
}

/** Sunny scene: a cat sitting on a cushion with sparkles and the sun. Used in the homepage hero and the 404 page. */
function svg_cat_scene(string $breed = 'ginger-tabby', string $frame = 'cs1', string $label = 'แมวส้มนั่งยิ้ม'): string
{
    return '<div class="cat-scene breed-' . e($breed) . '">'
        . '<svg viewBox="0 0 560 440" aria-hidden="true" class="cat-scene-bg">'
        . '<circle cx="280" cy="230" r="205" fill="var(--pink-soft)"/>'
        . '<g class="sun"><g class="sun-rays" stroke="var(--sun)" stroke-width="7" stroke-linecap="round">'
        . '<path d="M452 34v-16M452 166v16M386 100h-16M518 100h16M405 53l-11-11M499 147l11 11M405 147l-11 11M499 53l11-11"/></g>'
        . '<circle cx="452" cy="100" r="38" fill="var(--sun)"/></g>'
        . '<g transform="translate(70 40) scale(.42)">' . svg_geo_stars_parts() . '</g>'
        . '<rect x="70" y="352" width="420" height="58" rx="29" fill="var(--mint)"/>'
        . '<rect x="88" y="365" width="384" height="32" rx="16" fill="none" stroke="var(--mint-deep)" stroke-width="3" stroke-dasharray="8 8" opacity=".55"/>'
        . '</svg>'
        . svg_cat_frame($frame, 'cat-scene-cat', $label)
        . '</div>';
}

/** Cat that sits on the footer edge. */
function svg_footer_cat(): string
{
    return '<div class="breed-ginger-tabby">' . svg_cat_frame('cs8', 'ill-footer-cat') . '</div>';
}

/** Shown when a product has no photo. */
function svg_placeholder_cat(): string
{
    return <<<'SVG'
<svg viewBox="0 0 64 64" aria-hidden="true" class="ill-placeholder">
  <path d="M12 26 L14 8 L26 18 Q32 17 38 18 L50 8 L52 26 Q56 34 52 42 Q46 54 32 54 Q18 54 12 42 Q8 34 12 26Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
  <circle cx="24" cy="34" r="2.8" fill="currentColor"/><circle cx="40" cy="34" r="2.8" fill="currentColor"/>
  <path d="M29 41 h6 l-3 3z M32 44 q-3 4 -6 2 M32 44 q3 4 6 2" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
</svg>
SVG;
}

function svg_paw(string $class = 'ill-paw'): string
{
    return '<svg viewBox="0 0 32 32" aria-hidden="true" class="' . e($class) . '"><ellipse cx="16" cy="21" rx="7.5" ry="6.5" fill="currentColor"/>'
        . '<ellipse cx="7" cy="13" rx="3" ry="3.8" fill="currentColor"/><ellipse cx="12.5" cy="7.5" rx="3" ry="3.8" fill="currentColor"/>'
        . '<ellipse cx="19.5" cy="7.5" rx="3" ry="3.8" fill="currentColor"/><ellipse cx="25" cy="13" rx="3" ry="3.8" fill="currentColor"/></svg>';
}

/** Small icon for a product category, picked from words in its name. */
function svg_category_icon(string $category): string
{
    $icons = [
        'เปียก' => '<rect x="10" y="12" width="28" height="26" rx="5" fill="var(--mint)"/><rect x="10" y="12" width="28" height="7" rx="3" fill="var(--mint-deep)"/><path d="M17 28 q7 -7 14 0 q-7 7 -14 0z M31 28 l4 -3 v6z" fill="var(--ginger)"/>',
        'ทราย'  => '<path d="M6 22 h36 l-4 18 h-28z" fill="var(--sky)"/><path d="M6 22 h36" stroke="var(--sky-deep)" stroke-width="4" stroke-linecap="round"/><circle cx="17" cy="19" r="3" fill="var(--sand)"/><circle cx="24" cy="17" r="3.4" fill="var(--sand)"/><circle cx="31" cy="19" r="3" fill="var(--sand)"/>',
        'ขนม'   => '<path d="M8 24 q12 -14 26 0 q-12 14 -26 0z" fill="var(--sky)"/><path d="M34 24 l8 -7 v14z" fill="var(--sky-deep)"/><circle cx="15" cy="22" r="2" fill="var(--ink)"/>',
        'คอนโด' => '<circle cx="24" cy="26" r="14" fill="var(--pink)"/><path d="M12 22 q12 -6 24 4 M14 32 q10 -10 22 -2 M20 13 q-2 14 8 26" stroke="var(--pink-deep)" stroke-width="2.4" fill="none"/><path d="M36 36 q6 4 4 8" stroke="var(--pink-deep)" stroke-width="2.4" fill="none" stroke-linecap="round"/>',
        'ของเล่น' => '<circle cx="24" cy="26" r="14" fill="var(--pink)"/><path d="M12 22 q12 -6 24 4 M14 32 q10 -10 22 -2" stroke="var(--pink-deep)" stroke-width="2.4" fill="none"/>',
        'อุปกรณ์' => '<path d="M10 30 h28 v6 q0 4 -4 4 h-20 q-4 0 -4 -4z" fill="var(--mint-deep)"/><path d="M14 30 q10 -18 20 0" fill="var(--sky)"/><path d="M24 12 q-4 6 0 9 q4 -3 0 -9z" fill="var(--sky-deep)"/>',
        'อาหาร' => '<path d="M6 26 h36 q-2 14 -18 14 q-16 0 -18 -14z" fill="var(--ginger)"/><path d="M6 26 h36" stroke="var(--ginger-dark)" stroke-width="3" stroke-linecap="round"/><circle cx="16" cy="22" r="4" fill="var(--sand)"/><circle cx="24" cy="20" r="4.4" fill="var(--sand)"/><circle cx="32" cy="22" r="4" fill="var(--sand)"/>',
    ];
    foreach ($icons as $word => $art) {
        if (mb_strpos($category, $word) !== false) {
            return '<svg viewBox="0 0 48 48" aria-hidden="true" class="ill-cat-icon">' . $art . '</svg>';
        }
    }
    return svg_paw('ill-cat-icon ill-cat-icon-paw');
}
