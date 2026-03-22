<?php
ob_start();

/**
 * CUMU – backend/meta_api.php
 * Admin/Publisher: edit song, album, artist metadata + covers + featured flag.
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) jsonErr('Unauthorized', 401);
if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);

$db = getDB();

// Support both JSON body and multipart form
$isMultipart = !empty($_FILES);
if ($isMultipart) {
    $post = $_POST;
} else {
    $body = file_get_contents('php://input');
    $post = json_decode($body, true) ?: [];
}
$action = $post['action'] ?? '';

/* Helper: save uploaded image to covers/, return relative path */
function saveUploadedImage(string $fileKey): ?string {
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$fileKey];
    if ($file['size'] > 8 * 1024 * 1024) jsonErr('Image too large (max 8 MB).');
    $info = @getimagesize($file['tmp_name']);
    if (!$info) jsonErr('Not a valid image.');
    $mime = $info['mime'];
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) jsonErr('Only JPEG, PNG, WebP allowed.');
    $ext = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);
    $hash = md5_file($file['tmp_name']) . '_up';
    $dst  = COVERS_DIR . '/' . $hash . '.' . $ext;
    if (!file_exists($dst) && !move_uploaded_file($file['tmp_name'], $dst)) jsonErr('Could not save image.');
    return 'covers/' . $hash . '.' . $ext;
}

