<?php
/**
 * CUMU – backend/upload_api.php
 * Handles audio file uploads via multipart POST.
 * Saves files to /music/<Artist>/<Album>/ and triggers indexing.
 *
 * POST fields:
 *   files[]    – one or more audio files
 *   artist     – artist name (default: "Unknown Artist")
 *   album      – album name  (default: "Unknown Album")
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) jsonErr('Unauthorized', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST only.', 405);
if (empty($_FILES['files'])) jsonErr('No files uploaded.');

$artistRaw = trim($_POST['artist'] ?? 'Unknown Artist') ?: 'Unknown Artist';
$albumRaw  = trim($_POST['album']  ?? 'Unknown Album')  ?: 'Unknown Album';

// Sanitize folder names (strip path separators and dangerous chars)
function sanitizeName(string $n): string {
    $n = preg_replace('/[\/\\\\:*?"<>|]/', '', $n);
    $n = trim($n, ". \t\n\r");
    return $n ?: 'Unknown';
}

$artist = sanitizeName($artistRaw);
$album  = sanitizeName($albumRaw);

// Build target directory
$targetDir = MUSIC_DIR . '/' . $artist . '/' . $album;
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0750, true)) jsonErr('Could not create directory.', 500);
}

$ext_allowed = AUDIO_EXT;
$uploaded    = [];
$errors      = [];

// Normalize $_FILES['files'] to array format
$files = $_FILES['files'];
$count = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $count; $i++) {
    $name  = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
    $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $size  = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
    $err   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

    if ($err !== UPLOAD_ERR_OK) { $errors[] = "$name: Upload error code $err"; continue; }
    if ($size > MAX_UPLOAD)     { $errors[] = "$name: File too large (max 100 MB)"; continue; }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_allowed, true)) { $errors[] = "$name: Unsupported format"; continue; }

    // Sanitize filename
    $safeName = sanitizeName(pathinfo($name, PATHINFO_FILENAME)) . '.' . $ext;
    $destPath = $targetDir . '/' . $safeName;

    // Avoid overwrite
    if (file_exists($destPath)) {
        $safeName = sanitizeName(pathinfo($name, PATHINFO_FILENAME)) . '_' . time() . '.' . $ext;
        $destPath = $targetDir . '/' . $safeName;
    }

    if (!move_uploaded_file($tmp, $destPath)) { $errors[] = "$name: Could not save file"; continue; }

    // Index immediately
    $relPath = $artist . '/' . $album . '/' . $safeName;
    $title   = preg_replace('/^\d+[\s.\-_]+/', '', pathinfo($safeName, PATHINFO_FILENAME)) ?: pathinfo($safeName, PATHINFO_FILENAME);

    try {
        $db = getDB();
        $db->beginTransaction();

        $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artist]);
        $ar = $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1'); $ar->execute([$artist]);
        $artistId = (int)$ar->fetchColumn();

        $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name) VALUES(?,?)')->execute([$artistId,$album]);
        $alb = $db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1'); $alb->execute([$artistId,$album]);
        $albumId = (int)$alb->fetchColumn();

        $db->prepare('INSERT OR IGNORE INTO songs(artist_id,album_id,title,path) VALUES(?,?,?,?)')->execute([$artistId,$albumId,$title,$relPath]);
        $db->commit();

        $uploaded[] = ['name'=>$safeName,'artist'=>$artist,'album'=>$album,'path'=>$relPath];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = "$name: DB error – " . $e->getMessage();
        // Remove file if DB failed
        @unlink($destPath);
    }
}

jsonOk(['uploaded' => $uploaded, 'errors' => $errors]);
