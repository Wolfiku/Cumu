<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();

$db  = getDB();
$uid = currentUserId();
$b   = BASE_URL;

$pls = $db->prepare(
    'SELECT p.*, COUNT(ps.song_id) AS song_count
     FROM playlists p
     LEFT JOIN playlist_songs ps ON ps.playlist_id = p.id
     WHERE p.user_id = ?
     GROUP BY p.id
     ORDER BY p.is_favorite DESC, p.created_at DESC'
);
$pls->execute([$uid]);
$playlists = $pls->fetchAll();

try {
    $mxq = $db->query(
        'SELECT m.id, m.name, m.creator_id, u.username AS creator, COUNT(ms.song_id) AS song_count
         FROM mixtapes m
         LEFT JOIN users u ON u.id=m.creator_id
         LEFT JOIN mixtape_songs ms ON ms.mixtape_id=m.id
         GROUP BY m.id ORDER BY m.created_at DESC'
    );
    $mixtapes = $mxq->fetchAll();
} catch (Exception $e) { $mixtapes = []; }

appOpen('Library');
?>
<style>
/* ── Library page ─────────────────────────────────────── */
.lib-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: calc(env(safe-area-inset-top,20px) + 12px) 16px 12px;
}
.lib-header-title { font-size: 26px; font-weight: 800; letter-spacing: -.6px; }

.lib-add-btn {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--bg-el); border: 1px solid var(--border-m);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--t2); flex-shrink: 0;
  transition: background .12s, color .12s;
  -webkit-tap-highlight-color: transparent;
}
.lib-add-btn:active { background: var(--bg-hover); color: var(--t1); }

/* Section header */
.lib-section-head {
  padding: 16px 16px 6px;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .08em;
  color: var(--tf);
}

/* Library card — replaces lib-item with something cleaner */
.lib-card {
  display: flex; align-items: center; gap: 14px;
  padding: 10px 16px;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: background .1s;
}
.lib-card:active { background: var(--bg-el); }

.lib-card-art {
  width: 54px; height: 54px; border-radius: 10px;
  background: var(--bg-card); overflow: hidden; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border);
}
.lib-card-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
.lib-card-art.round { border-radius: 50%; }
.lib-card-art svg  { color: var(--tf); }

