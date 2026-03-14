<?php
if (!defined('BASE_URL')) require_once __DIR__ . '/../config.php';

function layoutHead(string $title, string $extra = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title) ?> – Cumu</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <?= $extra ?>
</head>
<body>
<?php }

function layoutSidebar(string $active = 'dashboard'): void {
    $u = currentUsername() ?? 'User';
    $b = BASE_URL;
    $admin = isAdmin();
?>
<aside class="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-logo">Cu<span>mu</span></div>
  </div>
  <nav class="sidebar-nav">
    <span class="nav-section-label">Library</span>
    <a href="<?= $b ?>/pages/dashboard.php"  class="nav-link <?= $active==='dashboard' ?'active':'' ?>"><?= icon('home')    ?><span>Dashboard</span></a>
    <a href="<?= $b ?>/pages/library.php"    class="nav-link <?= $active==='library'   ?'active':'' ?>"><?= icon('music')   ?><span>All Songs</span></a>
    <a href="<?= $b ?>/pages/artists.php"    class="nav-link <?= $active==='artists'   ?'active':'' ?>"><?= icon('mic')     ?><span>Artists</span></a>
    <a href="<?= $b ?>/pages/playlists.php"  class="nav-link <?= $active==='playlists' ?'active':'' ?>"><?= icon('list')    ?><span>Playlists</span></a>

    <span class="nav-section-label">Manage</span>
    <a href="<?= $b ?>/pages/upload.php"     class="nav-link <?= $active==='upload'    ?'active':'' ?>"><?= icon('upload')  ?><span>Upload Music</span></a>
    <a href="<?= $b ?>/pages/indexer.php"    class="nav-link <?= $active==='indexer'   ?'active':'' ?>"><?= icon('refresh') ?><span>Re-Index</span></a>
    <?php if ($admin): ?>
    <a href="<?= $b ?>/pages/admin.php"      class="nav-link <?= $active==='admin'     ?'active':'' ?>"><?= icon('users')   ?><span>Users</span></a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-foot">
    <div class="user-badge">
      <div class="user-avatar"><?= strtoupper(substr(h($u),0,1)) ?></div>
      <div>
        <div class="user-name"><?= h($u) ?></div>
        <?php if ($admin): ?><div class="user-role">Admin</div><?php endif; ?>
      </div>
    </div>
    <a href="<?= $b ?>/backend/logout.php" class="nav-link" style="margin-top:4px"><?= icon('logout') ?><span>Sign out</span></a>
  </div>
</aside>
<?php }

function layoutPlayerBar(): void { ?>
<audio id="cumu-audio" preload="none"></audio>
<div class="player-bar">
  <div class="player-track">
    <div class="player-thumb" id="player-thumb">
      <div class="player-thumb-ph"><?= icon('note') ?></div>
      <img id="player-thumb-img" style="display:none" alt="">
    </div>
    <div class="player-meta">
      <div class="player-song-title"  id="player-title">No track selected</div>
      <div class="player-song-artist" id="player-artist">–</div>
    </div>
  </div>
  <div class="player-controls">
    <div class="player-btn-row">
      <button class="ctrl-btn" id="btn-prev"><?= icon('skip-prev') ?></button>
      <button class="ctrl-btn play-pause" id="btn-pp">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>
      </button>
      <button class="ctrl-btn" id="btn-next"><?= icon('skip-next') ?></button>
    </div>
    <div class="player-progress">
      <span class="time-label" id="time-cur">0:00</span>
      <div class="progress-track" id="progress-track"><div class="progress-fill" id="progress-fill"></div></div>
      <span class="time-label right" id="time-dur">0:00</span>
    </div>
  </div>
  <div class="player-volume"><?= icon('volume') ?><input type="range" class="volume-slider" id="vol" min="0" max="100" value="85"></div>
</div>
<script>window.CUMU_BASE=<?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/player.js"></script>
<?php }
