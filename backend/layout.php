<?php
/**
 * CUMU – backend/layout.php
 * Mobile app shell: appOpen(), appClose(), bottom nav,
 * fullscreen player, playlist sheet, songRow() helper.
 * Admin helpers: adminHead(), adminFoot().
 */
if (!defined('BASE_URL')) require_once __DIR__ . '/../config.php';

/* ── App shell open ──────────────────────────────────────────────────────── */
function appOpen(string $title): void {
    $b = BASE_URL;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?php echo h($title); ?> – Cumu</title>
  <link rel="stylesheet" href="<?php echo $b; ?>/style.css">
</head>
<body>
<div class="app-shell">
<div class="page-content" id="page-content">
<?php
}

/* ── App shell close ─────────────────────────────────────────────────────── */
function appClose(string $activeTab = 'home'): void {
    $b = BASE_URL;
?>
</div><!-- /page-content -->

<?php _renderBottomNav($activeTab, $b); ?>
</div><!-- /app-shell -->

<?php _renderFullscreenPlayer($b); ?>
<?php _renderPlaylistSheet($b); ?>

<audio id="c-audio" preload="none"></audio>
<script>window.CUMU_BASE = <?php echo json_encode($b); ?>;</script>
<script src="<?php echo $b; ?>/player.js"></script>
</body>
</html>
<?php
}

/* ── Bottom navigation ───────────────────────────────────────────────────── */
function _renderBottomNav(string $active, string $b): void {
?>
<div class="bottom-nav" id="bottom-nav">

  <!-- Mini player (hidden until a track plays) -->
  <div class="mini-player" id="mini-player" style="display:none">
    <div class="mini-progress" id="mini-prog"></div>
    <div class="mini-cover" id="mini-cover">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 18V5l12-2v13"/>
        <circle cx="6" cy="18" r="3"/>
        <circle cx="18" cy="16" r="3"/>
      </svg>
    </div>
    <div class="mini-meta">
      <div class="mini-title"  id="mini-title">No track</div>
      <div class="mini-artist" id="mini-artist">–</div>
    </div>
    <div class="mini-controls">
      <button class="mini-play" id="mini-play" aria-label="Play/Pause">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
        </svg>
      </button>
      <button class="mini-btn" id="mini-next" aria-label="Next">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 4 15 12 5 20 5 4"/>
          <line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Nav tabs -->
  <nav class="nav-tabs">
    <a href="<?php echo $b; ?>/pages/home.php"
       class="nav-tab <?php echo $active === 'home'    ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="<?php echo $active === 'home' ? 'currentColor' : 'none'; ?>"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <span>Home</span>
    </a>
    <a href="<?php echo $b; ?>/pages/search.php"
       class="nav-tab <?php echo $active === 'search'  ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <span>Search</span>
    </a>
    <a href="<?php echo $b; ?>/pages/library.php"
       class="nav-tab <?php echo $active === 'library' ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
      </svg>
      <span>Library</span>
    </a>
  </nav>
</div>
<?php
}

/* ── Fullscreen player overlay ───────────────────────────────────────────── */
function _renderFullscreenPlayer(string $b): void {
?>
<div class="fp" id="fp" role="dialog" aria-modal="true" aria-label="Now playing">
  <div class="fp-bg-art"  id="fp-bg"></div>
  <div class="fp-bg-grad"></div>
  <div class="fp-inner">

    <div class="fp-header">
      <button class="fp-down" id="fp-down" aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </button>
      <div class="fp-label">Now Playing</div>
      <div style="width:40px"></div>
    </div>

    <div class="fp-art-wrap">
      <div class="fp-art" id="fp-art">
        <svg class="fp-art-ph" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1.5"
             style="width:56px;height:56px;color:#6a6a6a">
          <path d="M9 18V5l12-2v13"/>
          <circle cx="6" cy="18" r="3"/>
          <circle cx="18" cy="16" r="3"/>
        </svg>
      </div>
    </div>

    <div class="fp-info">
      <div style="min-width:0;flex:1">
        <div class="fp-title"  id="fp-title">No track selected</div>
        <div class="fp-artist" id="fp-artist" data-artist-id="">–</div>
      </div>
    </div>

    <div class="fp-progress">
      <div class="fp-track" id="fp-track">
        <div class="fp-fill" id="fp-fill"></div>
      </div>
      <div class="fp-times">
        <span id="fp-cur">0:00</span>
        <span id="fp-dur">0:00</span>
      </div>
    </div>

    <div class="fp-controls">
      <button class="fp-ctrl" id="fp-prev" aria-label="Previous">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="19 20 9 12 19 4 19 20"/>
          <line x1="5" y1="19" x2="5" y2="5"/>
        </svg>
      </button>
      <button class="fp-play" id="fp-play-btn" aria-label="Play/Pause">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
        </svg>
      </button>
      <button class="fp-ctrl" id="fp-next" aria-label="Next">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 4 15 12 5 20 5 4"/>
          <line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
      </button>
    </div>

    <div class="fp-vol">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
      </svg>
      <input type="range" class="vol-slider" id="fp-vol" min="0" max="100" value="85">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
        <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
      </svg>
    </div>

  </div>
</div>
<?php
}

