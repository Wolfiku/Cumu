<?php
ob_start();

/**
 * CUMU – backend/upload_api.php
 * Handles single, album, and audiobook uploads.
 * POST field 'type' = 'single' | 'album' | 'audiobook'
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/id3_reader.php';

if (!isLoggedIn())   jsonErr('Unauthorized', 401);
if (!isPublisher())  jsonErr('Publisher or Admin required.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST only.', 405);

$type = trim($_POST['type'] ?? 'single');
$db   = getDB();

/* ── Helpers ──────────────────────────────────────────────────────────── */
function sanitizeName(string $n): string {
    $n = preg_replace('/[\/\\\\:*?"<>|]/', '_', $n);
    $n = trim($n, ". \t");
    return $n ?: 'Unknown';
}

function savePostedCover(string $field = 'cover'): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f    = $_FILES[$field];
    $info = @getimagesize($f['tmp_name']);
    if (!$info) return null;
    $mime = $info['mime'];
    $ext  = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);
    $hash = md5_file($f['tmp_name']) . '_up';
    $dst  = COVERS_DIR . '/' . $hash . '.' . $ext;
    if (!file_exists($dst)) move_uploaded_file($f['tmp_name'], $dst);
    return 'covers/' . $hash . '.' . $ext;
}

function upsertArtist(PDO $db, string $name): int {
    $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$name]);
    $s = $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1'); $s->execute([$name]);
    return (int)$s->fetchColumn();
}

function upsertAlbum(PDO $db, int $artistId, string $name, ?string $cover, ?string $genre, ?int $year): int {
    $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name) VALUES(?,?)')->execute([$artistId,$name]);
    $s = $db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1'); $s->execute([$artistId,$name]);
    $id = (int)$s->fetchColumn();
    if ($cover)  $db->prepare('UPDATE albums SET cover=? WHERE id=? AND cover IS NULL')->execute([$cover,$id]);
    if ($genre)  $db->prepare('UPDATE albums SET genre=? WHERE id=?')->execute([$genre,$id]);
    if ($year)   $db->prepare('UPDATE albums SET year=?  WHERE id=?')->execute([$year,$id]);
    return $id;
}

/* ════════════════════════════════════════════════
   SINGLE UPLOAD
   ════════════════════════════════════════════════ */
if ($type === 'single') {
    if (empty($_FILES['files'])) jsonErr('No file uploaded.');
    $files  = $_FILES['files'];
    $count  = is_array($files['name']) ? count($files['name']) : 1;
    $artist = trim($_POST['artist'] ?? '') ?: 'Unknown Artist';
    $manualTitle = trim($_POST['title'] ?? '');
    $coverPath = savePostedCover('cover');

    $uploaded = []; $errors = [];

    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
        $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

        if ($err !== UPLOAD_ERR_OK)  { $errors[] = "$name: Upload error $err"; continue; }
        if ($size > MAX_UPLOAD)       { $errors[] = "$name: File too large"; continue; }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, AUDIO_EXT, true)) { $errors[] = "$name: Unsupported format"; continue; }

        $meta     = readID3($tmp);
        $title    = $manualTitle ?: ($meta['title'] ?: preg_replace('/^\d+[\s.\-_]+/', '', pathinfo($name, PATHINFO_FILENAME)) ?: pathinfo($name, PATHINFO_FILENAME));
        $artName  = $meta['artist'] ?: $artist;
        $albName  = $meta['album']  ?: $title; // single → album = song title
        $coverFin = $coverPath ?: saveCoverArt($meta);
        $duration = (int)($meta['duration'] ?? 0);

        $safeArt  = sanitizeName($artName);
        $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)) . '.' . $ext;
        $targetDir = MUSIC_DIR . '/' . $safeArt . '/Singles';
        if (!is_dir($targetDir)) mkdir($targetDir, 0750, true);
        $dest = $targetDir . '/' . $safeFile;
        if (file_exists($dest)) { $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)).'_'.time().'.'.$ext; $dest=$targetDir.'/'.$safeFile; }
        if (!move_uploaded_file($tmp, $dest)) { $errors[] = "$name: Could not save"; continue; }

        $relPath = $safeArt . '/Singles/' . $safeFile;
        try {
            $db->beginTransaction();
            $artistId = upsertArtist($db, $artName);
            $albumId  = upsertAlbum($db, $artistId, $albName, $coverFin, null, null);
            if ($coverFin) $db->prepare('UPDATE artists SET image=? WHERE id=? AND image IS NULL')->execute([$coverFin,$artistId]);
            $db->prepare('INSERT OR IGNORE INTO songs(artist_id,album_id,title,path,duration,type) VALUES(?,?,?,?,?,?)')->execute([$artistId,$albumId,$title,$relPath,$duration,'single']);
            $db->commit();
            $uploaded[] = $artName . ' – ' . $title;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            @unlink($dest); $errors[] = "$name: DB error – " . $e->getMessage();
        }
    }
    jsonOk(['uploaded' => $uploaded, 'errors' => $errors]);
}

/* ════════════════════════════════════════════════
   ALBUM UPLOAD
   ════════════════════════════════════════════════ */
