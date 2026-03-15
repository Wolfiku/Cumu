<?php
/**
 * CUMU – backend/stream.php
 * Streams songs AND audiobooks with HTTP Range support.
 *
 * Songs:      /backend/stream.php?id=<song_id>
 * Audiobooks: /backend/stream.php?ab=<audiobook_id>
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) { http_response_code(401); exit; }

$db = getDB();

/* ── Resolve path ──────────────────────────────────────────────────── */
$songId = filter_input(INPUT_GET, 'id',  FILTER_VALIDATE_INT);
$abId   = filter_input(INPUT_GET, 'ab',  FILTER_VALIDATE_INT);

if ($songId) {
    $s = $db->prepare('SELECT path FROM songs WHERE id=? LIMIT 1');
    $s->execute([$songId]); $row = $s->fetch();
} elseif ($abId) {
    $s = $db->prepare('SELECT path FROM audiobooks WHERE id=? LIMIT 1');
    $s->execute([$abId]); $row = $s->fetch();
} else { http_response_code(400); exit; }

if (!$row) { http_response_code(404); exit; }

$musicDir = realpath(MUSIC_DIR);
$fullPath  = realpath($musicDir . '/' . $row['path']);
if (!$fullPath || strpos($fullPath, $musicDir) !== 0 || !is_file($fullPath)) {
    http_response_code(403); exit;
}

/* ── MIME ──────────────────────────────────────────────────────────── */
$mimes = [
    'mp3'  => 'audio/mpeg',
    'flac' => 'audio/flac',
    'ogg'  => 'audio/ogg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'm4b'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'opus' => 'audio/ogg; codecs=opus',
];
$ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = $mimes[$ext] ?? 'application/octet-stream';

/* ── Range streaming ───────────────────────────────────────────────── */
$size  = filesize($fullPath);
$start = 0;
$end   = $size - 1;

if (!empty($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
    $start = isset($m[1]) && $m[1] !== '' ? (int)$m[1] : 0;
    $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $size - 1;
    if ($start > $end || $end >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
} else {
    http_response_code(200);
}

header('Content-Type: '           . $mime);
header('Content-Length: '         . ($end - $start + 1));
header('Accept-Ranges: bytes');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

while (ob_get_level()) ob_end_clean();
$fp = fopen($fullPath, 'rb');
fseek($fp, $start);
$remaining = $end - $start + 1;
while (!feof($fp) && $remaining > 0) {
    $chunk = fread($fp, min(8192, $remaining));
    if (!$chunk) break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}
fclose($fp);
