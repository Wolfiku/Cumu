<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../config.php';

function mobileHead(string $title, string $extra=''): void { ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?=h($title)?> – Cumu</title>
<link rel="stylesheet" href="<?=BASE_URL?>/style.css"><?=$extra?>
</head><body class="app">
<?php }

function bottomNav(string $active): void {
    $b=BASE_URL; $u=currentUsername();?>
<nav class="bottom-nav" id="bottom-nav">
  <a href="<?=$b?>/pages/home.php"    class="bn-item <?=$active==='home'   ?'active':''?>">
    <span class="bn-icon"><?=icon($active==='home'?'home':'home-o')?></span>
    <span class="bn-label">Home</span>
  </a>
  <a href="<?=$b?>/pages/search.php"  class="bn-item <?=$active==='search' ?'active':''?>">
    <span class="bn-icon"><?=icon('search')?></span>
    <span class="bn-label">Search</span>
  </a>
  <a href="<?=$b?>/pages/library.php" class="bn-item <?=$active==='library'?'active':''?>">
    <span class="bn-icon"><?=icon($active==='library'?'library':'library-o')?></span>
    <span class="bn-label">Library</span>
  </a>
</nav>
<?php }

function miniPlayer(): void { ?>
<div id="mini-player" class="mini-player hidden" onclick="Player.openFull()">
  <div class="mp-art"><div class="mp-art-ph" id="mp-art-ph"><?=icon('note')?></div><img id="mp-art-img" style="display:none" alt=""></div>
  <div class="mp-info">
    <div class="mp-title"  id="mp-title">—</div>
    <div class="mp-artist" id="mp-artist"></div>
  </div>
  <div class="mp-actions" onclick="event.stopPropagation()">
    <button class="mp-btn" id="mp-pp" onclick="Player.togglePlay()"><?=icon('play')?></button>
    <button class="mp-btn" id="mp-next" onclick="Player.next()"><?=icon('next')?></button>
  </div>
  <div class="mp-progress"><div class="mp-progress-fill" id="mp-prog"></div></div>
</div>
<?php }

function fullPlayer(): void { ?>
<div id="full-player" class="full-player" style="display:none">
  <!-- Handle bar -->
  <div class="fp-handle-wrap"><div class="fp-handle"></div></div>
  <!-- Top row -->
  <div class="fp-top">
    <button class="fp-close" onclick="Player.closeFull()"><?=icon('chevron-d')?></button>
    <div class="fp-queue-label">Now Playing</div>
    <button class="fp-more" id="fp-more-btn"><?=icon('more')?></button>
  </div>
  <!-- Art -->
  <div class="fp-art-wrap">
    <div class="fp-art" id="fp-art">
      <div class="fp-art-ph" id="fp-art-ph"><?=icon('note')?></div>
      <img id="fp-art-img" style="display:none" alt="">
    </div>
  </div>
  <!-- Meta -->
  <div class="fp-meta">
    <div class="fp-meta-left">
      <div class="fp-title"  id="fp-title">No track</div>
      <a  class="fp-artist" id="fp-artist-link" href="#"></a>
    </div>
    <button class="fp-heart" id="fp-heart"><?=icon('heart')?></button>
  </div>
  <!-- Progress -->
  <div class="fp-progress-row">
    <div class="fp-track" id="fp-track"><div class="fp-fill" id="fp-fill"></div></div>
    <div class="fp-times"><span id="fp-cur">0:00</span><span id="fp-dur">0:00</span></div>
  </div>
  <!-- Controls -->
  <div class="fp-controls">
    <button class="fp-ctrl sm" id="fp-shuffle"><?=icon('shuffle')?></button>
    <button class="fp-ctrl"    id="fp-prev"   onclick="Player.prev()"><?=icon('prev')?></button>
    <button class="fp-ctrl lg" id="fp-pp"     onclick="Player.togglePlay()"><?=icon('play')?></button>
    <button class="fp-ctrl"    id="fp-next"   onclick="Player.next()"><?=icon('next')?></button>
    <button class="fp-ctrl sm" id="fp-repeat" ><?=icon('repeat')?></button>
  </div>
  <!-- Volume -->
  <div class="fp-volume-row">
    <span style="opacity:.4;display:flex"><?=icon('volume')?></span>
    <input type="range" id="fp-vol" min="0" max="100" value="85" class="fp-vol-slider">
  </div>
</div>
<!-- Song options bottom sheet -->
<div id="song-sheet" class="bottom-sheet" style="display:none">
  <div class="bs-handle"></div>
  <div class="bs-art-row">
    <div class="bs-art" id="bs-art"></div>
    <div><div class="bs-title" id="bs-title"></div><div class="bs-artist" id="bs-artist"></div></div>
  </div>
  <div id="bs-pl-list"></div>
  <button class="bs-item" onclick="closeSheet()"><?=icon('check')?> Close</button>
</div>
<div id="sheet-overlay" class="sheet-overlay" style="display:none" onclick="closeSheet()"></div>
<audio id="cumu-audio" preload="none"></audio>
<script>window.CUMU_BASE=<?=json_encode(BASE_URL)?>;</script>
<script src="<?=BASE_URL?>/player.js"></script>
<?php }

// Admin layout (light theme, used by admin pages)
function adminHead(string $title): void { ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> – Cumu Admin</title>
<link rel="stylesheet" href="<?=BASE_URL?>/style.css">
</head><body class="admin-body">
<?php }

function adminNav(string $active=''): void { $b=BASE_URL; ?>
<nav class="admin-nav">
  <a href="<?=$b?>/pages/home.php" class="admin-logo">Cu<span>mu</span></a>
  <div class="admin-nav-links">
    <a href="<?=$b?>/pages/indexer.php"   class="<?=$active==='indexer'?'active':''?>"><?=icon('refresh')?> Re-Index</a>
    <a href="<?=$b?>/pages/upload.php"    class="<?=$active==='upload'?'active':''?>"><?=icon('upload')?> Upload</a>
    <a href="<?=$b?>/pages/admin.php"     class="<?=$active==='admin'?'active':''?>"><?=icon('users')?> Users</a>
    <a href="<?=$b?>/backend/logout.php"><?=icon('logout')?> Sign out</a>
  </div>
</nav>
<?php }
