<?php

/**
 * deploy/make-icons.php - generates public/assets/icon-192.png and icon-512.png with GD
 * (rose square, white circle, rose dot). Replace with the parlour's real logo any time.
 *
 *   php deploy/make-icons.php
 */

declare(strict_types=1);

$dir = dirname(__DIR__) . '/public/assets';

foreach ([192, 512] as $size) {
    $img = imagecreatetruecolor($size, $size);
    imageantialias($img, true);
    $rose  = imagecolorallocate($img, 178, 58, 115);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size, $size, $rose);
    $c = (int) ($size / 2);
    imagefilledellipse($img, $c, $c, (int) ($size * 0.62), (int) ($size * 0.62), $white);
    imagefilledellipse($img, $c, $c, (int) ($size * 0.24), (int) ($size * 0.24), $rose);
    $file = sprintf('%s/icon-%d.png', $dir, $size);
    imagepng($img, $file, 6);
    imagedestroy($img);
    printf("wrote %s (%d bytes)\n", $file, filesize($file));
}
