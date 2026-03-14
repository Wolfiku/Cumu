<?php
/**
 * CUMU – library.php
 *
 * Full music library view: browse by artist/album, or view all tracks.
 */

require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/layout.php';

requireLogin();

$db = getDB();

// ── Fetch songs grouped by artist then album ──────────────────────────────────
$songs = $db->query(
    'SELECT id, title, artist, album, cover, duration
     FROM songs
     ORDER BY artist ASC, album ASC, title ASC'
)->fetchAll();

// Group by artist > album
$grouped = [];
foreach ($songs as $song) {
    $artist = $song['artist'] ?: 'Unknown Artist';
    $album  = $song['album']  ?: 'Unknown Album';
    $grouped[$artist][$album][] = $song;
}

function formatDuration(int $seconds): string
{
    if ($seconds <= 0) return '--';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad((string)$s, 2, '0', STR_PAD_LEFT);
}

// ─────────────────────────────────────────────────────────────────────────────
layoutHead('Music Library');
?>

<div class="app-wrap">

<?php layoutSidebar('library'); ?>

<div class="main-content">

  <header class="page-header">
    <h1 class="page-title">Music Library</h1>
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

    <?php if (empty($songs)): ?>

      <div class="empty-state">
        <?= icon('note') ?>
        <div class="empty-state-title">Your library is empty</div>
        <div class="empty-state-text">
          Add MP3 files to the <code>music/</code> directory and run the scanner
          to populate your library.
        </div>
      </div>

    <?php else: ?>

      <!-- All tracks flat table (used by player.js for queue) -->
      <div class="song-table-wrap" style="display:none" aria-hidden="true">
        <table class="song-table" id="all-songs-table">
          <tbody>
          <?php foreach ($songs as $song): ?>
            <tr
              data-song-id="<?= (int) $song['id'] ?>"
              data-song-title="<?= h($song['title']  ?? '') ?>"
              data-song-artist="<?= h($song['artist'] ?? '') ?>"
              data-song-album="<?= h($song['album']   ?? '') ?>"
              data-song-cover="<?= $song['cover'] ? h($song['cover']) : '' ?>"
            ></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php foreach ($grouped as $artist => $albums): ?>

        <!-- Artist section -->
        <div class="artist-section" style="margin-bottom:36px">
          <h2 style="font-size:18px; font-weight:700; letter-spacing:-0.3px; margin-bottom:16px; color:var(--text)">
            <?= h($artist) ?>
          </h2>

          <?php foreach ($albums as $album => $tracks): ?>
            <?php
              // Use cover from first track that has one
              $albumCover = null;
              foreach ($tracks as $t) {
                if ($t['cover']) { $albumCover = $t['cover']; break; }
              }
            ?>

            <!-- Album block -->
            <div class="album-block" style="margin-bottom:24px; border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden">

              <!-- Album header -->
              <div style="display:flex; align-items:center; gap:14px; padding:14px 20px; background:var(--bg-subtle); border-bottom:1px solid var(--border)">
                <?php if ($albumCover): ?>
                  <img src="<?= h($albumCover) ?>" alt="" style="width:42px;height:42px;border-radius:4px;object-fit:cover;border:1px solid var(--border)">
                <?php else: ?>
                  <div style="width:42px;height:42px;border-radius:4px;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;border:1px solid var(--border)">
                    <?= icon('note') ?>
                  </div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:700;font-size:15px;color:var(--text)"><?= h($album) ?></div>
                  <div style="font-size:12px;color:var(--text-faint);margin-top:2px"><?= count($tracks) ?> track<?= count($tracks) !== 1 ? 's' : '' ?></div>
                </div>
              </div>

              <!-- Track list for album -->
              <table class="song-table" aria-label="<?= h($album) ?> tracklist">
                <tbody>
                <?php foreach ($tracks as $idx => $song): ?>
                  <tr
                    data-song-id="<?= (int) $song['id'] ?>"
                    data-song-title="<?= h($song['title']  ?? '') ?>"
                    data-song-artist="<?= h($song['artist'] ?? '') ?>"
                    data-song-album="<?= h($song['album']   ?? '') ?>"
                    data-song-cover="<?= $song['cover'] ? h($song['cover']) : '' ?>"
                    style="cursor:pointer"
                  >
                    <td style="width:46px; padding-left:20px">
                      <span class="song-num"><?= $idx + 1 ?></span>
                      <button class="song-play-btn" title="Play" aria-label="Play <?= h($song['title'] ?? '') ?>">
                        <?= icon('play') ?>
                      </button>
                    </td>
                    <td>
                      <div class="song-title" style="max-width:340px"><?= h($song['title'] ?? 'Unknown Title') ?></div>
                    </td>
                    <td style="text-align:right; padding-right:20px">
                      <span class="song-duration"><?= formatDuration((int) $song['duration']) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>

            </div><!-- /.album-block -->

          <?php endforeach; ?>
        </div><!-- /.artist-section -->

      <?php endforeach; ?>

    <?php endif; ?>

  </main>

</div><!-- /.main-content -->

<?php layoutPlayerBar(); ?>

</div><!-- /.app-wrap -->

<script src="player.js"></script>

<script>
// On the library page, the queue should be built from the hidden flat table
// so that prev/next navigation works across the full library.
document.addEventListener('DOMContentLoaded', function () {
  // Override the visible song tables used by player.js queue builder
  // to also include the hidden #all-songs-table rows.
  // player.js already calls buildQueueFromTable which reads all [data-song-id] rows.
  // As long as the hidden table rows have the right data attributes, the queue is correct.
});
</script>

</body>
</html>
