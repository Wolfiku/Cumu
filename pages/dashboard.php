<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db  = getDB();
$uid = currentUserId();
$b   = BASE_URL;

$totalSongs     = (int)$db->query('SELECT COUNT(*) FROM songs')->fetchColumn();
$totalArtists   = (int)$db->query('SELECT COUNT(*) FROM artists')->fetchColumn();
$totalAlbums    = (int)$db->query('SELECT COUNT(*) FROM albums')->fetchColumn();
$totalPlaylists = (int)$db->prepare('SELECT COUNT(*) FROM playlists WHERE user_id=?')->execute([$uid]) ?
                  (int)($db->prepare('SELECT COUNT(*) FROM playlists WHERE user_id=?')->execute([$uid]) && ($s=$db->prepare('SELECT COUNT(*) FROM playlists WHERE user_id=?')) && $s->execute([$uid]) ? $s->fetchColumn() : 0) : 0;
$ps=$db->prepare('SELECT COUNT(*) FROM playlists WHERE user_id=?');$ps->execute([$uid]);$totalPlaylists=(int)$ps->fetchColumn();

// Recent songs (last 20 added)
$recent = $db->query('SELECT s.id,s.title,s.duration,a.name AS artist,al.name AS album,al.cover
    FROM songs s
    LEFT JOIN artists a  ON a.id=s.artist_id
    LEFT JOIN albums al  ON al.id=s.album_id
    ORDER BY s.created_at DESC LIMIT 20')->fetchAll();

$flash=$_SESSION['flash_success']??null;unset($_SESSION['flash_success']);
layoutHead('Dashboard');
?>
<div class="app-wrap">
<?php layoutSidebar('dashboard');?>
<div class="main-content">
  <header class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <div class="search-wrap"><?=icon('search')?><input type="search" class="search-input" id="search-input" placeholder="Search songs, artists..."></div>
  </header>
  <main class="page-body">
    <?php if($flash):?><div class="alert alert-success" style="margin-bottom:20px"><?=h($flash)?></div><?php endif;?>
    <div class="stats-bar">
      <div class="stat-card"><div class="stat-value"><?=$totalSongs?></div><div class="stat-label">Songs</div></div>
      <div class="stat-card"><div class="stat-value"><?=$totalArtists?></div><div class="stat-label">Artists</div></div>
      <div class="stat-card"><div class="stat-value"><?=$totalAlbums?></div><div class="stat-label">Albums</div></div>
      <div class="stat-card"><div class="stat-value"><?=$totalPlaylists?></div><div class="stat-label">Playlists</div></div>
    </div>

    <?php if($totalSongs===0):?>
      <div class="empty-state"><?=icon('note')?>
        <div class="empty-state-title">Library is empty</div>
        <div class="empty-state-text">Upload songs via <a href="<?=$b?>/pages/upload.php">Upload Music</a> or drop files into <code>music/</code> and run <a href="<?=$b?>/pages/indexer.php">Re-Index</a>.</div>
      </div>
    <?php else:?>
      <div class="section-title">Recently Added</div>
      <div class="song-table-wrap">
        <table class="song-table" id="song-table">
          <thead><tr><th style="width:46px">#</th><th>Title</th><th class="col-album">Album</th><th class="col-artist">Artist</th><th style="width:70px;text-align:right">Time</th><th style="width:44px"></th></tr></thead>
          <tbody>
          <?php foreach($recent as $i=>$s):
            $cover = $s['cover'] ? $b.'/'.$s['cover'] : '';
          ?>
            <tr class="song-row"
                data-id="<?=(int)$s['id']?>"
                data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>"
                data-title="<?=h($s['title'])?>"
                data-artist="<?=h($s['artist']??'')?>"
                data-album="<?=h($s['album']??'')?>"
                data-cover="<?=h($cover)?>"
                data-index="<?=$i?>">
              <td><span class="song-num"><?=$i+1?></span><button class="song-play-btn"><?=icon('play')?></button></td>
              <td><div class="song-info">
                <?php if($cover):?><img class="song-cover" src="<?=h($cover)?>" alt="" loading="lazy">
                <?php else:?><div class="song-cover-placeholder"><?=icon('note')?></div><?php endif;?>
                <div><div class="song-title"><?=h($s['title'])?></div><div class="song-artist"><?=h($s['artist']??'Unknown')?></div></div>
              </div></td>
              <td class="col-album song-album-cell"><?=h($s['album']??'')?></td>
              <td class="col-artist" style="color:var(--text-muted);font-size:13px"><?=h($s['artist']??'')?></td>
              <td style="text-align:right"><span class="song-duration"><?=fmtDuration((int)$s['duration'])?></span></td>
              <td><button class="add-pl-btn" data-song-id="<?=(int)$s['id']?>" title="Add to playlist">+ PL</button></td>
            </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <?php if($totalSongs>20):?>
        <div style="text-align:center;margin-top:16px"><a href="<?=$b?>/pages/library.php" class="btn btn-secondary" style="width:auto">View all <?=$totalSongs?> songs <?=icon('chevron-right')?></a></div>
      <?php endif;?>
    <?php endif;?>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
