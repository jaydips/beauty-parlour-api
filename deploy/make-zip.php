<?php

/**
 * deploy/make-zip.php <parent-dir> <top-folder> <out.zip> [--flat]
 *
 * Builds the release zip with explicit Unix permissions (directories 0755,
 * files 0644). Windows tools (tar.exe, Compress-Archive) store no Unix modes,
 * and cPanel's unzip then creates world-writable 0777/0666 entries.
 * Used by deploy/build-release.ps1 and deploy/deploy-cpanel.sh; needs the php "zip" extension.
 *
 * With --flat the entries are stored without the top folder (used for the
 * document-root files, which are extracted straight into the docroot).
 */

declare(strict_types=1);

$flat = in_array('--flat', $argv, true);
$argv = array_values(array_filter($argv, static fn (string $a): bool => $a !== '--flat'));

[$script, $parent, $top, $out] = $argv + [null, null, null, null];

if ($parent === null || $top === null || $out === null) {
    fwrite(STDERR, "usage: php make-zip.php <parent-dir> <top-folder> <out.zip>\n");
    exit(2);
}

$root = rtrim($parent, '\\/') . DIRECTORY_SEPARATOR . $top;

if (! is_dir($root)) {
    fwrite(STDERR, "not a directory: {$root}\n");
    exit(2);
}

$zip = new ZipArchive();

if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "cannot create {$out}\n");
    exit(1);
}

$dirMode  = 040755 << 16;  // drwxr-xr-x
$fileMode = 0100644 << 16; // -rw-r--r--

if (! $flat) {
    $zip->addEmptyDir($top);
    $zip->setExternalAttributesName($top . '/', ZipArchive::OPSYS_UNIX, $dirMode);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

$dirs = $files = 0;

foreach ($iterator as $path => $info) {
    $relative = ($flat ? '' : $top . '/') . str_replace('\\', '/', substr($path, strlen($root) + 1));

    if ($info->isDir()) {
        $zip->addEmptyDir($relative);
        $zip->setExternalAttributesName($relative . '/', ZipArchive::OPSYS_UNIX, $dirMode);
        $dirs++;
    } else {
        $zip->addFile($path, $relative);
        $zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, $fileMode);
        $files++;
    }
}

$zip->close();

printf("zip written: %s (%d dirs, %d files, %.2f MB)\n", $out, $dirs, $files, filesize($out) / 1048576);
