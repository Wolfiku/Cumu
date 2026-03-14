<?php
/**
 * CUMU – backend/meta_api.php
 * Admin-only: update song/album/artist metadata.
 * POST multipart: song_id, title, artist_name, album_name, year, track_num, cover(file)
 */
require_once __DIR__ . '/../backend/session.php';
if (!isAdmin()) jsonErr('Admin only', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('POST only', 405);

$songId   = (int)($_POST['song_id'] ?? 0);
if (!$songId) jsonErr('song_id required');

$db = getDB();
$sq = $db->prepare('SELECT s.*,a.name AS artist_name,al.name AS album_name FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE s.id=? LIMIT 1');
$sq->execute([$songId]); $song = $sq->fetch();
if (!$song) jsonErr('Song not found', 404);

$newTitle      = trim($_POST['title']       ?? $song['title']);
$newArtistName = trim($_POST['artist_name'] ?? ($song['artist_name']??''));
$newAlbumName  = trim($_POST['album_name']  ?? ($song['album_name']??''));
$newYear       = (int)($_POST['year']       ?? 0);
$newTrack      = (int)($_POST['track_num']  ?? $song['track_num']);

if ($newTitle === '') jsonErr('Title cannot be empty');

try {
    $db->beginTransaction();

    // Upsert artist
    if ($newArtistName) {
        $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$newArtistName]);
        $ar=$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1');$ar->execute([$newArtistName]);$aid=(int)$ar->fetchColumn();
    } else {
        $aid = (int)$song['artist_id'];
    }

    // Upsert album
    if ($newAlbumName && $aid) {
        $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name,year) VALUES(?,?,?)')->execute([$aid,$newAlbumName,$newYear]);
        $alb=$db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1');$alb->execute([$aid,$newAlbumName]);$alid=(int)$alb->fetchColumn();
        if ($newYear) $db->prepare('UPDATE albums SET year=? WHERE id=?')->execute([$newYear,$alid]);
    } else {
        $alid = (int)$song['album_id'];
    }

    // Handle cover upload
    $coverRel = null;
    if (!empty($_FILES['cover']['tmp_name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $mime = mime_content_type($_FILES['cover']['tmp_name']);
        if (!str_starts_with($mime, 'image/')) jsonErr('Cover must be an image');
        $ext  = $mime === 'image/png' ? 'png' : 'jpg';
        if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);
        $fn   = md5($songId . time()) . '.' . $ext;
        move_uploaded_file($_FILES['cover']['tmp_name'], COVERS_DIR . '/' . $fn);
        $coverRel = 'covers/' . $fn;
        if ($alid) $db->prepare('UPDATE albums SET cover=? WHERE id=?')->execute([$coverRel,$alid]);
    }

    // Update song
    $db->prepare('UPDATE songs SET title=?,artist_id=?,album_id=?,track_num=? WHERE id=?')->execute([$newTitle,$aid,$alid,$newTrack,$songId]);

    $db->commit();
    jsonOk(['ok'=>true,'song_id'=>$songId,'title'=>$newTitle,'artist'=>$newArtistName,'album'=>$newAlbumName]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    jsonErr('DB error: ' . $e->getMessage(), 500);
}