if ($type === 'album') {
    if (empty($_FILES['files'])) jsonErr('No files uploaded.');
    $albumName = trim($_POST['album']  ?? '') ?: 'Unknown Album';
    $artistName= trim($_POST['artist'] ?? '') ?: 'Unknown Artist';
    $genre     = trim($_POST['genre']  ?? '') ?: null;
    $year      = (int)($_POST['year']  ?? 0) ?: null;
    $coverPath = savePostedCover('cover');

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    $uploaded = []; $errors = [];

    // Pre-create artist + album
    $db->beginTransaction();
    try {
        $artistId = upsertArtist($db, $artistName);
        $albumId  = upsertAlbum($db, $artistId, $albumName, $coverPath, $genre, $year);
        if ($coverPath) $db->prepare('UPDATE artists SET image=? WHERE id=? AND image IS NULL')->execute([$coverPath,$artistId]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonErr('Could not create album: ' . $e->getMessage());
    }

    $safeArt = sanitizeName($artistName);
    $safeAlb = sanitizeName($albumName);
    $targetDir = MUSIC_DIR . '/' . $safeArt . '/' . $safeAlb;
    if (!is_dir($targetDir)) mkdir($targetDir, 0750, true);

    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
        $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

        if ($err !== UPLOAD_ERR_OK)  { $errors[] = "$name: Upload error"; continue; }
        if ($size > MAX_UPLOAD)       { $errors[] = "$name: File too large"; continue; }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, AUDIO_EXT, true)) { $errors[] = "$name: Unsupported"; continue; }

        $meta  = readID3($tmp);
        $title = $meta['title'] ?: (preg_replace('/^\d+[\s.\-_]+/', '', pathinfo($name, PATHINFO_FILENAME)) ?: pathinfo($name, PATHINFO_FILENAME));
        $track = $meta['track'] ?: ($i + 1);
        $duration = (int)($meta['duration'] ?? 0);

        $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)) . '.' . $ext;
        $dest = $targetDir . '/' . $safeFile;
        if (file_exists($dest)) { $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)).'_'.time().'.'.$ext; $dest=$targetDir.'/'.$safeFile; }
        if (!move_uploaded_file($tmp, $dest)) { $errors[] = "$name: Could not save"; continue; }

        $relPath = $safeArt . '/' . $safeAlb . '/' . $safeFile;
        try {
            $db->prepare('INSERT OR IGNORE INTO songs(artist_id,album_id,title,path,duration,track_num,type) VALUES(?,?,?,?,?,?,?)')->execute([$artistId,$albumId,$title,$relPath,$duration,$track,'song']);
            $uploaded[] = $i+1 . '. ' . $title;
        } catch (Exception $e) {
            @unlink($dest); $errors[] = "$name: DB – " . $e->getMessage();
        }
    }
    jsonOk(['uploaded' => $uploaded, 'errors' => $errors]);
}

/* ════════════════════════════════════════════════
   AUDIOBOOK UPLOAD
   ════════════════════════════════════════════════ */
if ($type === 'audiobook') {
    if (empty($_FILES['files'])) jsonErr('No file uploaded.');
    $f = $_FILES['files'];
    // Normalize single-file
    $name  = is_array($f['name'])     ? $f['name'][0]     : $f['name'];
    $tmp   = is_array($f['tmp_name']) ? $f['tmp_name'][0] : $f['tmp_name'];
    $size  = is_array($f['size'])     ? $f['size'][0]     : $f['size'];
    $err   = is_array($f['error'])    ? $f['error'][0]    : $f['error'];

    if ($err !== UPLOAD_ERR_OK)       jsonErr("Upload error $err");
    if ($size > 500 * 1024 * 1024)    jsonErr('File too large (max 500 MB)');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, array_merge(AUDIO_EXT, ['m4b']), true)) jsonErr('Unsupported format');

    $title    = trim($_POST['title']    ?? '') ?: pathinfo($name, PATHINFO_FILENAME);
    $seriesName= trim($_POST['series']  ?? '');
    $narrator = trim($_POST['narrator'] ?? '');
    $coverPath = savePostedCover('cover');

    // Save file
    $abDir = MUSIC_DIR . '/_audiobooks';
    if (!is_dir($abDir)) mkdir($abDir, 0750, true);
    $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)) . '.' . $ext;
    $dest = $abDir . '/' . $safeFile;
    if (file_exists($dest)) { $safeFile = sanitizeName(pathinfo($name, PATHINFO_FILENAME)).'_'.time().'.'.$ext; $dest=$abDir.'/'.$safeFile; }
    if (!move_uploaded_file($tmp, $dest)) jsonErr('Could not save file.');

    $relPath = '_audiobooks/' . $safeFile;

    // Estimate duration
    $meta     = readID3($dest);
    $duration = (int)($meta['duration'] ?? 0);
    if (!$coverPath) $coverPath = saveCoverArt($meta);

    try {
        $db->beginTransaction();
        $seriesId = null;
        if ($seriesName !== '') {
            $db->prepare('INSERT OR IGNORE INTO series(name) VALUES(?)')->execute([$seriesName]);
            $sr = $db->prepare('SELECT id FROM series WHERE name=? LIMIT 1'); $sr->execute([$seriesName]);
            $seriesId = (int)$sr->fetchColumn();
            if ($coverPath) $db->prepare('UPDATE series SET cover=? WHERE id=? AND cover IS NULL')->execute([$coverPath,$seriesId]);
        }
        $db->prepare('INSERT OR IGNORE INTO audiobooks(series_id,title,narrator,path,cover,duration) VALUES(?,?,?,?,?,?)')->execute([$seriesId,$title,$narrator,$relPath,$coverPath,$duration]);
        $db->commit();
        jsonOk(['uploaded' => [$title], 'errors' => []]);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        @unlink($dest);
        jsonErr('DB error: ' . $e->getMessage());
    }
}

jsonErr('Unknown upload type.');
