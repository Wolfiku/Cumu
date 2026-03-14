<?php
/**
 * CUMU – playlists.php
 *
 * Playlist management page.
 * Currently shows existing playlists and allows creation.
 * Full playlist editing will be added in a future update.
 */

require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/layout.php';

requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Handle create playlist POST ────────────────────────────────────────────────
$createError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $createError = 'Playlist name cannot be empty.';
    } elseif (strlen($name) > 100) {
        $createError = 'Playlist name is too long (max 100 characters).';
    } else {
        $stmt = $db->prepare('INSERT INTO playlists (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);
        header('Location: playlists.php');
        exit;
    }
}

// ── Handle delete playlist POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $pid = (int) ($_POST['playlist_id'] ?? 0);
    if ($pid > 0) {
        // Only delete playlists owned by the current user
        $stmt = $db->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$pid, $userId]);
    }
    header('Location: playlists.php');
    exit;
}

// ── Fetch user's playlists ────────────────────────────────────────────────────
$playlists = $db->prepare(
    'SELECT p.id, p.name, p.created_at,
            COUNT(ps.song_id) AS song_count
     FROM playlists p
     LEFT JOIN playlist_songs ps ON ps.playlist_id = p.id
     WHERE p.user_id = ?
     GROUP BY p.id
     ORDER BY p.created_at DESC'
);
$playlists->execute([$userId]);
$playlists = $playlists->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
layoutHead('Playlists');
?>

<div class="app-wrap">

<?php layoutSidebar('playlists'); ?>

<div class="main-content">

  <header class="page-header">
    <h1 class="page-title">Playlists</h1>
  </header>

  <main class="page-body">

    <!-- Coming-soon notice -->
    <div class="placeholder-banner" style="margin-bottom:32px">
      <div class="placeholder-banner-title">Playlists – Coming Soon</div>
      <div class="placeholder-banner-text">
        Full playlist management with drag-and-drop ordering will be added in the next update.
        You can already create and delete playlists below.
      </div>
    </div>

    <!-- Create playlist form -->
    <div style="margin-bottom:28px">
      <div class="section-title">New Playlist</div>
      <?php if ($createError): ?>
        <div class="alert alert-error"><?= h($createError) ?></div>
      <?php endif; ?>
      <form method="POST" style="display:flex; gap:10px; max-width:440px">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="flex:1; margin-bottom:0">
          <input
            type="text"
            name="name"
            placeholder="Playlist name..."
            maxlength="100"
            required
          >
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto; padding:10px 18px; white-space:nowrap">
          Create
        </button>
      </form>
    </div>

    <!-- Playlist list -->
    <div class="section-title">Your Playlists</div>

    <?php if (empty($playlists)): ?>
      <div class="empty-state">
        <?= icon('list') ?>
        <div class="empty-state-title">No playlists yet</div>
        <div class="empty-state-text">Create your first playlist to get started.</div>
      </div>
    <?php else: ?>

      <div class="song-table-wrap">
        <table class="song-table" aria-label="Your playlists">
          <thead>
            <tr>
              <th>Name</th>
              <th>Songs</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($playlists as $pl): ?>
            <tr>
              <td style="font-weight:600"><?= h($pl['name']) ?></td>
              <td style="color:var(--text-muted)"><?= (int) $pl['song_count'] ?></td>
              <td style="color:var(--text-faint); font-size:13px">
                <?= h(date('d M Y', strtotime($pl['created_at']))) ?>
              </td>
              <td style="text-align:right">
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete playlist &quot;<?= h(addslashes($pl['name'])) ?>&quot;?')">
                  <input type="hidden" name="action"      value="delete">
                  <input type="hidden" name="playlist_id" value="<?= (int) $pl['id'] ?>">
                  <button type="submit" class="btn btn-secondary"
                          style="font-size:12px; padding:6px 12px">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

  </main>

</div><!-- /.main-content -->

<?php layoutPlayerBar(); ?>

</div><!-- /.app-wrap -->

<script src="player.js"></script>
</body>
</html>
