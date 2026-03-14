<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=getDB();$b=BASE_URL;
$aid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$aid){header('Location:'.$b.'/pages/search.php');exit;}
$ar=$db->prepare('SELECT * FROM artists WHERE id=? LIMIT 1');$ar->execute([$aid]);$artist=$ar->fetch();
if(!$artist){header('Location:'.$b.'/pages/search.php');exit;}
$albums=$db->prepare('SELECT al.*,COUNT(s.id) AS song_count FROM albums al LEFT JOIN songs s ON s.album_id=al.id WHERE al.artist_id=? GROUP BY al.id ORDER BY al.year DESC,al.name');$albums->execute([$aid]);$albums=$albums->fetchAll();
$songs=$db->prepare('SELECT s.id,s.title,s.duration,s.track_num,al.name AS album,al.cover,al.id AS album_id,? AS artist,? AS artist_id FROM songs s LEFT JOIN albums al ON al.id=s.album_id WHERE s.artist_id=? ORDER BY al.name,s.track_num,s.title');$songs->execute([$artist['name'],$aid,$aid]);$songs=$songs->fetchAll();
$img=$artist['image']?$b.'/'.$artist['image']:'';
appOpen(h($artist['name']));
?>
<div class="artist-hero" style="position:relative">
  <button class="back-btn" onclick="history.back()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <?php if($img):?><img src="<?=h($img)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><div class="artist-hero-ph"><?=strtoupper(substr(h($artist['name']),0,1))?></div><?php endif;?>
  <div class="artist-hero-overlay"></div>
  <div class="artist-hero-name"><?=h($artist['name'])?></div>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 8px">
  <div style="font-size:13px;color:var(--tf)"><?=count($songs)?> song<?=count($songs)!=1?'s':''?> · <?=count($albums)?> album<?=count($albums)!=1?'s':''?></div>
  <button class="play-all" id="play-all-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg></button>
</div>
<?php if(!empty($albums)):?>
<div class="section-label-sm">Albums</div>
<div class="recent-row" style="padding-bottom:16px">
  <?php foreach($albums as $al):$cover=$al['cover']?$b.'/'.$al['cover']:'';?>
    <a href="<?=$b?>/pages/album.php?id=<?=(int)$al['id']?>" class="r-card">
      <div class="r-card-art"><?php if($cover):?><img src="<?=h($cover)?>" alt=""><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg><?php endif;?></div>
      <div class="r-card-title"><?=h($al['name'])?></div>
      <div class="r-card-sub"><?=$al['year']?:''?></div>
    </a>
  <?php endforeach;?>
</div>
<?php endif;?>
<div class="section-label-sm">Songs</div>
<?php foreach($songs as $i=>$s): songRow($s,$i,$b,true,false); endforeach;?>
<div style="height:8px"></div>
<script>document.getElementById('play-all-btn')?.addEventListener('click',()=>{const r=document.querySelectorAll('.song-row[data-id]');if(r.length){Cumu.buildQueue(r);Cumu.playAt(0);}});</script>
<?php appClose('search');?>
