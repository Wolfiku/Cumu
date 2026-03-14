<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db = getDB(); $b = BASE_URL;

$songs = $db->query('SELECT s.id,s.title,s.duration,s.track_num,a.name AS artist,al.name AS album,al.cover,a.id AS artist_id,al.id AS album_id
    FROM songs s
    LEFT JOIN artists a  ON a.id=s.artist_id
    LEFT JOIN albums  al ON al.id=s.album_id
    ORDER BY a.name,al.name,s.track_num,s.title')->fetchAll();

layoutHead('All Songs');
?>
<div class="app-wrap">
<?php layoutSidebar('library');?>
<div class="main-content">
  <header class="page-header">
    <h1 class="page-title">All Songs</h1>
    <div class="search-wrap"><?=icon('search')?><input type="search" class="search-input" id="search-input" placeholder="Search..."></div>
  </header>
  <main class="page-body">
    <?php if(empty($songs)):?>
      <div class="empty-state"><?=icon('note')?><div class="empty-state-title">No songs yet</div><div class="empty-state-text">Upload songs or run the indexer.</div></div>
    <?php else:?>
      <div class="section-title"><?=count($songs)?> songs</div>
      <div class="song-table-wrap">
        <table class="song-table" id="song-table">
          <thead><tr><th style="width:46px">#</th><th>Title</th><th class="col-album">Album</th><th class="col-artist">Artist</th><th style="width:70px;text-align:right">Time</th><th style="width:44px"></th></tr></thead>
          <tbody>
          <?php foreach($songs as $i=>$s):
            $cover=$s['cover']?$b.'/'.$s['cover']:'';
          ?>
            <tr class="song-row"
                data-id="<?=(int)$s['id']?>"
                data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>"
                data-title="<?=h($s['title'])?>" data-artist="<?=h($s['artist']??'')?>"
                data-album="<?=h($s['album']??'')?>" data-cover="<?=h($cover)?>" data-index="<?=$i?>">
              <td><span class="song-num"><?=$i+1?></span><button class="song-play-btn"><?=icon('play')?></button></td>
              <td><div class="song-info">
                <?php if($cover):?><img class="song-cover" src="<?=h($cover)?>" alt="" loading="lazy">
                <?php else:?><div class="song-cover-placeholder"><?=icon('note')?></div><?php endif;?>
                <div><div class="song-title"><?=h($s['title'])?></div><div class="song-artist"><?=h($s['artist']??'')?></div></div>
              </div></td>
              <td class="col-album"><a href="<?=$b?>/pages/album.php?id=<?=(int)$s['album_id']?>" style="color:var(--text-muted);font-size:13px"><?=h($s['album']??'')?></a></td>
              <td class="col-artist"><a href="<?=$b?>/pages/artist.php?id=<?=(int)$s['artist_id']?>" style="color:var(--text-muted);font-size:13px"><?=h($s['artist']??'')?></a></td>
              <td style="text-align:right"><span class="song-duration"><?=fmtDuration((int)$s['duration'])?></span></td>
              <td><button class="add-pl-btn" data-song-id="<?=(int)$s['id']?>" title="Add to playlist">+ PL</button></td>
            </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
    <?php endif;?>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
