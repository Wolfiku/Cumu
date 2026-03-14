<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=$getDB=getDB();$uid=currentUserId();$b=BASE_URL;
$hour=(int)date('G');
$greeting=$hour<12?'Good morning':($hour<18?'Good afternoon':'Good evening');
$recent=$db->query('SELECT s.id,s.title,a.name AS artist,al.name AS album,al.cover,a.id AS artist_id FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id ORDER BY s.created_at DESC LIMIT 20')->fetchAll();
$recentAlbums=$db->query('SELECT al.id,al.name,al.cover,a.name AS artist,a.id AS artist_id FROM albums al LEFT JOIN artists a ON a.id=al.artist_id ORDER BY al.created_at DESC LIMIT 8')->fetchAll();
$pls=$db->prepare('SELECT id,name,cover FROM playlists WHERE user_id=? ORDER BY created_at DESC LIMIT 4');$pls->execute([$uid]);$playlists=$pls->fetchAll();
$totalSongs=(int)$db->query('SELECT COUNT(*) FROM songs')->fetchColumn();
appOpen(h($greeting));
?>
<div class="page-header" style="padding-top:calc(env(safe-area-inset-top,20px) + 12px);padding-bottom:0">
  <div class="page-header-row">
    <div style="font-size:22px;font-weight:800"><?=h($greeting)?></div>
    <?php if(isAdmin()):?>
    <a href="<?=$b?>/pages/admin.php" style="width:32px;height:32px;border-radius:50%;background:var(--bg-card);display:flex;align-items:center;justify-content:center;color:var(--t2)">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </a>
    <?php endif;?>
  </div>
</div>
<?php if(!empty($playlists)||!empty($recentAlbums)):
  $items=array_merge(array_map(fn($p)=>['url'=>$b.'/pages/playlist.php?id='.$p['id'],'name'=>$p['name'],'cover'=>$p['cover']?$b.'/'.$p['cover']:''],$playlists),array_map(fn($al)=>['url'=>$b.'/pages/album.php?id='.$al['id'],'name'=>$al['name'],'cover'=>$al['cover']?$b.'/'.$al['cover']:''],$recentAlbums));
?>
<div class="quick-grid" style="padding:16px 16px 4px">
  <?php foreach(array_slice($items,0,6) as $item):?>
    <a href="<?=h($item['url'])?>" class="q-item">
      <div class="q-item-art"><?php if($item['cover']):?><img src="<?=h($item['cover'])?>" alt=""><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg><?php endif;?></div>
      <div class="q-item-name"><?=h($item['name'])?></div>
    </a>
  <?php endforeach;?>
</div>
<?php endif;?>
<?php if(!empty($recentAlbums)):?>
<div class="section-label">Recently Added</div>
<div class="recent-row" style="padding-bottom:8px">
  <?php foreach($recentAlbums as $al):$cover=$al['cover']?$b.'/'.$al['cover']:'';?>
    <a href="<?=$b?>/pages/album.php?id=<?=(int)$al['id']?>" class="r-card">
      <div class="r-card-art"><?php if($cover):?><img src="<?=h($cover)?>" alt=""><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg><?php endif;?></div>
      <div class="r-card-title"><?=h($al['name'])?></div>
      <div class="r-card-sub"><?=h($al['artist'])?></div>
    </a>
  <?php endforeach;?>
</div>
<?php endif;?>
<?php if(!empty($recent)):?>
<div class="section-label">Songs <span style="font-size:13px;font-weight:500;color:var(--tf)"><?=$totalSongs?> total</span></div>
<?php foreach($recent as $i=>$s): songRow($s,$i,$b); endforeach;?>
<div style="padding:16px;text-align:center"><a href="<?=$b?>/pages/search.php" style="font-size:14px;font-weight:600;color:var(--accent)">Search all songs →</a></div>
<?php else:?>
<div class="empty-state" style="padding-top:48px">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
  <div class="empty-state-title">No music yet</div>
  <div class="empty-state-text"><?php if(isAdmin()):?><a href="<?=$b?>/pages/upload.php" style="color:var(--accent)">Upload music</a> or drop files in <code>music/</code> and <a href="<?=$b?>/pages/indexer.php" style="color:var(--accent)">re-index</a>.<?php else:?>Ask your admin to upload music.<?php endif;?></div>
</div>
<?php endif;?>
<?php appClose('home');?>