/* Make sure featured column exists */
try { $db->exec("ALTER TABLE songs  ADD COLUMN featured INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE albums ADD COLUMN featured INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE artists ADD COLUMN banner TEXT"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE albums  ADD COLUMN genre TEXT"); } catch (Exception $e) {}

switch ($action) {

    /* ── Song: get ─────────────────────────────────────────────────────── */
    case 'get_song': {
        $sid = (int)($post['song_id'] ?? 0);
        if (!$sid) jsonErr('song_id required.');
        if (!isAdmin()) jsonErr('Admin only.', 403);
        $s = $db->prepare('SELECT s.*,a.name AS artist,al.name AS album,al.cover FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE s.id=? LIMIT 1');
        $s->execute([$sid]); $row=$s->fetch();
        if (!$row) jsonErr('Not found.',404);
        jsonOk($row);
    }

    /* ── Song: update metadata ─────────────────────────────────────────── */
    case 'update_song': {
        if (!isAdmin()) jsonErr('Admin only.', 403);
        $sid   = (int)($post['song_id'] ?? 0);
        $title = trim($post['title']   ?? '');
        $artist= trim($post['artist']  ?? '');
        $album = trim($post['album']   ?? '');
        if (!$sid || $title === '') jsonErr('song_id and title required.');
        $sq = $db->prepare('SELECT * FROM songs WHERE id=? LIMIT 1'); $sq->execute([$sid]); $song=$sq->fetch();
        if (!$song) jsonErr('Not found.',404);
        $db->beginTransaction();
        try {
            if ($artist) { $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artist]); $ar=$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1');$ar->execute([$artist]);$artistId=(int)$ar->fetchColumn(); } else { $artistId=$song['artist_id']; }
            if ($album)  { $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name) VALUES(?,?)')->execute([$artistId,$album]); $alb=$db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1');$alb->execute([$artistId,$album]);$albumId=(int)$alb->fetchColumn(); } else { $albumId=$song['album_id']; }
            $db->prepare('UPDATE songs SET title=?,artist_id=?,album_id=? WHERE id=?')->execute([$title,$artistId,$albumId,$sid]);
            $db->commit();
            $us=$db->prepare('SELECT s.*,a.name AS artist,al.name AS album,al.cover FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE s.id=? LIMIT 1');$us->execute([$sid]);
            jsonOk($us->fetch());
        } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonErr('DB error: '.$e->getMessage()); }
    }

    /* ── Song: update cover ────────────────────────────────────────────── */
    case 'update_cover': {
        if (!isAdmin()) jsonErr('Admin only.', 403);
        $sid = (int)($post['song_id'] ?? 0);
        if (!$sid) jsonErr('song_id required.');
        $coverPath = saveUploadedImage('cover');
        if (!$coverPath) jsonErr('No cover uploaded.');
        $sq=$db->prepare('SELECT artist_id,album_id FROM songs WHERE id=? LIMIT 1');$sq->execute([$sid]);$song=$sq->fetch();
        if (!$song) jsonErr('Not found.',404);
        $db->prepare('UPDATE albums  SET cover=? WHERE id=?')->execute([$coverPath,$song['album_id']]);
        $db->prepare('UPDATE artists SET image=? WHERE id=?')->execute([$coverPath,$song['artist_id']]);
        jsonOk(['cover' => BASE_URL . '/' . $coverPath]);
    }

    /* ── Album: get ────────────────────────────────────────────────────── */
    case 'get_album': {
        $alid = (int)($post['album_id'] ?? 0);
        if (!$alid) jsonErr('album_id required.');
        $s=$db->prepare('SELECT al.*,a.name AS artist FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.id=? LIMIT 1');
        $s->execute([$alid]);$row=$s->fetch();
        if (!$row) jsonErr('Not found.',404);
        jsonOk($row);
    }

    /* ── Album: update ─────────────────────────────────────────────────── */
    case 'update_album': {
        $alid     = (int)($post['album_id'] ?? 0);
        $name     = trim($post['name']     ?? '');
        $artist   = trim($post['artist']   ?? '');
        $genre    = trim($post['genre']    ?? '');
        $year     = (int)($post['year']    ?? 0);
        $featured = (int)($post['featured'] ?? 0);
        if (!$alid) jsonErr('album_id required.');

        $coverPath = saveUploadedImage('cover');

        $db->beginTransaction();
        try {
            if ($artist) {
                $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artist]);
                $ar=$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1');$ar->execute([$artist]);$artistId=(int)$ar->fetchColumn();
                $db->prepare('UPDATE albums SET artist_id=? WHERE id=?')->execute([$artistId,$alid]);
            }
            $sets = ['genre=?','year=?','featured=?'];
            $vals = [$genre ?: null, $year ?: null, $featured];
            if ($name)      { $sets[]=$db->quote($name);      $db->prepare('UPDATE albums SET name=? WHERE id=?')->execute([$name,$alid]); }
            if ($genre)     $db->prepare('UPDATE albums SET genre=? WHERE id=?')->execute([$genre,$alid]);
            $db->prepare('UPDATE albums SET year=?,featured=? WHERE id=?')->execute([$year?:null,$featured,$alid]);
            if ($coverPath) $db->prepare('UPDATE albums SET cover=? WHERE id=?')->execute([$coverPath,$alid]);
            $db->commit();
            $s=$db->prepare('SELECT al.*,a.name AS artist FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.id=? LIMIT 1');$s->execute([$alid]);
            jsonOk($s->fetch());
        } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonErr('DB error: '.$e->getMessage()); }
    }

    /* ── Artist: get ───────────────────────────────────────────────────── */
    case 'get_artist': {
        $aid = (int)($post['artist_id'] ?? 0);
        if (!$aid) jsonErr('artist_id required.');
        $s=$db->prepare('SELECT * FROM artists WHERE id=? LIMIT 1');$s->execute([$aid]);$row=$s->fetch();
        if (!$row) jsonErr('Not found.',404);
        jsonOk($row);
    }

    /* ── Artist: update ────────────────────────────────────────────────── */
    case 'update_artist': {
        $aid  = (int)($post['artist_id'] ?? 0);
        $name = trim($post['name'] ?? '');
        if (!$aid) jsonErr('artist_id required.');

        $imgPath    = saveUploadedImage('image');
        $bannerPath = saveUploadedImage('banner');

        $db->beginTransaction();
        try {
            if ($name)      $db->prepare('UPDATE artists SET name=?   WHERE id=?')->execute([$name,$aid]);
            if ($imgPath)   $db->prepare('UPDATE artists SET image=?  WHERE id=?')->execute([$imgPath,$aid]);
            if ($bannerPath)$db->prepare('UPDATE artists SET banner=? WHERE id=?')->execute([$bannerPath,$aid]);
            $db->commit();
            $s=$db->prepare('SELECT * FROM artists WHERE id=? LIMIT 1');$s->execute([$aid]);
            jsonOk($s->fetch());
        } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonErr('DB error: '.$e->getMessage()); }
    }

    /* ── Search songs ─────────────────────────────────────────────────────── */
    case 'search_songs': {
        $q = '%' . trim($post['q'] ?? '') . '%';
        $res = $db->prepare('SELECT s.id,s.title,a.name AS artist,al.cover FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE s.title LIKE ? OR a.name LIKE ? ORDER BY s.title LIMIT 20');
        $res->execute([$q,$q]); jsonOk($res->fetchAll());
    }
    /* ── Search albums ─────────────────────────────────────────────────────── */
    case 'search_albums': {
        $q = '%' . trim($post['q'] ?? '') . '%';
        $res = $db->prepare('SELECT al.id,al.name,al.cover,al.genre,al.year,a.name AS artist,a.id AS artist_id FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.name LIKE ? OR a.name LIKE ? ORDER BY al.name LIMIT 20');
        $res->execute([$q,$q]); jsonOk($res->fetchAll());
    }
    /* ── Search artists ────────────────────────────────────────────────────── */
    case 'search_artists': {
        $q = '%' . trim($post['q'] ?? '') . '%';
        $res = $db->prepare('SELECT id,name,image,banner FROM artists WHERE name LIKE ? ORDER BY name LIMIT 20');
        $res->execute([$q]); jsonOk($res->fetchAll());
    }
    /* ── Search audiobooks ─────────────────────────────────────────────────── */
    case 'search_audiobooks': {
        $q = '%' . trim($post['q'] ?? '') . '%';
        $res = $db->prepare('SELECT ab.id,ab.title,ab.cover,ab.narrator,s.name AS series FROM audiobooks ab LEFT JOIN series s ON s.id=ab.series_id WHERE ab.title LIKE ? OR s.name LIKE ? ORDER BY ab.title LIMIT 20');
        $res->execute([$q,$q]); jsonOk($res->fetchAll());
    }
    /* ── Get audiobook ─────────────────────────────────────────────────────── */
    case 'get_audiobook': {
        $abid = (int)($post['audiobook_id'] ?? 0);
        if (!$abid) jsonErr('audiobook_id required.');
        $res = $db->prepare('SELECT ab.*,s.name AS series FROM audiobooks ab LEFT JOIN series s ON s.id=ab.series_id WHERE ab.id=? LIMIT 1');
        $res->execute([$abid]); $row=$res->fetch();
        if (!$row) jsonErr('Not found.',404);
        jsonOk($row);
    }
    /* ── Update audiobook ──────────────────────────────────────────────────── */
    case 'update_audiobook': {
        if (!isAdmin()) jsonErr('Admin only.',403);
        $abid     = (int)($post['audiobook_id'] ?? 0);
        $title    = trim($post['title']    ?? '');
        $seriesNm = trim($post['series']   ?? '');
        $narrator = trim($post['narrator'] ?? '');
        if (!$abid||!$title) jsonErr('audiobook_id and title required.');
        $coverPath = saveUploadedImage('cover');
        $db->beginTransaction();
        try {
            $seriesId = null;
            if ($seriesNm !== '') {
                $db->prepare('INSERT OR IGNORE INTO series(name) VALUES(?)')->execute([$seriesNm]);
                $sr=$db->prepare('SELECT id FROM series WHERE name=? LIMIT 1');$sr->execute([$seriesNm]);$seriesId=(int)$sr->fetchColumn();
            }
            $db->prepare('UPDATE audiobooks SET title=?,series_id=?,narrator=? WHERE id=?')->execute([$title,$seriesId,$narrator,$abid]);
            if ($coverPath) $db->prepare('UPDATE audiobooks SET cover=? WHERE id=?')->execute([$coverPath,$abid]);
            $db->commit();
            $res=$db->prepare('SELECT ab.*,s.name AS series FROM audiobooks ab LEFT JOIN series s ON s.id=ab.series_id WHERE ab.id=? LIMIT 1');$res->execute([$abid]);
            jsonOk($res->fetch());
        } catch(Exception $e){ if($db->inTransaction())$db->rollBack(); jsonErr('DB error: '.$e->getMessage()); }
    }

        default: jsonErr('Unknown action.');
}
