<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db=getDB();$b=BASE_URL;$uid=currentUserId();
$pid=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$pid){header('Location:'.$b.'/pages/playlists.php');exit;}

$pq=$db->prepare('SELECT * FROM playlists WHERE id=? LIMIT 1');$pq->execute([$pid]);$playlist=$pq->fetch();
if(!$playlist||($playlist['user_id']!==$uid&&!isAdmin())){header('Location:'.$b.'/pages/playlists.php');exit;}

$songs=$db->prepare('SELECT s.id,s.title,s.duration,a.name AS artist,al.name AS album,al.cover,ps.position
    FROM playlist_songs ps
    JOIN songs   s  ON s.id=ps.song_id
    LEFT JOIN artists a  ON a.id=s.artist_id
    LEFT JOIN albums  al ON al.id=s.album_id
    WHERE ps.playlist_id=? ORDER BY ps.position,ps.added_at');
$songs->execute([$pid]);$songs=$songs->fetchAll();

layoutHead(h($playlist['name']));
?>
<div class="app-wrap">
<?php layoutSidebar('playlists');?>
<div class="main-content">
  <header class="page-header" style="gap:12px">
    <a href="<?=$b?>/pages/playlists.php" style="color:var(--text-muted);font-size:13px;display:flex;align-items:center;gap:4px;white-space:nowrap">Playlists <?=icon('chevron-right')?></a>
    <h1 class="page-title"><?=h($playlist['name'])?></h1>
  </header>
  <main class="page-body">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:28px">
      <div style="width:80px;height:80px;border-radius:var(--radius-lg);background:var(--accent-soft);border:1px solid var(--accent-mid);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?=icon('list')?>
      </div>
      <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-faint);margin-bottom:4px">Playlist</div>
        <div style="font-size:22px;font-weight:800;letter-spacing:-0.5px"><?=h($playlist['name'])?></div>
        <div style="font-size:13px;color:var(--text-faint);margin-top:4px"><?=count($songs)?> song<?=count($songs)!=1?'s':''?></div>
      </div>
    </div>

    <?php if(empty($songs)):?>
      <div class="empty-state"><?=icon('note')?>
        <div class="empty-state-title">Playlist is empty</div>
        <div class="empty-state-text">Go to <a href="<?=$b?>/pages/library.php">All Songs</a> and use the + button to add songs here.</div>
      </div>
    <?php else:?>
      <div class="song-table-wrap">
        <table class="song-table" id="song-table">
          <thead><tr><th style="width:46px">#</th><th>Title</th><th class="col-album">Album</th><th class="col-artist">Artist</th><th style="width:70px;text-align:right">Time</th><th style="width:44px"></th></tr></thead>
          <tbody>
          <?php foreach($songs as $i=>$s):$cover=$s['cover']?$b.'/'.$s['cover']:'';?>
            <tr class="song-row" data-id="<?=(int)$s['id']?>" data-stream="<?=$b?>/backend/stream.php?id=<?=(int)$s['id']?>" data-title="<?=h($s['title'])?>" data-artist="<?=h($s['artist']??'')?>" data-album="<?=h($s['album']??'')?>" data-cover="<?=h($cover)?>" data-index="<?=$i?>">
              <td><span class="song-num"><?=$i+1?></span><button class="song-play-btn"><?=icon('play')?></button></td>
              <td><div class="song-info">
                <?php if($cover):?><img class="song-cover" src="<?=h($cover)?>" alt="" loading="lazy">
                <?php else:?><div class="song-cover-placeholder"><?=icon('note')?></div><?php endif;?>
                <div><div class="song-title"><?=h($s['title'])?></div><div class="song-artist"><?=h($s['artist']??'')?></div></div>
              </div></td>
              <td class="col-album song-album-cell"><?=h($s['album']??'')?></td>
              <td class="col-artist" style="font-size:13px;color:var(--text-muted)"><?=h($s['artist']??'')?></td>
              <td style="text-align:right"><span class="song-duration"><?=fmtDuration((int)$s['duration'])?></span></td>
              <td>
                <button class="btn-icon rem-song" data-sid="<?=(int)$s['id']?>" title="Remove from playlist" style="opacity:.5;background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;color:var(--text-muted)"><?=icon('trash')?></button>
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
document.querySelectorAll('.rem-song').forEach(btn=>{
  btn.onclick=async()=>{
    const r=await fetch(window.CUMU_BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'remove_song',playlist_id:<?=$pid?>,song_id:parseInt(btn.dataset.sid)})});
    const d=await r.json();
    if(d.ok)btn.closest('tr').remove();else alert(d.error);
  };
});
</script>
</body></html>
