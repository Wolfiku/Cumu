<?php
/**
 * CUMU – backend/layout.php  v3
 * - Song sheet z-index above fullscreen player (310)
 * - Heart / favorites button in FP
 * - Better FP info row layout
 * - Page fade-in animation
 */
if (!defined('BASE_URL')) require_once __DIR__ . '/../config.php';

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
<script>window.CUMU_BASE=<?php echo json_encode($b); ?>;</script>
<div class="app-shell">
<div class="page-content page-enter" id="page-content" style="padding-bottom:16px">
<?php }

function appClose(string $activeTab = 'home'): void {
    $b        = BASE_URL;
    $isAdmin  = isAdmin()     ? 'true' : 'false';
    $isPub    = isPublisher() ? 'true' : 'false';
?>
</div><!-- /page-content -->
</div><!-- /app-shell -->

<script>
  window.CUMU_BASE      = <?php echo json_encode($b); ?>;
  window.CUMU_ADMIN     = <?php echo $isAdmin; ?>;
  window.CUMU_PUBLISHER = <?php echo $isPub; ?>;
</script>
<script src="<?php echo $b; ?>/player.js"></script>
</body>
</html>
<?php }

/* ── Bottom nav ──────────────────────────────────────────────────────────── */
function _renderBottomNav(string $active, string $b): void { ?>
<div class="bottom-nav" id="bottom-nav">

  <div class="mini-player" id="mini-player" style="display:none">
    <div class="mini-progress" id="mini-prog"></div>
    <div class="mini-cover" id="mini-cover">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)">
        <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
      </svg>
    </div>
    <div class="mini-meta">
      <div class="mini-title"  id="mini-title">No track</div>
      <div class="mini-artist" id="mini-artist">–</div>
    </div>
    <div class="mini-controls">
      <button class="mini-play" id="mini-play" aria-label="Play/Pause">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
        </svg>
      </button>
      <button class="mini-btn" id="mini-next" aria-label="Next">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
      </button>
    </div>
  </div>

  <nav class="nav-tabs">
    <a href="<?php echo $b; ?>/pages/home.php"
       class="nav-tab <?php echo $active==='home'    ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="<?php echo $active==='home' ? 'currentColor' : 'none'; ?>"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      <span>Home</span>
    </a>
    <a href="<?php echo $b; ?>/pages/search.php"
       class="nav-tab <?php echo $active==='search'  ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <span>Search</span>
    </a>
    <a href="<?php echo $b; ?>/pages/library.php"
       class="nav-tab <?php echo $active==='library' ? 'active' : ''; ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
      </svg>
      <span>Library</span>
    </a>
  </nav>
</div>
<?php }

/* ── Fullscreen player ───────────────────────────────────────────────────── */
function _renderFullscreenPlayer(string $b): void { ?>
<div class="fp" id="fp" role="dialog" aria-modal="true" aria-label="Now playing">
  <div class="fp-bg-art"  id="fp-bg"></div>
  <div class="fp-bg-grad"></div>
  <div class="fp-inner">

    <!-- Header row -->
    <div class="fp-header">
      <button class="fp-icon-btn" id="fp-down" aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </button>
      <div class="fp-label">Now Playing</div>
      <button class="fp-icon-btn" id="fp-more-btn" aria-label="Options">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
        </svg>
      </button>
    </div>

    <!-- Album art -->
    <div class="fp-art-wrap">
      <div class="fp-art" id="fp-art">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
             style="width:52px;height:52px;color:#52525b">
          <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
        </svg>
      </div>
    </div>

    <!-- Track info + heart -->
    <div class="fp-info">
      <div class="fp-info-text">
        <div class="fp-title"  id="fp-title">No track selected</div>
        <button class="fp-artist-btn" id="fp-artist" data-artist-id="">–</button>
      </div>
      <button class="fp-heart-btn" id="fp-heart" aria-label="Add to favorites">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </button>
    </div>

    <!-- Progress -->
    <div class="fp-progress">
      <div class="fp-track" id="fp-track">
        <div class="fp-fill" id="fp-fill"></div>
      </div>
      <div class="fp-times">
        <span id="fp-cur">0:00</span>
        <span id="fp-dur">0:00</span>
      </div>
    </div>

    <!-- Controls -->
    <div class="fp-controls">
      <button class="fp-ctrl" id="fp-prev" aria-label="Previous">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/>
        </svg>
      </button>
      <button class="fp-play" id="fp-play-btn" aria-label="Play/Pause">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
        </svg>
      </button>
      <button class="fp-ctrl" id="fp-next" aria-label="Next">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
      </button>
    </div>

  </div><!-- /fp-inner -->
</div><!-- /fp -->
<?php }

