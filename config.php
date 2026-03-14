<?php
if (defined('CUMU_ROOT')) return;
define('CUMU_ROOT', __DIR__);

(function () {
    $doc  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $proj = rtrim(str_replace('\\', '/', realpath(__DIR__)), '/');
    $base = ($doc && strpos($proj, $doc) === 0) ? substr($proj, strlen($doc)) : '';
    define('BASE_URL', rtrim($base, '/'));
})();

define('DB_PATH',     CUMU_ROOT . '/database/cumu.db');
define('MUSIC_DIR',   CUMU_ROOT . '/music');
define('COVERS_DIR',  CUMU_ROOT . '/covers');
define('UPLOAD_DIR',  CUMU_ROOT . '/uploads');
define('AUDIO_EXT',   ['mp3', 'flac', 'ogg', 'wav', 'm4a', 'aac', 'opus']);
define('MAX_UPLOAD',  100 * 1024 * 1024); // 100 MB per file
