<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=getDB();$b=BASE_URL;
$alid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$alid){header('Location:'.$b.'/pages/search.php');exit;}
$aq=$db->prepare('SELECT al.*,a.name AS artist_name,a.id AS artist_id FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.id=? LIMIT 1');$aq->execute([$alid]);$album=$aq->fetch();
if(!$album){header('Location:'.$b.'/pages/search.php');exit;}
$sq=$db->prepare('SELECT s.id,s.title,s.duration,s.track_num,? AS artist,? AS artist_id,? AS album,? AS cover FROM songs s WHERE s.album_id=? ORDER BY s.track_num,s.title');$sq->execute([$album['artist_name'],$album['artist_id'],$album['name'],$album['cover'],$alid]);$songs=$sq->fetchAll();
$cover=$album['cover']?$b.'/'.$album['cover']:'';
appOpen(h($album['name']));
?>
<div style="position:relative">
  <button onclick="history.back()" style="position:absolute;top:calc(env(safe-area-inset-top,0px)+10px);left:10px;z-index:5;width:36px;height:36px;background:rgba(0,0,0,.5);border:none;border-radius:50%;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <div class="album-hero" style="padding-top:calc(env(safe-area-inset-top,0px)+48px)">
    <div class="album-art-lg"><?php if($cover):?><img src="<?=h($cover)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg><?php endif;?></div>
    <div class="album-title"><?=h($album['name'])?></div>
    <a href="<?=$b?>/pages/artist.php?id=<?=(int)$album['artist_id']?>" class="album-artist"><?=h($album['artist_name'])?></a>
    <div class="album-meta"><?=count($songs)?> track<?=count($songs)!=1?'s':''?><?=$album['year']?' · '.$album['year']:''?></div>
  </div>
</div>
<div style="display:flex;align-items:center;justify-content:flex-end;padding:8px 20px 4px">
  <button class="play-all" id="play-all-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg></button>
</div>
<?php foreach($songs as $i=>$s): songRow($s,$i,$b,false,true); endforeach;?>
<div style="height:8px"></div>
<script>document.getElementById('play-all-btn')?.addEventListener('click',()=>{const r=document.querySelectorAll('.song-row[data-id]');if(r.length){Cumu.buildQueue(r);Cumu.playAt(0);}});</script>
<?php appClose('search');?>
