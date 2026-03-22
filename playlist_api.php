<?php
ob_start();

/**
 * CUMU – backend/playlist_api.php
 * Playlist management + favorites support.
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) jsonErr('Unauthorized', 401);

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$post   = (!empty($json) && is_array($json)) ? $json : $_POST;
$action = $post['action'] ?? ($_GET['action'] ?? '');
$db     = getDB();
$uid    = currentUserId();

/* Ensure is_favorite column exists */
try { $db->exec('ALTER TABLE playlists ADD COLUMN is_favorite INTEGER DEFAULT 0'); } catch (Exception $e) {}

switch ($action) {

  /* ── List user playlists ─────────────────────────────────────────────── */
  case 'list_user': {
    $s = $db->prepare('SELECT id, name, description, cover, is_favorite FROM playlists WHERE user_id=? ORDER BY is_favorite DESC, name ASC');
    $s->execute([$uid]);
    jsonOk($s->fetchAll());
  }

  /* ── Create playlist ─────────────────────────────────────────────────── */
  case 'create_playlist': {
    $name = trim($post['name'] ?? '');
    $desc = trim($post['description'] ?? '');
    if ($name === '')         jsonErr('Name is required.');
    if (strlen($name) > 100) jsonErr('Name too long.');
    $db->prepare('INSERT INTO playlists(user_id,name,description) VALUES(?,?,?)')->execute([$uid, $name, $desc ?: null]);
    $id = (int)$db->lastInsertId();
    jsonOk(['id' => $id, 'name' => $name]);
  }

  /* ── Get or create Favorites playlist ───────────────────────────────── */
  case 'get_or_create_favorites': {
    $s = $db->prepare('SELECT id, name FROM playlists WHERE user_id=? AND is_favorite=1 LIMIT 1');
    $s->execute([$uid]);
    $fav = $s->fetch();
    if (!$fav) {
      $favCover = 'assets/covers/favourite.png';
      $db->prepare('INSERT INTO playlists(user_id,name,is_favorite,cover) VALUES(?,?,1,?)')->execute([$uid, 'Favorites', $favCover]);
      $fav = ['id' => (int)$db->lastInsertId(), 'name' => 'Favorites', 'cover' => $favCover];
    }
    jsonOk($fav);
  }

  /* ── Check if song is favorited ──────────────────────────────────────── */
  case 'is_favorite': {
    $sid = (int)($post['song_id'] ?? 0);
    if (!$sid) jsonErr('song_id required.');
    // Get favorites playlist
    $fp = $db->prepare('SELECT id FROM playlists WHERE user_id=? AND is_favorite=1 LIMIT 1');
    $fp->execute([$uid]); $favPl = $fp->fetch();
    if (!$favPl) { jsonOk(['is_fav' => false]); }
    $chk = $db->prepare('SELECT 1 FROM playlist_songs WHERE playlist_id=? AND song_id=? LIMIT 1');
    $chk->execute([$favPl['id'], $sid]);
    jsonOk(['is_fav' => (bool)$chk->fetch()]);
  }

  /* ── Update playlist (name, description, cover) ────────────────────────── */
  case 'update_playlist': {
    $pid  = (int)($post['playlist_id'] ?? 0);
    $name = trim($post['name'] ?? '');
    $desc = trim($post['description'] ?? '');
    if (!$pid) jsonErr('playlist_id required.');
    $pl = $db->prepare('SELECT user_id, is_favorite FROM playlists WHERE id=? LIMIT 1');
    $pl->execute([$pid]); $row = $pl->fetch();
    if (!$row) jsonErr('Not found.', 404);
    if ($row['user_id'] !== $uid && !isAdmin()) jsonErr('Forbidden.', 403);
    if ($name !== '' && !$row['is_favorite']) {
        $db->prepare('UPDATE playlists SET name=? WHERE id=?')->execute([$name, $pid]);
    }
    if ($desc !== '') {
        $db->prepare('UPDATE playlists SET description=? WHERE id=?')->execute([$desc, $pid]);
    }
    // Cover upload handled separately via update_playlist_cover
    $ps = $db->prepare('SELECT id,name,description,cover,is_favorite FROM playlists WHERE id=? LIMIT 1');
    $ps->execute([$pid]); jsonOk($ps->fetch());
  }

  /* ── Update playlist cover ───────────────────────────────────────────────── */
  case 'update_playlist_cover': {
    $pid = (int)($post['playlist_id'] ?? $_POST['playlist_id'] ?? 0);
    if (!$pid) jsonErr('playlist_id required.');
    $pl = $db->prepare('SELECT user_id,is_favorite FROM playlists WHERE id=? LIMIT 1');
    $pl->execute([$pid]); $row=$pl->fetch();
    if (!$row) jsonErr('Not found.',404);
    if ($row['user_id'] !== $uid && !isAdmin()) jsonErr('Forbidden.',403);
    if ($row['is_favorite']) jsonErr('Cannot change Favorites cover.');
    if (empty($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) jsonErr('No cover file.');
    $f = $_FILES['cover'];
    if ($f['size'] > 5*1024*1024) jsonErr('Too large (max 5 MB).');
    $info = @getimagesize($f['tmp_name']); if (!$info) jsonErr('Invalid image.');
    $mime = $info['mime'];
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) jsonErr('JPEG/PNG/WebP only.');
    $ext  = match($mime){'image/png'=>'png','image/webp'=>'webp',default=>'jpg'};
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR,0750,true);
    $hash = md5_file($f['tmp_name']).'_pl';
    $dst  = COVERS_DIR.'/'.$hash.'.'.$ext;
    if (!file_exists($dst)) move_uploaded_file($f['tmp_name'],$dst);
    $coverPath = 'covers/'.$hash.'.'.$ext;
    $db->prepare('UPDATE playlists SET cover=? WHERE id=?')->execute([$coverPath,$pid]);
    jsonOk(['cover' => BASE_URL.'/'.$coverPath]);
  }

    /* ── Delete playlist ─────────────────────────────────────────────────── */
  case 'delete_playlist': {
    $pid = (int)($post['playlist_id'] ?? 0);
    if (!$pid) jsonErr('playlist_id required.');
    $pl = $db->prepare('SELECT user_id, is_favorite FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
    if (!$row)  jsonErr('Not found.', 404);
    if ($row['user_id'] !== $uid && !isAdmin()) jsonErr('Forbidden.', 403);
    if ($row['is_favorite']) jsonErr('Cannot delete Favorites playlist.');
    $db->prepare('DELETE FROM playlists WHERE id=?')->execute([$pid]);
    jsonOk(['deleted' => $pid]);
  }

  /* ── Add song ────────────────────────────────────────────────────────── */
  case 'add_song': {
    $pid = (int)($post['playlist_id'] ?? 0);
    $sid = (int)($post['song_id']     ?? 0);
    if (!$pid||!$sid) jsonErr('playlist_id and song_id required.');
    $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
    if (!$row) jsonErr('Not found.', 404);
    if ($row['user_id'] !== $uid && !isAdmin()) jsonErr('Forbidden.', 403);
    $mp = $db->prepare('SELECT COALESCE(MAX(position),0) FROM playlist_songs WHERE playlist_id=?'); $mp->execute([$pid]); $maxPos=(int)$mp->fetchColumn();
    try {
      $db->prepare('INSERT INTO playlist_songs(playlist_id,song_id,position) VALUES(?,?,?)')->execute([$pid,$sid,$maxPos+1]);
      jsonOk(['playlist_id'=>$pid,'song_id'=>$sid]);
    } catch (Exception $e) { jsonErr('Song already in playlist.'); }
  }

  /* ── Remove song ─────────────────────────────────────────────────────── */
  case 'remove_song': {
    $pid = (int)($post['playlist_id'] ?? 0);
    $sid = (int)($post['song_id']     ?? 0);
    if (!$pid||!$sid) jsonErr('playlist_id and song_id required.');
    $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
    if (!$row) jsonErr('Not found.', 404);
    if ($row['user_id'] !== $uid && !isAdmin()) jsonErr('Forbidden.', 403);
    $db->prepare('DELETE FROM playlist_songs WHERE playlist_id=? AND song_id=?')->execute([$pid,$sid]);
    jsonOk(['removed'=>true]);
  }

  /* ── Reorder ─────────────────────────────────────────────────────────── */
  case 'reorder': {
    $pid   = (int)($post['playlist_id'] ?? 0);
    $order = $post['order'] ?? [];
    if (!$pid||empty($order)) jsonErr('playlist_id and order[] required.');
    $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
    if (!$row||($row['user_id']!==$uid&&!isAdmin())) jsonErr('Forbidden.', 403);
    $stmt = $db->prepare('UPDATE playlist_songs SET position=? WHERE playlist_id=? AND song_id=?');
    foreach ($order as $pos => $sid) { $stmt->execute([$pos, $pid, (int)$sid]); }
    jsonOk(['reordered'=>true]);
  }

  default: jsonErr('Unknown action.');
}