.lib-card-body  { flex: 1; min-width: 0; }
.lib-card-name  { font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lib-card-sub   { font-size: 13px; color: var(--t2); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.lib-card-action {
  flex-shrink: 0; width: 34px; height: 34px;
  background: none; border: none; cursor: pointer;
  color: var(--tf); display: flex; align-items: center; justify-content: center;
  border-radius: 50%; -webkit-tap-highlight-color: transparent;
  transition: background .1s, color .1s;
}
.lib-card-action:active { background: var(--bg-hover); color: var(--t1); }

/* Mixtape cassette icon */
.mx-icon-bg {
  width: 54px; height: 54px; border-radius: 10px;
  background: #1d1823;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; border: 1px solid rgba(255,255,255,.06);
}

/* Fav badge */
.fav-dot {
  display: inline-block; width: 6px; height: 6px;
  background: var(--accent); border-radius: 50%;
  margin-left: 6px; vertical-align: middle; flex-shrink:0;
}
</style>

<!-- Header -->
<div class="lib-header">
  <div class="lib-header-title">Library</div>
  <button class="lib-add-btn" id="new-pl-btn" title="New Playlist">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
  </button>
</div>

<!-- Playlists -->
<?php if (!empty($playlists)): ?>
  <div class="lib-section-head">Playlists</div>
  <?php foreach ($playlists as $pl):
    if ($pl['is_favorite'])        $cover = $b . '/assets/covers/favourite.png';
    elseif (!empty($pl['cover']))  $cover = $b . '/' . $pl['cover'];
    else                           $cover = '';
  ?>
    <div class="lib-card" onclick="window.navigate('pages/playlist.php','id=<?= (int)$pl['id'] ?>')">
      <div class="lib-card-art">
        <?php if ($cover): ?>
          <img src="<?= h($cover) ?>" alt="">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
          </svg>
        <?php endif; ?>
      </div>
      <div class="lib-card-body">
        <div class="lib-card-name">
          <?= h($pl['name']) ?>
          <?php if ($pl['is_favorite']): ?><span class="fav-dot"></span><?php endif; ?>
        </div>
        <div class="lib-card-sub">
          Playlist
          · <?= (int)$pl['song_count'] ?> song<?= $pl['song_count'] != 1 ? 's' : '' ?>
          <?php if (!empty($pl['description'])): ?>
            · <?= h(mb_strimwidth($pl['description'], 0, 32, '…')) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$pl['is_favorite']): ?>
        <button class="lib-card-action edit-pl-btn"
                data-id="<?= (int)$pl['id'] ?>"
                data-name="<?= h($pl['name']) ?>"
                data-desc="<?= h($pl['description'] ?? '') ?>"
                data-cover="<?= h($cover) ?>"
                onclick="event.stopPropagation()">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
          </svg>
        </button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="empty-state" style="padding-top:40px">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
    </svg>
    <div class="empty-state-title">No playlists yet</div>
    <div class="empty-state-text">Tap + to create your first playlist.</div>
  </div>
<?php endif; ?>

<!-- Mixtapes -->
<?php if (!empty($mixtapes)): ?>
  <div class="lib-section-head" style="margin-top:8px">Mixtapes</div>
  <?php foreach ($mixtapes as $mx): ?>
    <div class="lib-card" onclick="window.navigate('pages/mixtape.php','id=<?= (int)$mx['id'] ?>')">
      <div class="mx-icon-bg">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:#888">
          <rect x="2" y="5" width="20" height="14" rx="2.5"/>
          <circle cx="8.5" cy="12" r="2"/><circle cx="15.5" cy="12" r="2"/>
          <line x1="8.5" y1="12" x2="15.5" y2="12" stroke-width="1"/>
          <path d="M5 5v14M19 5v14" stroke-dasharray="2 2" opacity=".4"/>
        </svg>
      </div>
      <div class="lib-card-body">
        <div class="lib-card-name"><?= h($mx['name']) ?></div>
        <div class="lib-card-sub">
          Mixtape · <?= (int)$mx['song_count'] ?> song<?= $mx['song_count'] != 1 ? 's' : '' ?>
          · by <?= h($mx['creator'] ?? 'Unknown') ?>
        </div>
      </div>
      <?php if (isPublisher() && ($mx['creator_id'] === $uid || isAdmin())): ?>
        <button class="lib-card-action del-mx-btn"
                data-mxid="<?= (int)$mx['id'] ?>"
                onclick="event.stopPropagation()">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          </svg>
        </button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div style="height:8px"></div>

<!-- ── Create playlist sheet ─────────────────────────────── -->
<div class="sheet-overlay" id="new-pl-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">New Playlist</div>
    <div style="padding:0 16px 24px">
      <div id="pl-create-err" class="alert alert-error" style="display:none"></div>
      <div class="form-group">
        <label>Name</label>
        <input type="text" id="pl-name-input" placeholder="My playlist…" maxlength="100">
      </div>
      <div class="form-group">
        <label>Description <span style="font-weight:400;color:var(--tf)">(optional)</span></label>
        <input type="text" id="pl-desc-input" placeholder="Short description…" maxlength="200">
      </div>
      <button class="btn btn-primary" id="pl-create-btn" style="border-radius:10px">Create</button>
    </div>
  </div>
</div>

<!-- ── Edit playlist sheet ────────────────────────────────── -->
<div class="sheet-overlay" id="edit-pl-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Edit Playlist</div>
    <div style="padding:0 16px 24px">
      <div id="pl-edit-err" class="alert alert-error"   style="display:none"></div>
      <div id="pl-edit-ok"  class="alert alert-success" style="display:none"></div>

      <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
        <div id="edit-pl-cover-thumb"
             style="width:64px;height:64px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          </svg>
        </div>
        <label class="btn btn-secondary" style="width:auto;padding:9px 16px;font-size:13px;border-radius:10px;cursor:pointer;display:inline-flex">
          Change cover
          <input type="file" id="edit-pl-cover-input" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </div>

      <div class="form-group">
        <label>Name</label>
        <input type="text" id="edit-pl-name" maxlength="100">
      </div>
      <div class="form-group">
        <label>Description</label>
        <input type="text" id="edit-pl-desc" maxlength="200" placeholder="Optional">
      </div>
      <button class="btn btn-primary" id="edit-pl-save-btn" style="border-radius:10px">Save</button>
    </div>
  </div>
</div>

<script>
var BASE = window.CUMU_BASE || '';
var editingPlId = null;

/* ── New playlist ──────────────────────────────────────────── */
var newOv = document.getElementById('new-pl-overlay');
document.getElementById('new-pl-btn').addEventListener('click', function(){
  newOv.classList.add('open');
  setTimeout(function(){ document.getElementById('pl-name-input').focus(); }, 300);
});
newOv.addEventListener('click', function(e){ if(e.target===newOv) newOv.classList.remove('open'); });

document.getElementById('pl-create-btn').addEventListener('click', async function(){
  var name = document.getElementById('pl-name-input').value.trim();
  var desc = document.getElementById('pl-desc-input').value.trim();
  var err  = document.getElementById('pl-create-err');
  if (!name){ err.textContent='Name required.'; err.style.display='block'; return; }
  var r = await fetch(BASE+'/backend/playlist_api.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'create_playlist', name:name, description:desc})
  });
  var d = await r.json();
  if (d.ok){ window.navigate('pages/playlist.php','id='+d.data.id); newOv.classList.remove('open'); }
  else     { err.textContent=d.error; err.style.display='block'; }
});

