<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=getDB();$b=BASE_URL;
$q=trim($_GET['q']??'');$songs=[];$artists=[];$albums=[];
if($q!==''){
    $like='%'.$q.'%';
    $ss=$db->prepare('SELECT s.id,s.title,s.duration,a.name AS artist,al.name AS album,al.cover,a.id AS artist_id FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id WHERE s.title LIKE ? OR a.name LIKE ? OR al.name LIKE ? ORDER BY s.title LIMIT 40');$ss->execute([$like,$like,$like]);$songs=$ss->fetchAll();
    $as=$db->prepare('SELECT * FROM artists WHERE name LIKE ? ORDER BY name LIMIT 10');$as->execute([$like]);$artists=$as->fetchAll();
    $als=$db->prepare('SELECT al.*,a.name AS artist_name FROM albums al LEFT JOIN artists a ON a.id=al.artist_id WHERE al.name LIKE ? OR a.name LIKE ? ORDER BY al.name LIMIT 10');$als->execute([$like,$like]);$albums=$als->fetchAll();
}
$allArtists=[];
if($q==='') $allArtists=$db->query('SELECT id,name,image FROM artists ORDER BY name LIMIT 30')->fetchAll();
appOpen('Search');
?>
<div style="padding-top:calc(env(safe-area-inset-top,20px) + 4px)">
<div class="search-bar-wrap">
  <form method="GET" style="margin:0">
    <div class="search-bar">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" name="q" value="<?=h($q)?>" placeholder="Songs, artists, albums..." autofocus autocomplete="off" autocorrect="off" spellcheck="false">
    </div>
  </form>
</div>
<?php if($q===''):?>
  <?php if(!empty($allArtists)):?>
    <div class="section-label">Artists</div>
    <div style="padding:0 16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:12px">
      <?php foreach($allArtists as $a):$img=$a['image']?$b.'/'.$a['image']:'';?>
        <a href="<?=$b?>/pages/artist.php?id=<?=(int)$a['id']?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;text-decoration:none">
          <div style="width:72px;height:72px;border-radius:50%;background:var(--bg-card);overflow:hidden;display:flex;align-items:center;justify-content:center;border:1px solid var(--border)">
            <?php if($img):?><img src="<?=h($img)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><span style="font-size:24px;font-weight:800;color:var(--tf)"><?=strtoupper(substr(h($a['name']),0,1))?></span><?php endif;?>
          </div>
          <div style="font-size:12px;font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80px"><?=h($a['name'])?></div>
        </a>
      <?php endforeach;?>
    </div>
  <?php else:?>
    <div class="empty-state" style="padding-top:80px">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <div class="empty-state-title">Search Cumu</div>
      <div class="empty-state-text">Find songs, artists and albums.</div>
    </div>
  <?php endif;?>
<?php else:?>
  <?php if(empty($songs)&&empty($artists)&&empty($albums)):?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <div class="empty-state-title">No results</div>
      <div class="empty-state-text">No results for "<?=h($q)?>"</div>
    </div>
  <?php endif;?>
  <?php if(!empty($artists)):?>
    <div class="section-label-sm" style="padding-top:12px">Artists</div>
    <div style="padding:0 16px">
      <?php foreach($artists as $a):$img=$a['image']?$b.'/'.$a['image']:'';?>
        <a href="<?=$b?>/pages/artist.php?id=<?=(int)$a['id']?>" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);text-decoration:none">
          <div style="width:48px;height:48px;border-radius:50%;background:var(--bg-card);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?php if($img):?><img src="<?=h($img)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><span style="font-size:18px;font-weight:800;color:var(--tf)"><?=strtoupper(substr(h($a['name']),0,1))?></span><?php endif;?>
          </div>
          <div><div style="font-size:15px;font-weight:600"><?=h($a['name'])?></div><div style="font-size:13px;color:var(--t2);margin-top:2px">Artist</div></div>
        </a>
      <?php endforeach;?>
    </div>
  <?php endif;?>
  <?php if(!empty($albums)):?>
    <div class="section-label-sm">Albums</div>
    <div style="padding:0 16px">
      <?php foreach($albums as $al):$cover=$al['cover']?$b.'/'.$al['cover']:'';?>
        <a href="<?=$b?>/pages/album.php?id=<?=(int)$al['id']?>" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);text-decoration:none">
          <div style="width:48px;height:48px;border-radius:var(--r);background:var(--bg-card);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?php if($cover):?><img src="<?=h($cover)?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else:?><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg><?php endif;?>
          </div>
          <div><div style="font-size:15px;font-weight:600"><?=h($al['name'])?></div><div style="font-size:13px;color:var(--t2);margin-top:2px"><?=h($al['artist_name']??'')?></div></div>
        </a>
      <?php endforeach;?>
    </div>
  <?php endif;?>
  <?php if(!empty($songs)):?>
    <div class="section-label-sm">Songs</div>
    <?php foreach($songs as $i=>$s): songRow($s,$i,$b); endforeach;?>
  <?php endif;?>
<?php endif;?>
</div>
<?php appClose('search');?>
