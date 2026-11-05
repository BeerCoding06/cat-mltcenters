<?php

/**
 * Turn a downloaded photo into a WebP of exactly $w x $h (centre crop, like object-fit: cover) with the site's warm
 * tone, so photos from different sources look like one set next to the cream-and-ginger design.
 * Needs GD with WebP (bundled in the Docker image). Returns false if the bytes are not an image.
 */
function photo_make(string $bytes, string $dest, int $w, int $h, float $focusY = 0.5): bool
{
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return false;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int) round($w / $scale);
    $ch = (int) round($h / $scale);
    $cx = (int) round(($sw - $cw) / 2);
    if ($sh > $sw * 1.1) {
        $focusY = min($focusY, 0.33);  // portrait shots: the face is near the top, keep it in the crop
    }
    $cy = (int) round(max(0, min($sh - $ch, $sh * $focusY - $ch / 2)));
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch);
    imagedestroy($src);
    photo_warm($out);
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $ok = imagewebp($out, $dest, 80);
    imagedestroy($out);
    return $ok;
}

/** The house look: a little warmer, a little softer, a hint of the site's cream in the shadows. */
function photo_warm($img): void
{
    imagefilter($img, IMG_FILTER_COLORIZE, 14, 6, -10);
    imagefilter($img, IMG_FILTER_CONTRAST, 4);   // positive = slightly less contrast
    imagefilter($img, IMG_FILTER_BRIGHTNESS, 4);
}