/* ── Edit playlist ─────────────────────────────────────────── */
var editOv = document.getElementById('edit-pl-overlay');
editOv.addEventListener('click', function(e){ if(e.target===editOv) editOv.classList.remove('open'); });

document.querySelectorAll('.edit-pl-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    editingPlId = parseInt(btn.dataset.id);
    document.getElementById('edit-pl-name').value = btn.dataset.name || '';
    document.getElementById('edit-pl-desc').value = btn.dataset.desc || '';
    var thumb = document.getElementById('edit-pl-cover-thumb');
    thumb.innerHTML = btn.dataset.cover
      ? '<img src="'+btn.dataset.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg>';
    document.getElementById('pl-edit-err').style.display='none';
    document.getElementById('pl-edit-ok').style.display='none';
    editOv.classList.add('open');
  });
});

document.getElementById('edit-pl-cover-input').addEventListener('change', function(){
  var f=this.files[0]; if(!f) return;
  var r=new FileReader();
  r.onload=function(e){ document.getElementById('edit-pl-cover-thumb').innerHTML='<img src="'+e.target.result+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'; };
  r.readAsDataURL(f);
});

document.getElementById('edit-pl-save-btn').addEventListener('click', async function(){
  var btn=this; btn.textContent='Saving…'; btn.disabled=true;
  var err=document.getElementById('pl-edit-err'), ok=document.getElementById('pl-edit-ok');
  var r=await fetch(BASE+'/backend/playlist_api.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'update_playlist', playlist_id:editingPlId,
      name:document.getElementById('edit-pl-name').value.trim(),
      description:document.getElementById('edit-pl-desc').value.trim()})
  });
  var d=await r.json();
  if(!d.ok){ err.textContent=d.error; err.style.display='block'; btn.textContent='Save'; btn.disabled=false; return; }
  var cf=document.getElementById('edit-pl-cover-input').files[0];
  if(cf){
    var fd=new FormData(); fd.append('action','update_playlist_cover'); fd.append('playlist_id',editingPlId); fd.append('cover',cf);
    var r2=await fetch(BASE+'/backend/playlist_api.php',{method:'POST',body:fd});
    var d2=await r2.json();
    if(!d2.ok){ err.textContent=d2.error; err.style.display='block'; btn.textContent='Save'; btn.disabled=false; return; }
  }
  ok.textContent='Saved!'; ok.style.display='block';
  btn.textContent='Save'; btn.disabled=false;
  setTimeout(function(){ editOv.classList.remove('open'); location.reload(); }, 700);
});

/* ── Delete mixtape ────────────────────────────────────────── */
document.querySelectorAll('.del-mx-btn').forEach(function(btn){
  btn.addEventListener('click', async function(){
    if(!confirm('Delete this Mixtape?')) return;
    var mid=parseInt(btn.dataset.mxid);
    var r=await fetch(BASE+'/backend/mixtape_api.php',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'delete',mixtape_id:mid})
    });
    var d=await r.json();
    if(d.ok) btn.closest('.lib-card').remove();
  });
});
</script>

<?php appClose('library'); ?>