/* ── Add-to-playlist sheet ───────────────────────────────────────────────── */
function _renderPlaylistSheet(string $b): void {
?>
<div class="sheet-overlay" id="pl-sheet">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Add to Playlist</div>
    <div id="pl-sheet-list"></div>
    <div class="sheet-item"
         onclick="window.location='<?php echo $b; ?>/pages/library.php'">
      <div class="sheet-item-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </div>
      <div><div class="sheet-item-name">New Playlist</div></div>
    </div>
  </div>
</div>
<?php
}

/* ── Reusable song row ───────────────────────────────────────────────────── */
function songRow(array $s, int $idx, string $b, bool $showArt = true, bool $showNum = false): void {
    $cover    = !empty($s['cover']) ? $b . '/' . $s['cover'] : '';
    $artistId = isset($s['artist_id']) ? (int) $s['artist_id'] : 0;
    $title    = isset($s['title'])  ? $s['title']  : 'Unknown';
    $artist   = isset($s['artist']) ? $s['artist'] : '';
    $songId   = (int) $s['id'];
    $stream   = $b . '/backend/stream.php?id=' . $songId;
?>
    <div class="song-row"
         data-id="<?php echo $songId; ?>"
         data-idx="<?php echo $idx; ?>"
         data-stream="<?php echo h($stream); ?>"
         data-title="<?php echo h($title); ?>"
         data-artist="<?php echo h($artist); ?>"
         data-cover="<?php echo h($cover); ?>"
         data-artist-id="<?php echo $artistId; ?>">

      <?php if ($showNum): ?>
        <div class="sr-num"><?php echo $idx + 1; ?></div>
      <?php endif; ?>

      <?php if ($showArt): ?>
        <div class="sr-art">
          <?php if ($cover): ?>
            <img src="<?php echo h($cover); ?>" alt="" loading="lazy">
          <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 18V5l12-2v13"/>
              <circle cx="6" cy="18" r="3"/>
              <circle cx="18" cy="16" r="3"/>
            </svg>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="sr-info">
        <div class="sr-title"><?php echo h($title); ?></div>
        <div class="sr-artist"><?php echo h($artist); ?></div>
      </div>

      <button class="sr-action" data-song-id="<?php echo $songId; ?>"
              title="Add to playlist" aria-label="Add to playlist">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="1"/>
          <circle cx="19" cy="12" r="1"/>
          <circle cx="5"  cy="12" r="1"/>
        </svg>
      </button>
    </div>
<?php
}

/* ── Admin page helpers ──────────────────────────────────────────────────── */
function adminHead(string $title, string $active = ''): void {
    $b = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo h($title); ?> – Cumu Admin</title>
  <link rel="stylesheet" href="<?php echo $b; ?>/style.css">
</head>
<body>
<div class="admin-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div style="font-size:20px;font-weight:800">
      Cu<span style="color:var(--accent)">mu</span>
      <span style="font-size:13px;font-weight:600;color:var(--tf);margin-left:6px">Admin</span>
    </div>
    <a href="<?php echo $b; ?>/pages/home.php"
       style="font-size:13px;color:var(--t2)">← App</a>
  </div>
  <nav class="admin-nav">
    <a href="<?php echo $b; ?>/pages/admin.php"
       class="<?php echo $active === 'users'   ? 'active' : ''; ?>">Users</a>
    <a href="<?php echo $b; ?>/pages/upload.php"
       class="<?php echo $active === 'upload'  ? 'active' : ''; ?>">Upload</a>
    <a href="<?php echo $b; ?>/pages/indexer.php"
       class="<?php echo $active === 'indexer' ? 'active' : ''; ?>">Re-Index</a>
    <a href="<?php echo $b; ?>/backend/logout.php"
       style="margin-left:auto">Sign out</a>
  </nav>
<?php
}

function adminFoot(): void {
?>
</div><!-- /admin-wrap -->
</body>
</html>
<?php
}
