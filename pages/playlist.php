<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=getDB();$b=BASE_URL;$uid=currentUserId();
$pid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$pid){header('Location:'.$b.'/pages/library.php');exit;}
$pq=$db->prepare('SELECT * FROM playlists WHERE id=? LIMIT 1');$pq->execute([$pid]);$playlist=$pq->fetch();
if(!$playlist||($playlist['user_id']!==$uid&&!isAdmin())){header('Location:'.$b.'/pages/library.php');exit;}
$sq=$db->prepare('SELECT s.id,s.title,s.duration,a.name AS artist,al.name AS album,al.cover,a.id AS artist_id,ps.position FROM playlist_songs ps JOIN songs s ON s.id=ps.song_id LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE ps.playlist_id=? ORDER BY ps.position,ps.added_at');
$sq->execute([$pid]);$songs=$sq->fetchAll();
appOpen(h($playlist['name']));
?>
<div style="position:relative">
  <button onclick="history.back()" style="position:absolute;top:calc(env(safe-area-inset-top,0px)+10px);left:10px;z-index:5;width:36px;height:36px;background:rgba(0,0,0,.4);border:none;border-radius:50%;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <div class="album-hero" style="padding-top:calc(env(safe-area-inset-top,0px)+48px)">
    <div class="album-art-lg" style="background:linear-gradient(135deg,#1e1e1e,#2a2a2a)">
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </div>
    <div class="album-title"><?=h($playlist['name'])?></div>
    <div class="album-meta">Playlist · <?=count($songs)?> song<?=count($songs)!=1?'s':''?></div>
  </div>
</div>
<?php if(empty($songs)):?>
  <div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
    <div class="empty-state-title">Empty playlist</div>
    <div class="empty-state-text">Browse songs and tap ··· to add them here.</div>
  </div>
<?php else:?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 20px 4px">
    <div style="font-size:13px;color:var(--tf)"><?=count($songs)?> tracks</div>
    <button class="play-all" id="play-all-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg></button>
  </div>
  <?php foreach($songs as $i=>$s):$cover=$s['cover']?$b.'/'.$s['cover']:'';?>
    <div class="song-row" data-id="<?=(int)$s['id']?>" data-idx="<?=$i?>" data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>" data-title="<?=h($s['title'])?>" data-artist="<?=h($s['artist']??'')?>" data-album="<?=h($s['album']??'')?>" data-cover="<?=h($cover)?>" data-artist-id="<?=(int)($s['artist_id']??0)?>">
      <div class="sr-art"><?php if($cover):?><img src="<?=h($cover)?>" alt="" loading="lazy"><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg><?php endif;?></div>
      <div class="sr-info"><div class="sr-title"><?=h($s['title'])?></div><div class="sr-artist"><?=h($s['artist']??'')?></div></div>
      <button class="sr-action rem-btn" data-sid="<?=(int)$s['id']?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
    </div>
  <?php endforeach;?>
<?php endif;?>
<div style="height:8px"></div>
<script>
document.getElementById('play-all-btn')?.addEventListener('click',()=>{const r=document.querySelectorAll('.song-row[data-id]');if(r.length){Cumu.buildQueue(r);Cumu.playAt(0);}});
document.querySelectorAll('.rem-btn').forEach(btn=>{btn.addEventListener('click',async e=>{e.stopPropagation();const r=await fetch(window.CUMU_BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove_song',playlist_id:<?=$pid?>,song_id:parseInt(btn.dataset.sid)})});const d=await r.json();if(d.ok)btn.closest('.song-row').remove();});});
</script>
<?php appClose('library');?>
