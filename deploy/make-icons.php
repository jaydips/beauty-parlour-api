<?php

/**
 * deploy/make-icons.php - regenerates the PNG icons used by the web manifest
 * (public/assets/icon-192.png, icon-512.png, icon-maskable-512.png).
 * A seven-dot flower on the brand colour, drawn with GD so no design tool is needed.
 *
 *   php deploy/make-icons.php
 */

declare(strict_types=1);

$out = dirname(__DIR__) . '/public/assets/';

if (! is_dir($out) && ! mkdir($out, 0755, true)) {
    fwrite(STDERR, "cannot create {$out}\n");
    exit(1);
}

/**
 * @param bool $maskable full-bleed background (Android masks it), otherwise a rounded square with transparent corners
 */
function flowerIcon(int $size, bool $maskable): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagealphablending($img, true);

    $bg    = imagecolorallocate($img, 180, 35, 107);  // #b4236b
    $white = imagecolorallocate($img, 255, 255, 255);
    $gold  = imagecolorallocate($img, 255, 214, 102);

    if ($maskable) {
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    } else {
        $r = (int) round($size * 0.22);
        imagefilledrectangle($img, $r, 0, $size - $r, $size, $bg);
        imagefilledrectangle($img, 0, $r, $size, $size - $r, $bg);

        foreach ([[$r, $r], [$size - $r, $r], [$r, $size - $r], [$size - $r, $size - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $bg);
        }
    }

    // petals: six white dots on a ring, a golden centre (maskable icons keep content inside the safe zone)
    $scale  = $maskable ? 0.78 : 1.0;
    $cx     = $cy = $size / 2;
    $ring   = $size * 0.27 * $scale;
    $petal  = $size * 0.20 * $scale;
    $centre = $size * 0.19 * $scale;

    for ($i = 0; $i < 6; $i++) {
        $angle = deg2rad(90 + $i * 60);
        imagefilledellipse($img, (int) round($cx + cos($angle) * $ring), (int) round($cy - sin($angle) * $ring), (int) round($petal), (int) round($petal), $white);
    }

    imagefilledellipse($img, (int) $cx, (int) $cy, (int) round($centre), (int) round($centre), $gold);

    return $img;
}

foreach (['icon-192.png' => [192, false], 'icon-512.png' => [512, false], 'icon-maskable-512.png' => [512, true]] as $file => [$size, $maskable]) {
    $img = flowerIcon($size, $maskable);
    imagepng($img, $out . $file, 9);
    imagedestroy($img);
    printf("%s (%d bytes)\n", $file, filesize($out . $file));
}
