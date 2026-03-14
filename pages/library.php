<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db  = getDB();
$uid = currentUserId();
$b   = BASE_URL;

// User's playlists
$pls = $db->prepare('SELECT p.*,COUNT(ps.song_id) AS song_count FROM playlists p LEFT JOIN playlist_songs ps ON ps.playlist_id=p.id WHERE p.user_id=? GROUP BY p.id ORDER BY p.created_at DESC');
$pls->execute([$uid]); $playlists = $pls->fetchAll();

appOpen('Library');
?>

<div class="page-header" style="padding-top:calc(env(safe-area-inset-top,20px) + 12px)">
  <div class="page-header-row">
    <h1>Your Library</h1>
    <button id="new-pl-btn" style="width:32px;height:32px;border-radius:50%;background:var(--bg-card);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t1)">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>
  </div>
</div>

<?php if (empty($playlists)): ?>
  <div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
    <div class="empty-state-title">Create your first playlist</div>
    <div class="empty-state-text">Tap the + button to create a playlist.</div>
  </div>
<?php else: ?>
  <div class="lib-list">
    <?php foreach($playlists as $pl):
      $cover = $pl['cover'] ? $b.'/'.$pl['cover'] : '';
    ?>
      <a href="<?= $b ?>/pages/playlist.php?id=<?= (int)$pl['id'] ?>" class="lib-item">
        <div class="lib-item-art">
          <?php if($cover):?><img src="<?= h($cover) ?>" alt="">
          <?php else:?>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          <?php endif;?>
        </div>
        <div class="lib-item-info">
          <div class="lib-item-name"><?= h($pl['name']) ?></div>
          <div class="lib-item-sub">Playlist · <?= (int)$pl['song_count'] ?> song<?= $pl['song_count']!=1?'s':'' ?></div>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf);flex-shrink:0"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    <?php endforeach;?>
  </div>
<?php endif; ?>

<!-- Create playlist sheet -->
<div class="sheet-overlay" id="new-pl-overlay">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">New Playlist</div>
    <div style="padding:0 16px 16px">
      <div id="pl-error" class="alert alert-error" style="display:none"></div>
      <div class="form-group">
        <label>Name</label>
        <input type="text" id="pl-name-input" placeholder="My playlist..." maxlength="100">
      </div>
      <button class="btn btn-primary" id="pl-create-btn">Create Playlist</button>
    </div>
  </div>
</div>

<script>
const overlay = document.getElementById('new-pl-overlay');
document.getElementById('new-pl-btn').onclick = () => {
  overlay.classList.add('open');
  setTimeout(() => document.getElementById('pl-name-input').focus(), 300);
};
overlay.addEventListener('click', e => { if(e.target===overlay) overlay.classList.remove('open'); });

document.getElementById('pl-create-btn').onclick = async () => {
  const name = document.getElementById('pl-name-input').value.trim();
  const err  = document.getElementById('pl-error');
  if (!name) { err.textContent='Name required.'; err.style.display='block'; return; }
  const r = await fetch(window.CUMU_BASE+'/backend/playlist_api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_playlist',name})});
  const d = await r.json();
  if (d.ok) { location.href = window.CUMU_BASE+'/pages/playlist.php?id='+d.data.id; }
  else { err.textContent=d.error; err.style.display='block'; }
};
</script>

<?php appClose('library'); ?>
