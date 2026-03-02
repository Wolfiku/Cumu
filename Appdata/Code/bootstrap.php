<?php
// Sandbox: Nur der App-Ordner + /tmp erlaubt
ini_set('open_basedir', dirname(__DIR__) . ':/tmp/');

// Logging relativ zum App-Ordner
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');
