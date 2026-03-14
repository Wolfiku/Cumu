<?php
/**
 * CUMU – backend/stream.php
 *
 * Secure audio streaming endpoint.
 *
 * Usage:  backend/stream.php?id=<song_id>
 *
 * - Requires authentication (session)
 * - Validates song ID from database
 * - Resolves the real file path server-side (no direct URL access to /music)
 * - Supports HTTP Range requests for seeking
 *
 * NOTE: The /music directory should NOT be served publicly by your web server.
 * In Apache, add a .htaccess with "Deny from all" inside /music.
 * In Nginx, block the location with "deny all;".
 */

require_once __DIR__ . '/session.php';

// ── Authentication check ──────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

// ── Validate input ────────────────────────────────────────────────────────────
$songId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$songId || $songId < 1) {
    http_response_code(400);
    exit('Invalid song ID');
}

// ── Fetch song record from database ──────────────────────────────────────────
try {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, title, path FROM songs WHERE id = ? LIMIT 1');
    $stmt->execute([$songId]);
    $song = $stmt->fetch();
} catch (Exception $e) {
    error_log('[Cumu] Stream DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('Server error');
}

if (!$song) {
    http_response_code(404);
    exit('Song not found');
}

// ── Resolve and validate the file path ───────────────────────────────────────
// The path stored in DB is relative to the project root (e.g. "music/Artist/Album/song.mp3")
$projectRoot = realpath(__DIR__ . '/..');
$musicDir    = $projectRoot . '/music';
$rawPath     = $projectRoot . '/' . ltrim($song['path'], '/');
$realPath    = realpath($rawPath);

// Prevent directory traversal: resolved path must be inside /music
if ($realPath === false || strpos($realPath, $musicDir) !== 0) {
    error_log('[Cumu] Directory traversal attempt for song ID ' . $songId);
    http_response_code(403);
    exit('Forbidden');
}

if (!is_file($realPath) || !is_readable($realPath)) {
    http_response_code(404);
    exit('File not found');
}

// ── Determine MIME type ───────────────────────────────────────────────────────
$ext      = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap  = [
    'mp3'  => 'audio/mpeg',
    'ogg'  => 'audio/ogg',
    'flac' => 'audio/flac',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'opus' => 'audio/ogg; codecs=opus',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// ── HTTP Range support (enables seeking) ─────────────────────────────────────
$fileSize = filesize($realPath);
$start    = 0;
$end      = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
    $start = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
    $end   = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $fileSize - 1;

    if ($start > $end || $end >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
} else {
    http_response_code(200);
}

// ── Send headers ──────────────────────────────────────────────────────────────
header('Content-Type: '   . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Accept-Ranges: bytes');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Prevent the response from being included in frames
header('X-Frame-Options: DENY');

// Clean output buffer before streaming
while (ob_get_level()) {
    ob_end_clean();
}

// ── Stream the file ───────────────────────────────────────────────────────────
$fp     = fopen($realPath, 'rb');
$length = $end - $start + 1;
$chunk  = 8192; // 8 KB chunks

fseek($fp, $start);

$sent = 0;
while (!feof($fp) && $sent < $length) {
    $toRead = min($chunk, $length - $sent);
    $data   = fread($fp, $toRead);
    if ($data === false) break;
    echo $data;
    $sent += strlen($data);
    flush();
}

fclose($fp);
exit;
