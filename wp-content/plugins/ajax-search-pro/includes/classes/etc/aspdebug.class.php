<?php
/* Prevent direct access */
defined('ABSPATH') or die("You can't access this file directly.");

class aspDebug {

    private static $procesStart = array();

    public static function start($process = 'default') {
        if (ASP_DEBUG != 1) return;

        print $process .' START
';
        self::$procesStart[$process] = microtime(true);
    }

    public static function stop($process = 'default') {
        if (ASP_DEBUG != 1) return;
        print $process. " END | runtime: ".number_format(microtime(true) - self::$procesStart[$process], 3, '.', '').'s
';
    }
}