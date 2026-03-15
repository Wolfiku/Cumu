<?php
/**
 * CUMU – app.php
 * The persistent shell. Audio + player live HERE forever.
 * All page content loads in an iframe — navigation never
 * destroys the audio element.
 *
 * URL scheme:  /app.php?p=pages/home.php
 *              /app.php?p=pages/search.php
 * Default:     home.php
 */
require_once __DIR__ . '/backend/session.php';
requireLogin();

$b    = BASE_URL;
$page = $_GET['p'] ?? 'pages/home.php';

// Whitelist allowed pages to prevent open-redirect
$allowed = [
  'pages/home.php','pages/search.php','pages/library.php',
  'pages/artist.php','pages/album.php','pages/playlist.php',
  'pages/song_edit.php',
  'pages/upload.php','pages/indexer.php','pages/admin.php',
];
// Allow pages with query strings like artist.php?id=3
$pageBase = strtok($page, '?');
if (!in_array($pageBase, $allowed, true)) {
  $page = 'pages/home.php';
}

$sep = strpos($page, '?') !== false ? '&' : '?';
$iframeUrl = $b . '/' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . $sep . 'frame=1';
$isAdmin   = isAdmin()     ? 'true' : 'false';
$isPub     = isPublisher() ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Cumu</title>
  <link rel="stylesheet" href="<?= $b ?>/style.css">
  <link rel="stylesheet" href="<?= $b ?>/player.css">
  <style>
    html, body { height: 100%; margin: 0; overflow: hidden; background: var(--bg); }
    .shell { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }
    .shell-frame {
      flex: 1;
      border: none;
      background: var(--bg);
      width: 100%;
      display: block;
      /* leave room for bottom nav */
      margin-bottom: 0;
    }
    /* Nav + mini player at bottom */
    .shell-bottom {
      flex-shrink: 0;
      background: rgba(17,17,19,.97);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-top: 1px solid var(--border);
      position: relative;
      z-index: 100;
    }
  </style>
</head>
<body>
<div class="shell">

  <!-- Page content iframe -->
  <iframe
    class="shell-frame"
    id="main-frame"
    src="<?= $iframeUrl ?>"
    title="Content"
    allow="autoplay"
  ></iframe>

  <!-- Persistent bottom bar (audio lives here) -->
  <div class="shell-bottom" id="shell-bottom">

    <!-- Mini player -->
    <div class="mini-player" id="mini-player" style="display:none">
      <div class="mini-progress" id="mini-prog"></div>
      <div class="mini-cover" id="mini-cover">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)">
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
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
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

    <!-- Tab nav -->
    <nav class="nav-tabs">
      <button class="nav-tab" data-page="pages/home.php"    id="tab-home">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span>Home</span>
      </button>
      <button class="nav-tab" data-page="pages/search.php"  id="tab-search">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <span>Search</span>
      </button>
      <button class="nav-tab" data-page="pages/library.php" id="tab-library">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
        </svg>
        <span>Library</span>
      </button>
    </nav>

  </div>
</div>

<!-- Fullscreen player (lives in shell, not iframe) -->
<div class="fp" id="fp">
  <div class="fp-bg-art" id="fp-bg"></div>
  <div class="fp-bg-grad"></div>
  <div class="fp-inner">

    <div class="fp-header">
      <button class="fp-icon-btn" id="fp-down">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </button>
      <span class="fp-label">Now Playing</span>
      <button class="fp-icon-btn" id="fp-more-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="5" r="2"/>
          <circle cx="12" cy="12" r="2"/>
          <circle cx="12" cy="19" r="2"/>
        </svg>
      </button>
    </div>

    <div class="fp-art-wrap">
      <div class="fp-art" id="fp-art">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
             style="width:52px;height:52px;color:#52525b">
          <path d="M9 18V5l12-2v13"/>
          <circle cx="6" cy="18" r="3"/>
          <circle cx="18" cy="16" r="3"/>
        </svg>
      </div>
    </div>

    <div class="fp-info">
      <div class="fp-info-text">
        <div class="fp-title"  id="fp-title">No track selected</div>
        <button class="fp-artist-btn" id="fp-artist" data-artist-id="">–</button>
      </div>
      <button class="fp-heart-btn" id="fp-heart">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </button>
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
      <button class="fp-ctrl" id="fp-prev">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="19 20 9 12 19 4 19 20"/>
          <line x1="5" y1="19" x2="5" y2="5"/>
        </svg>
      </button>
      <button class="fp-play" id="fp-play-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
          <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
        </svg>
      </button>
      <button class="fp-ctrl" id="fp-next">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 4 15 12 5 20 5 4"/>
          <line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
      </button>
    </div>

  </div>