/* ── Song options sheet ──────────────────────────────────────────────────── */
function _renderSongSheet(string $b): void { ?>
<!-- Song options — z-index 310 (above FP at 200) -->
<div class="sheet-overlay" id="song-sheet-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <!-- Song header -->
    <div style="display:flex;align-items:center;gap:12px;padding:4px 16px 14px;border-bottom:1px solid var(--border)">
      <div id="ss-cover"
           style="width:46px;height:46px;border-radius:8px;background:var(--bg-card);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)">
          <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
        </svg>
      </div>
      <div style="min-width:0">
        <div id="ss-title"  style="font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
        <div id="ss-artist" style="font-size:13px;color:var(--t2);margin-top:2px"></div>
      </div>
    </div>
    <!-- Actions -->
    <div class="sheet-item" id="ss-add-pl">
      <div class="sheet-item-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
      </div>
      <div class="sheet-item-name">Add to playlist</div>
    </div>
    <a id="ss-artist-link" href="#" class="sheet-item" style="text-decoration:none">
      <div class="sheet-item-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <div class="sheet-item-name">Go to artist</div>
    </a>
    <a id="ss-edit-link" href="#" class="sheet-item" style="display:none;text-decoration:none">
      <div class="sheet-item-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
      </div>
      <div class="sheet-item-name">Edit metadata</div>
    </a>
  </div>
</div>

<!-- Playlist picker — z-index 320 -->
<div class="sheet-overlay" id="pl-sheet" style="z-index:320">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Add to Playlist</div>
    <div id="pl-sheet-list"></div>
    <div class="sheet-item" id="pl-new-item">
      <div class="sheet-item-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </div>
      <div><div class="sheet-item-name">New Playlist</div></div>
    </div>
  </div>
</div>
<?php }

/* ── Song row ────────────────────────────────────────────────────────────── */
function songRow(array $s, int $idx, string $b, bool $showArt = true, bool $showNum = false): void {
    $cover    = !empty($s['cover'])     ? $b . '/' . $s['cover'] : '';
    $artistId = isset($s['artist_id'])  ? (int)$s['artist_id']   : 0;
    $title    = $s['title']  ?? 'Unknown';
    $artist   = $s['artist'] ?? '';
    $songId   = (int)$s['id'];
?>
  <div class="song-row"
       data-id="<?php echo $songId; ?>"
       data-idx="<?php echo $idx; ?>"
       data-stream="<?php echo $b; ?>/backend/stream.php?id=<?php echo $songId; ?>"
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
               fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)">
            <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
          </svg>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="sr-info">
      <div class="sr-title"><?php echo h($title); ?></div>
      <div class="sr-artist"><?php echo h($artist); ?></div>
    </div>

    <button class="sr-action sr-more"
            data-song-id="<?php echo $songId; ?>"
            data-artist-id="<?php echo $artistId; ?>"
            data-title="<?php echo h($title); ?>"
            data-artist="<?php echo h($artist); ?>"
            data-cover="<?php echo h($cover); ?>"
            aria-label="Options">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
      </svg>
    </button>
  </div>
<?php }

/* ── Admin helpers ───────────────────────────────────────────────────────── */
function adminHead(string $title, string $active = ''): void {
    $b = BASE_URL;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo h($title); ?> – Cumu Admin</title>
  <link rel="stylesheet" href="<?php echo $b; ?>/style.css">
</head>
<body>
<script>window.CUMU_BASE=<?php echo json_encode($b); ?>;</script>
<div class="admin-wrap">
  <div class="admin-topbar">
    <div>
      <span class="admin-logo">Cu<span>mu</span></span>
      <span class="admin-logo-sub">Admin</span>
    </div>
    <div class="admin-topbar-right">
      <a href="<?php echo $b; ?>/pages/home.php"
         class="btn btn-secondary" style="width:auto;padding:7px 14px;font-size:13px;border-radius:var(--r)">
        ← App
      </a>
      <a href="<?php echo $b; ?>/backend/logout.php"
         class="btn btn-secondary" style="width:auto;padding:7px 14px;font-size:13px;border-radius:var(--r)">
        Sign out
      </a>
    </div>
  </div>
  <div class="admin-body">
  <nav class="admin-tabs">
    <a href="<?php echo $b; ?>/pages/admin.php"
       class="admin-tab <?php echo $active==='users'   ?'active':''; ?>">Users</a>
    <a href="<?php echo $b; ?>/pages/upload.php"
       class="admin-tab <?php echo $active==='upload'  ?'active':''; ?>">Upload</a>
    <a href="<?php echo $b; ?>/pages/indexer.php"
       class="admin-tab <?php echo $active==='indexer' ?'active':''; ?>">Re-Index</a>
  </nav>
<?php }

function adminFoot(): void { ?>
  </div><!-- /admin-body -->
</div><!-- /admin-wrap -->
</body>
</html>
<?php }
