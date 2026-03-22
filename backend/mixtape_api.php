<?php
/**
 * CUMU – backend/mixtape_api.php
 *
 * Mixtapes are album-like collections.
 * Only publishers and admins can create / manage them.
 * All logged-in users can list and stream them.
 */
require_once __DIR__ . '/../backend/session.php';
if (!isLoggedIn()) jsonErr('Unauthorized', 401);

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$post   = (!empty($json) && is_array($json)) ? $json : $_POST;
$action = $post['action'] ?? '';
$db     = getDB();
$uid    = currentUserId();

switch ($action) {

  /* ── List all mixtapes (visible to everyone) ──────────────────────── */
  case 'list': {
    $s = $db->prepare(
      'SELECT m.id, m.name, m.created_at, u.username AS creator,
              COUNT(ms.song_id) AS song_count
       FROM mixtapes m
       LEFT JOIN users u ON u.id = m.creator_id
       LEFT JOIN mixtape_songs ms ON ms.mixtape_id = m.id
       GROUP BY m.id
       ORDER BY m.created_at DESC'
    );
    $s->execute();
    jsonOk($s->fetchAll());
  }

  /* ── Get one mixtape with full song list ──────────────────────────── */
  case 'get': {
    $mid = (int)($post['mixtape_id'] ?? 0);
    if (!$mid) jsonErr('mixtape_id required.');
    $mq = $db->prepare(
      'SELECT m.*, u.username AS creator
       FROM mixtapes m
       LEFT JOIN users u ON u.id = m.creator_id
       WHERE m.id = ? LIMIT 1'
    );
    $mq->execute([$mid]); $mx = $mq->fetch();
    if (!$mx) jsonErr('Not found.', 404);

    $sq = $db->prepare(
      'SELECT s.id, s.title, s.duration, s.path,
              a.name AS artist, al.name AS album, al.cover,
              a.id AS artist_id, ms.position
       FROM mixtape_songs ms
       JOIN songs s    ON s.id  = ms.song_id
       LEFT JOIN artists a  ON a.id  = s.artist_id
       LEFT JOIN albums  al ON al.id = s.album_id
       WHERE ms.mixtape_id = ?
       ORDER BY ms.position, ms.added_at'
    );
    $sq->execute([$mid]);
    $mx['songs'] = $sq->fetchAll();
    jsonOk($mx);
  }

  /* ── Create (publisher / admin only) ──────────────────────────────── */
  case 'create': {
    if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);
    $name = trim($post['name'] ?? '');
    if ($name === '')        jsonErr('Name is required.');
    if (strlen($name) > 100) jsonErr('Name too long (max 100).');
    $db->prepare('INSERT INTO mixtapes(creator_id, name) VALUES(?,?)')->execute([$uid, $name]);
    jsonOk(['id' => (int)$db->lastInsertId(), 'name' => $name]);
  }

  /* ── Rename (publisher / admin only) ──────────────────────────────── */
  case 'rename': {
    if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);
    $mid  = (int)($post['mixtape_id'] ?? 0);
    $name = trim($post['name'] ?? '');
    if (!$mid || $name === '') jsonErr('mixtape_id and name required.');
    // Only creator or admin can rename
    $mq = $db->prepare('SELECT creator_id FROM mixtapes WHERE id=? LIMIT 1');
    $mq->execute([$mid]); $row = $mq->fetch();
    if (!$row || ($row['creator_id'] !== $uid && !isAdmin())) jsonErr('Forbidden.', 403);
    $db->prepare('UPDATE mixtapes SET name=? WHERE id=?')->execute([$name, $mid]);
    jsonOk(['id' => $mid, 'name' => $name]);
  }

  /* ── Delete (publisher / admin only) ──────────────────────────────── */
  case 'delete': {
    if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);
    $mid = (int)($post['mixtape_id'] ?? 0);
    if (!$mid) jsonErr('mixtape_id required.');
    $mq = $db->prepare('SELECT creator_id FROM mixtapes WHERE id=? LIMIT 1');
    $mq->execute([$mid]); $row = $mq->fetch();
    if (!$row || ($row['creator_id'] !== $uid && !isAdmin())) jsonErr('Forbidden.', 403);
    $db->prepare('DELETE FROM mixtapes WHERE id=?')->execute([$mid]);
    jsonOk(['deleted' => $mid]);
  }

  /* ── Add song (publisher / admin only) ───────────────────────────── */
  case 'add_song': {
    if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);
    $mid = (int)($post['mixtape_id'] ?? 0);
    $sid = (int)($post['song_id']    ?? 0);
    if (!$mid || !$sid) jsonErr('mixtape_id and song_id required.');
    $mq = $db->prepare('SELECT creator_id FROM mixtapes WHERE id=? LIMIT 1');
    $mq->execute([$mid]); $row = $mq->fetch();
    if (!$row || ($row['creator_id'] !== $uid && !isAdmin())) jsonErr('Forbidden.', 403);
    $mp = $db->prepare('SELECT COALESCE(MAX(position),0) FROM mixtape_songs WHERE mixtape_id=?');
    $mp->execute([$mid]); $maxPos = (int)$mp->fetchColumn();
    try {
      $db->prepare('INSERT INTO mixtape_songs(mixtape_id,song_id,position) VALUES(?,?,?)')->execute([$mid,$sid,$maxPos+1]);
      jsonOk(['mixtape_id' => $mid, 'song_id' => $sid]);
    } catch (Exception $e) { jsonErr('Song already in mixtape.'); }
  }

  /* ── Remove song (publisher / admin only) ────────────────────────── */
  case 'remove_song': {
    if (!isPublisher()) jsonErr('Publisher or Admin required.', 403);
    $mid = (int)($post['mixtape_id'] ?? 0);
    $sid = (int)($post['song_id']    ?? 0);
    if (!$mid || !$sid) jsonErr('mixtape_id and song_id required.');
    $mq = $db->prepare('SELECT creator_id FROM mixtapes WHERE id=? LIMIT 1');
    $mq->execute([$mid]); $row = $mq->fetch();
    if (!$row || ($row['creator_id'] !== $uid && !isAdmin())) jsonErr('Forbidden.', 403);
    $db->prepare('DELETE FROM mixtape_songs WHERE mixtape_id=? AND song_id=?')->execute([$mid,$sid]);
    jsonOk(['removed' => true]);
  }

  default: jsonErr('Unknown action.');
}
