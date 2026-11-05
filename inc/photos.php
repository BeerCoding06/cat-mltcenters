<?php

/**
 * Turn a downloaded photo into a WebP of exactly $w x $h (crop like object-fit: cover, $focusY = where the crop sits
 * from top 0 to bottom 1) with the site's warm tone, so photos from different sources look like one set next to the
 * cream-and-ginger design. With $fitTall, a photo much taller than the frame is shown whole over a blurred copy of
 * itself instead: nobody checks unattended downloads, and cropping a portrait shot is what cuts cats' heads off.
 * Needs GD with WebP (bundled in the Docker image). Returns false if the bytes are not an image.
 */
function photo_make(string $bytes, string $dest, int $w, int $h, float $focusY = 0.5, bool $fitTall = false): bool
{
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return false;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $out = imagecreatetruecolor($w, $h);
    $tall = $fitTall && $sw / $sh < ($w / $h) * 0.7;
    photo_cover($out, $src, $w, $h, $tall ? 0.5 : $focusY);
    if ($tall) {
        photo_backdrop($out);
        $fw = (int) round($sw * $h / $sh);
        imagecopyresampled($out, $src, (int) (($w - $fw) / 2), 0, 0, 0, $fw, $h, $sw, $sh);
    }
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

/** Fill $out with $src the way object-fit: cover does. */
function photo_cover($out, $src, int $w, int $h, float $focusY): void
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int) round($w / $scale);
    $ch = (int) round($h / $scale);
    $cx = (int) round(($sw - $cw) / 2);
    $cy = (int) round(max(0, min($sh - $ch, $sh * $focusY - $ch / 2)));
    imagecopyresampled($out, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch);
}

/** Blur and fade the picture in $img towards the site's cream, as a quiet backdrop for the whole photo. */
function photo_backdrop($img): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    // Shrink, blur, then grow back in two steps: one big jump up from a tiny image leaves visible blocks.
    $img2 = $img;
    foreach ([[24, 2], [6, 6]] as list($div, $passes)) {
        $tmp = imagecreatetruecolor(max(1, intdiv($w, $div)), max(1, intdiv($h, $div)));
        imagecopyresampled($tmp, $img2, 0, 0, 0, 0, imagesx($tmp), imagesy($tmp), imagesx($img2), imagesy($img2));
        for ($i = 0; $i < $passes; $i++) {
            imagefilter($tmp, IMG_FILTER_GAUSSIAN_BLUR);
        }
        if ($img2 !== $img) {
            imagedestroy($img2);
        }
        $img2 = $tmp;
    }
    imagecopyresampled($img, $img2, 0, 0, 0, 0, $w, $h, imagesx($img2), imagesy($img2));
    imagedestroy($img2);
    for ($i = 0; $i < 4; $i++) {
        imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
    }
    imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, 255, 246, 236, 50));
}

/** The house look: a little warmer, a little softer, a hint of the site's cream in the shadows. */
function photo_warm($img): void
{
    imagefilter($img, IMG_FILTER_COLORIZE, 14, 6, -10);
    imagefilter($img, IMG_FILTER_CONTRAST, 4);   // positive = slightly less contrast
    imagefilter($img, IMG_FILTER_BRIGHTNESS, 4);
}
