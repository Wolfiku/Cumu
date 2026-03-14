<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=$getDB=getDB();$b=BASE_URL;$uid=currentUserId();

$pls=$db->prepare('SELECT p.*,COUNT(ps.song_id) AS song_count FROM playlists p LEFT JOIN playlist_songs ps ON ps.playlist_id=p.id WHERE p.user_id=? GROUP BY p.id ORDER BY p.created_at DESC');
$pls->execute([$uid]);$playlists=$pls->fetchAll();

layoutHead('Playlists');
?>
<div class="app-wrap">
<?php layoutSidebar('playlists');?>
<div class="main-content">
  <header class="page-header">
    <h1 class="page-title">Playlists</h1>
    <button class="btn btn-primary" id="new-pl-btn" style="width:auto;padding:9px 16px"><?=icon('plus')?> New Playlist</button>
  </header>
  <main class="page-body">

    <!-- Create playlist modal -->
    <div id="new-pl-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;display:none;align-items:center;justify-content:center">
      <div style="background:#fff;border-radius:var(--radius-lg);padding:32px;width:100%;max-width:380px;box-shadow:var(--shadow-lg)">
        <div style="font-size:18px;font-weight:700;margin-bottom:20px">New Playlist</div>
        <div id="pl-error" class="alert alert-error" style="display:none"></div>
        <div class="form-group"><label>Name</label><input type="text" id="pl-name" placeholder="My playlist..." maxlength="100" autofocus></div>
        <div style="display:flex;gap:10px;margin-top:4px">
          <button class="btn btn-primary" id="pl-create-btn" style="flex:1">Create</button>
          <button class="btn btn-secondary" id="pl-cancel-btn" style="flex:1;width:auto">Cancel</button>
        </div>
      </div>
    </div>

    <?php if(empty($playlists)):?>
      <div class="empty-state"><?=icon('list')?>
        <div class="empty-state-title">No playlists yet</div>
        <div class="empty-state-text">Create a playlist with the button above.</div>
      </div>
    <?php else:?>
      <div class="song-table-wrap">
        <table class="song-table">
          <thead><tr><th>Name</th><th>Songs</th><th>Created</th><th></th></tr></thead>
          <tbody>
          <?php foreach($playlists as $pl):?>
            <tr>
              <td><a href="<?=$b?>/pages/playlist.php?id=<?=(int)$pl['id']?>" style="font-weight:600;color:var(--text)"><?=h($pl['name'])?></a></td>
              <td style="color:var(--text-muted)"><?=(int)$pl['song_count']?> song<?=$pl['song_count']!=1?'s':''?></td>
              <td style="color:var(--text-faint);font-size:13px"><?=h(date('d M Y',strtotime($pl['created_at'])))?></td>
              <td style="text-align:right">
                <button class="btn btn-secondary del-pl-btn" data-id="<?=(int)$pl['id']?>" data-name="<?=h($pl['name'])?>" style="font-size:12px;padding:6px 12px;display:inline-flex;align-items:center;gap:5px"><?=icon('trash')?> Delete</button>
              </td>
            </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
    <?php endif;?>
  </main>
</div>
<?php layoutPlayerBar();?>
</div>
<script>
const modal=document.getElementById('new-pl-modal');
document.getElementById('new-pl-btn').onclick=()=>{modal.style.display='flex';document.getElementById('pl-name').focus();};
document.getElementById('pl-cancel-btn').onclick=()=>{modal.style.display='none';};
modal.addEventListener('click',e=>{if(e.target===modal)modal.style.display='none';});

document.getElementById('pl-create-btn').onclick=async()=>{
  const name=document.getElementById('pl-name').value.trim();
  const err=document.getElementById('pl-error');
  if(!name){err.textContent='Name required.';err.style.display='block';return;}
  const r=await fetch(window.CUMU_BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_playlist',name})});
  const d=await r.json();
  if(d.ok){location.href=window.CUMU_BASE+'/pages/playlist.php?id='+d.data.id;}
  else{err.textContent=d.error;err.style.display='block';}
};

document.querySelectorAll('.del-pl-btn').forEach(btn=>{
  btn.onclick=async()=>{
    if(!confirm('Delete playlist "'+btn.dataset.name+'"?'))return;
    const r=await fetch(window.CUMU_BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete_playlist',playlist_id:btn.dataset.id})});
    const d=await r.json();
    if(d.ok)location.reload();else alert(d.error);
  };
});
</script>
</body></html>
