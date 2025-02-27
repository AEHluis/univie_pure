<?php

if (!defined('LF')) {
    define('LF', chr(10));
}

require_once __DIR__ . '/../.Build/vendor/autoload.php';

$GLOBALS['BE_USER'] = (object)[
    'uc' => ['lang' => 'de']
];
