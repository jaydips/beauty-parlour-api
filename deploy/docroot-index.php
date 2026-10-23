<?php

/*
 * Front controller for the cPanel DOCUMENT ROOT.
 *
 * On this hosting every document root must be inside public_html, while the
 * application lives in /home/<cpanel-user>/beauty-parlour-api/. Copy the files
 * from public/ into the document root and REPLACE the copied index.php with this
 * one. The only difference from public/index.php is the Paths line below.
 *
 * Adjust the relative path to match the document root depth:
 *   public_html/<hostname>/          ->  '../../beauty-parlour-api/app/Config/Paths.php'
 *   public_html/<hostname>/api/      ->  '../../../beauty-parlour-api/app/Config/Paths.php'
 *   public_html/                     ->  '../beauty-parlour-api/app/Config/Paths.php'
 */

use CodeIgniter\Boot;
use Config\Paths;

$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// >>> The one line that differs from public/index.php <<<
require FCPATH . '../../beauty-parlour-api/app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
