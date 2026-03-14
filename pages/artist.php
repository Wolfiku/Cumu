<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db = getDB(); $b = BASE_URL;
$aid = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$aid){header('Location:'.$b.'/pages/artists.php');exit;}

$ar=$db->prepare('SELECT * FROM artists WHERE id=? LIMIT 1');$ar->execute([$aid]);$artist=$ar->fetch();
if(!$artist){header('Location:'.$b.'/pages/artists.php');exit;}

$albums=$db->prepare('SELECT al.*,COUNT(s.id) AS song_count FROM albums al LEFT JOIN songs s ON s.album_id=al.id WHERE al.artist_id=? GROUP BY al.id ORDER BY al.year,al.name');
$albums->execute([$aid]);$albums=$albums->fetchAll();

$songs=$db->prepare('SELECT s.id,s.title,s.duration,s.track_num,al.name AS album,al.cover,al.id AS album_id FROM songs s LEFT JOIN albums al ON al.id=s.album_id WHERE s.artist_id=? ORDER BY al.name,s.track_num,s.title');
$songs->execute([$aid]);$songs=$songs->fetchAll();

layoutHead(h($artist['name']));
?>
<div class="app-wrap">
<?php layoutSidebar('artists');?>
<div class="main-content">
  <header class="page-header">
    <a href="<?=$b?>/pages/artists.php" style="color:var(--text-muted);font-size:13px;display:flex;align-items:center;gap:4px">Artists <?=icon('chevron-right')?></a>
    <h1 class="page-title"><?=h($artist['name'])?></h1>
  </header>
  <main class="page-body">
    <!-- Artist header -->
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:32px;padding:24px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--radius-lg)">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--accent-soft);border:2px solid var(--accent-mid);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:var(--accent);flex-shrink:0">
        <?=strtoupper(substr(h($artist['name']),0,1))?>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;letter-spacing:-0.5px"><?=h($artist['name'])?></div>
        <div style="font-size:13px;color:var(--text-faint);margin-top:4px"><?=count($albums)?> album<?=count($albums)!=1?'s':''?> · <?=count($songs)?> song<?=count($songs)!=1?'s':''?></div>
      </div>
    </div>

    <!-- Albums -->
    <?php if(!empty($albums)):?>
      <div class="section-title" style="margin-bottom:14px">Albums</div>
      <div class="albums-row" style="margin-bottom:32px">
        <?php foreach($albums as $al):$cover=$al['cover']?$b.'/'.$al['cover']:'';?>
          <a href="<?=$b?>/pages/album.php?id=<?=(int)$al['id']?>" class="album-tile">
            <?php if($cover):?><img src="<?=h($cover)?>" alt="" class="album-tile-img">
            <?php else:?><div class="album-tile-img album-tile-ph"><?=icon('disc')?></div><?php endif;?>
            <div class="album-tile-name"><?=h($al['name'])?></div>
            <div class="album-tile-meta"><?=(int)$al['song_count']?> tracks<?=$al['year']?' · '.$al['year']:''?></div>
          </a>
        <?php endforeach;?>
      </div>
    <?php endif;?>

    <!-- All songs -->
    <div class="section-title" style="margin-bottom:12px">All Tracks</div>
    <div class="song-table-wrap">
      <table class="song-table" id="song-table"><thead><tr><th style="width:46px">#</th><th>Title</th><th class="col-album">Album</th><th style="width:70px;text-align:right">Time</th><th style="width:44px"></th></tr></thead>
      <tbody>
      <?php foreach($songs as $i=>$s):$cover=$s['cover']?$b.'/'.$s['cover']:'';?>
        <tr class="song-row" data-id="<?=(int)$s['id']?>" data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>" data-title="<?=h($s['title'])?>" data-artist="<?=h($artist['name'])?>" data-album="<?=h($s['album']??'')?>" data-cover="<?=h($cover)?>" data-index="<?=$i?>">
          <td><span class="song-num"><?=$s['track_num']?:$i+1?></span><button class="song-play-btn"><?=icon('play')?></button></td>
          <td><div class="song-info">
            <?php if($cover):?><img class="song-cover" src="<?=h($cover)?>" alt="" loading="lazy">
            <?php else:?><div class="song-cover-placeholder"><?=icon('note')?></div><?php endif;?>
            <div><div class="song-title"><?=h($s['title'])?></div></div>
          </div></td>
          <td class="col-album"><a href="<?=$b?>/pages/album.php?id=<?=(int)$s['album_id']?>" style="color:var(--text-muted);font-size:13px"><?=h($s['album']??'')?></a></td>
          <td style="text-align:right"><span class="song-duration"><?=fmtDuration((int)$s['duration'])?></span></td>
        </tr>
      <?php endforeach;?>
      </tbody></table>
    </div>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
