<?php
// Initialise the tiqr-server-library autoloader

$tiqr_dir = __DIR__ . '/..';
$vendor_dir = __DIR__ . '/../../../vendor';

require_once $tiqr_dir. '/Tiqr/AutoLoader.php';

$autoloader = \Tiqr_AutoLoader::getInstance([
    'tiqr.path' => $tiqr_dir,
    'phpqrcode.path' => $vendor_dir . '/kairos/phpqrcode',
    'zend.path' => $vendor_dir . '/zendframework/zendframework1/library'
]);
$autoloader->setIncludePath();