</div>

<!-- Song options sheet -->
<div class="sheet-overlay" id="song-sheet-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div style="display:flex;align-items:center;gap:12px;padding:4px 16px 14px;border-bottom:1px solid var(--border)">
      <div id="ss-cover" style="width:46px;height:46px;border-radius:8px;background:var(--bg-card);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
      </div>
      <div style="min-width:0">
        <div id="ss-title"  style="font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
        <div id="ss-artist" style="font-size:13px;color:var(--t2);margin-top:2px"></div>
      </div>
    </div>
    <div class="sheet-item" id="ss-add-pl">
      <div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
      <div class="sheet-item-name">Add to playlist</div>
    </div>
    <div class="sheet-item" id="ss-artist-link">
      <div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
      <div class="sheet-item-name">Go to artist</div>
    </div>
    <div class="sheet-item" id="ss-edit-link" style="display:none">
      <div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
      <div class="sheet-item-name">Edit metadata</div>
    </div>
  </div>
</div>

<!-- Playlist picker sheet -->
<div class="sheet-overlay" id="pl-sheet" style="z-index:320">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Add to Playlist</div>
    <div id="pl-sheet-list"></div>
    <div class="sheet-item" id="pl-new-item">
      <div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
      <div class="sheet-item-name">New Playlist</div>
    </div>
  </div>
</div>

<audio id="c-audio" preload="none"></audio>

<script>
window.CUMU_BASE      = <?= json_encode($b) ?>;
window.CUMU_ADMIN     = <?= $isAdmin ?>;
window.CUMU_PUBLISHER = <?= $isPub ?>;

/* ── iframe navigation ─────────────────────────────────────────────────── */
var frame    = document.getElementById('main-frame');
var curPage  = <?= json_encode($pageBase) ?>;

function navTo(page, query) {
  var url = window.CUMU_BASE + '/' + page + (query ? '?' + query : '');
  var sep = url.indexOf('?') >= 0 ? '&' : '?';
  frame.src = url + sep + 'frame=1';
  curPage = page;
  updateTabActive(page);
  // tell the iframe about the shell (for playAt calls)
}

function updateTabActive(page) {
  document.querySelectorAll('.nav-tab').forEach(function(t){
    t.classList.toggle('active', t.dataset.page === page);
  });
  // Bold/fill active icon
  document.querySelectorAll('.nav-tab').forEach(function(t){
    var svg = t.querySelector('svg');
    if (!svg) return;
    if (t.dataset.page === page) {
      svg.setAttribute('fill', 'currentColor');
    } else {
      svg.setAttribute('fill', 'none');
    }
  });
}

document.querySelectorAll('.nav-tab').forEach(function(tab){
  tab.addEventListener('click', function(){ navTo(tab.dataset.page); });
});

// Handle links inside iframe that should navigate within the shell
window.navTo = navTo; // expose globally for shell_player.js

window.addEventListener('message', function(e){
  if (!e.data || typeof e.data !== 'object') return;
  var d = e.data;
  if (d.type === 'navigate')      { navTo(d.page, d.query || ''); }
  if (d.type === 'play')          { window.ShellPlayer && window.ShellPlayer.receiveQueue(d.queue, d.idx); }
  if (d.type === 'openSongSheet') { window.ShellPlayer && window.ShellPlayer.openSongSheetPublic && window.ShellPlayer.openSongSheetPublic(d.data); }
  if (d.type === 'setTab')        { updateTabActive(d.page.replace(/\?.*$/,'')); }
});

updateTabActive(curPage);
</script>
<script src="<?= $b ?>/shell_player.js"></script>
</body>
</html>
