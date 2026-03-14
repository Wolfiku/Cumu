<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=$getDB=getDB();$b=BASE_URL;
$alid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$alid){header('Location:'.$b.'/pages/library.php');exit;}

$aq=$db->prepare('SELECT al.*,a.name AS artist_name,a.id AS artist_id FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.id=? LIMIT 1');
$aq->execute([$alid]);$album=$aq->fetch();
if(!$album){header('Location:'.$b.'/pages/library.php');exit;}

$sq=$db->prepare('SELECT * FROM songs WHERE album_id=? ORDER BY track_num,title');$sq->execute([$alid]);$songs=$sq->fetchAll();
$cover=$album['cover']?$b.'/'.$album['cover']:'';

layoutHead(h($album['name']));
?>
<div class="app-wrap">
<?php layoutSidebar('library');?>
<div class="main-content">
  <header class="page-header" style="gap:12px">
    <a href="<?=$b?>/pages/artist.php?id=<?=(int)$album['artist_id']?>" style="color:var(--text-muted);font-size:13px;display:flex;align-items:center;gap:4px;white-space:nowrap"><?=h($album['artist_name'])?> <?=icon('chevron-right')?></a>
    <h1 class="page-title"><?=h($album['name'])?></h1>
  </header>
  <main class="page-body">
    <div style="display:flex;align-items:flex-end;gap:24px;margin-bottom:32px">
      <?php if($cover):?><img src="<?=h($cover)?>" alt="" style="width:140px;height:140px;border-radius:var(--radius-lg);object-fit:cover;border:1px solid var(--border);box-shadow:var(--shadow-lg);flex-shrink:0">
      <?php else:?><div style="width:140px;height:140px;border-radius:var(--radius-lg);background:var(--bg-muted);display:flex;align-items:center;justify-content:center;border:1px solid var(--border);flex-shrink:0"><?=icon('disc')?></div><?php endif;?>
      <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-faint);margin-bottom:6px">Album</div>
        <div style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px"><?=h($album['name'])?></div>
        <a href="<?=$b?>/pages/artist.php?id=<?=(int)$album['artist_id']?>" style="font-size:14px;font-weight:600;color:var(--accent)"><?=h($album['artist_name'])?></a>
        <div style="font-size:13px;color:var(--text-faint);margin-top:6px"><?=count($songs)?> track<?=count($songs)!=1?'s':''?><?=$album['year']?' · '.$album['year']:''?></div>
      </div>
    </div>
    <div class="song-table-wrap">
      <table class="song-table" id="song-table"><thead><tr><th style="width:46px">#</th><th>Title</th><th style="width:70px;text-align:right">Time</th></tr></thead>
      <tbody>
      <?php foreach($songs as $i=>$s):?>
        <tr class="song-row" data-id="<?=(int)$s['id']?>" data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>" data-title="<?=h($s['title'])?>" data-artist="<?=h($album['artist_name'])?>" data-album="<?=h($album['name'])?>" data-cover="<?=h($cover)?>" data-index="<?=$i?>">
          <td><span class="song-num"><?=$s['track_num']?:$i+1?></span><button class="song-play-btn"><?=icon('play')?></button></td>
          <td><div class="song-title"><?=h($s['title'])?></div></td>
          <td style="text-align:right"><span class="song-duration"><?=fmtDuration((int)$s['duration'])?></span></td>
        </tr>
      <?php endforeach;?>
      </tbody></table>
    </div>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
