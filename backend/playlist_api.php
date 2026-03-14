<?php
/**
 * CUMU – backend/playlist_api.php
 * JSON API for playlist management.
 *
 * POST actions (form data or JSON body):
 *   create_playlist  { name }
 *   delete_playlist  { playlist_id }
 *   add_song         { playlist_id, song_id }
 *   remove_song      { playlist_id, song_id }
 *   reorder          { playlist_id, order: [song_id,...] }
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) jsonErr('Unauthorized', 401);

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$post   = !empty($json) ? $json : $_POST;
$action = $post['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

    // ── Create playlist ──────────────────────────────────────
    case 'create_playlist': {
        $name = trim($post['name'] ?? '');
        if ($name === '')         jsonErr('Name is required.');
        if (strlen($name) > 100) jsonErr('Name too long (max 100 chars).');

        $db->prepare('INSERT INTO playlists(user_id,name) VALUES(?,?)')->execute([$uid,$name]);
        $id = (int)$db->lastInsertId();
        jsonOk(['id'=>$id,'name'=>$name]);
    }

    // ── Delete playlist ──────────────────────────────────────
    case 'delete_playlist': {
        $pid = (int)($post['playlist_id'] ?? 0);
        if (!$pid) jsonErr('playlist_id required.');
        // Only owner or admin may delete
        $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
        if (!$row) jsonErr('Not found.',404);
        if ($row['user_id']!==$uid && !isAdmin()) jsonErr('Forbidden.',403);
        $db->prepare('DELETE FROM playlists WHERE id=?')->execute([$pid]);
        jsonOk(['deleted'=>$pid]);
    }

    // ── Add song to playlist ─────────────────────────────────
    case 'add_song': {
        $pid = (int)($post['playlist_id'] ?? 0);
        $sid = (int)($post['song_id']     ?? 0);
        if (!$pid||!$sid) jsonErr('playlist_id and song_id required.');
        // Ownership check
        $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
        if (!$row) jsonErr('Playlist not found.',404);
        if ($row['user_id']!==$uid && !isAdmin()) jsonErr('Forbidden.',403);
        // Max position
        $maxPos = (int)$db->prepare('SELECT COALESCE(MAX(position),0) FROM playlist_songs WHERE playlist_id=?')->execute([$pid]) ?
                  (int)$db->prepare('SELECT COALESCE(MAX(position),0) FROM playlist_songs WHERE playlist_id=?')->execute([$pid]) : 0;
        $mp = $db->prepare('SELECT COALESCE(MAX(position),0) FROM playlist_songs WHERE playlist_id=?'); $mp->execute([$pid]); $maxPos=(int)$mp->fetchColumn();
        try {
            $db->prepare('INSERT INTO playlist_songs(playlist_id,song_id,position) VALUES(?,?,?)')->execute([$pid,$sid,$maxPos+1]);
            jsonOk(['playlist_id'=>$pid,'song_id'=>$sid]);
        } catch (Exception) { jsonErr('Song already in playlist.'); }
    }

    // ── Remove song from playlist ────────────────────────────
    case 'remove_song': {
        $pid = (int)($post['playlist_id'] ?? 0);
        $sid = (int)($post['song_id']     ?? 0);
        if (!$pid||!$sid) jsonErr('playlist_id and song_id required.');
        $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
        if (!$row) jsonErr('Not found.',404);
        if ($row['user_id']!==$uid && !isAdmin()) jsonErr('Forbidden.',403);
        $db->prepare('DELETE FROM playlist_songs WHERE playlist_id=? AND song_id=?')->execute([$pid,$sid]);
        jsonOk(['removed'=>true]);
    }

    // ── Reorder playlist ─────────────────────────────────────
    case 'reorder': {
        $pid   = (int)($post['playlist_id'] ?? 0);
        $order = $post['order'] ?? [];
        if (!$pid||empty($order)) jsonErr('playlist_id and order[] required.');
        $pl = $db->prepare('SELECT user_id FROM playlists WHERE id=? LIMIT 1'); $pl->execute([$pid]); $row=$pl->fetch();
        if (!$row||($row['user_id']!==$uid && !isAdmin())) jsonErr('Forbidden.',403);
        $stmt = $db->prepare('UPDATE playlist_songs SET position=? WHERE playlist_id=? AND song_id=?');
        foreach ($order as $pos => $sid) {
            $stmt->execute([$pos, $pid, (int)$sid]);
        }
        jsonOk(['reordered'=>true]);
    }

    case 'list_user': {
        $s=$db->prepare('SELECT id,name FROM playlists WHERE user_id=? ORDER BY name ASC');$s->execute([$uid]);jsonOk($s->fetchAll());
    }

    default: jsonErr('Unknown action.');
}

// list_user is also supported via GET for the picker
