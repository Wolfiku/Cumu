<?php
/**
 * CUMU – dashboard.php
 *
 * Main dashboard: shows library stats and a full song list with player.
 */

require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/layout.php';

requireLogin();

$db       = getDB();
$username = currentUsername();

// ── Library statistics ────────────────────────────────────────────────────────
$totalSongs     = (int) $db->query('SELECT COUNT(*) FROM songs')->fetchColumn();
$totalArtists   = (int) $db->query('SELECT COUNT(DISTINCT artist) FROM songs')->fetchColumn();
$totalAlbums    = (int) $db->query('SELECT COUNT(DISTINCT album)  FROM songs')->fetchColumn();
$totalPlaylists = (int) $db->query(
    'SELECT COUNT(*) FROM playlists WHERE user_id = ' . currentUserId()
)->fetchColumn();

// ── Fetch all songs ───────────────────────────────────────────────────────────
$songs = $db->query(
    'SELECT id, title, artist, album, cover, duration
     FROM songs
     ORDER BY artist ASC, album ASC, title ASC'
)->fetchAll();

// ── Flash messages ────────────────────────────────────────────────────────────
$successMsg = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDuration(int $seconds): string
{
    if ($seconds <= 0) return '--';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad((string)$s, 2, '0', STR_PAD_LEFT);
}

// ─────────────────────────────────────────────────────────────────────────────

layoutHead('Dashboard');
?>

<div class="app-wrap">

<?php layoutSidebar('dashboard'); ?>

<div class="main-content">

  <!-- Page header -->
  <header class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <div class="search-wrap">
      <?= icon('search') ?>
      <input
        type="search"
        class="search-input"
        id="search-input"
        placeholder="Search songs, artists, albums..."
        aria-label="Search library"
      >
    </div>
  </header>

  <main class="page-body">

    <?php if ($successMsg): ?>
      <div class="alert alert-success" style="margin-bottom:24px">
        <?= h($successMsg) ?>
      </div>
    <?php endif; ?>

    <!-- Stats bar -->
    <div class="stats-bar">
      <div class="stat-card">
        <div class="stat-value"><?= $totalSongs ?></div>
        <div class="stat-label">Songs</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalArtists ?></div>
        <div class="stat-label">Artists</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalAlbums ?></div>
        <div class="stat-label">Albums</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalPlaylists ?></div>
        <div class="stat-label">Playlists</div>
      </div>
    </div>

    <!-- Song list -->
    <div class="section-title">All Songs</div>

    <?php if (empty($songs)): ?>

      <div class="empty-state">
        <?= icon('note') ?>
        <div class="empty-state-title">No music indexed yet</div>
        <div class="empty-state-text">
          Drop your MP3 files into the <code>music/</code> folder, then run
          <code>php scanner/scan_music.php</code> to populate your library.
        </div>
      </div>

    <?php else: ?>

      <div class="song-table-wrap">
        <table class="song-table" aria-label="Song list">
          <thead>
            <tr>
              <th style="width:50px">#</th>
              <th>Title</th>
              <th class="col-album">Album</th>
              <th style="width:80px; text-align:right">Time</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($songs as $i => $song):
            $coverAttr = $song['cover'] ? h($song['cover']) : '';
          ?>
            <tr
              data-song-id="<?= (int) $song['id'] ?>"
              data-song-title="<?= h($song['title']  ?? '') ?>"
              data-song-artist="<?= h($song['artist'] ?? '') ?>"
              data-song-album="<?= h($song['album']  ?? '') ?>"
              data-song-cover="<?= $coverAttr ?>"
              style="cursor:pointer"
            >
              <!-- # / play button -->
              <td>
                <span class="song-num"><?= $i + 1 ?></span>
                <button class="song-play-btn" title="Play" aria-label="Play <?= h($song['title'] ?? '') ?>">
                  <?= icon('play') ?>
                </button>
              </td>

              <!-- Title + artist -->
              <td>
                <div class="song-info">
                  <?php if ($song['cover']): ?>
                    <img
                      class="song-cover"
                      src="<?= $coverAttr ?>"
                      alt=""
                      loading="lazy"
                    >
                  <?php else: ?>
                    <div class="song-cover-placeholder"><?= icon('note') ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="song-title"><?= h($song['title']  ?? 'Unknown Title') ?></div>
                    <div class="song-artist"><?= h($song['artist'] ?? 'Unknown Artist') ?></div>
                  </div>
                </div>
              </td>

              <!-- Album -->
              <td class="song-album-cell col-album">
                <?= h($song['album'] ?? '') ?>
              </td>

              <!-- Duration -->
              <td>
                <span class="song-duration">
                  <?= formatDuration((int) $song['duration']) ?>
                </span>
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
