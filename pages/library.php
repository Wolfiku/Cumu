<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db  = getDB();
$uid = currentUserId();
$b   = BASE_URL;

$pls = $db->prepare('SELECT p.*, COUNT(ps.song_id) AS song_count
    FROM playlists p
    LEFT JOIN playlist_songs ps ON ps.playlist_id = p.id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.is_favorite DESC, p.created_at DESC');
$pls->execute([$uid]);
$playlists = $pls->fetchAll();

// Default cover for unknowns
$defaultCover = $b . '/assets/covers/favourite.png';

// Fetch user mixtapes
$mxs = $db->prepare('SELECT m.*, COUNT(ms.song_id) AS song_count
    FROM mixtapes m LEFT JOIN mixtape_songs ms ON ms.mixtape_id=m.id
    WHERE m.user_id=? GROUP BY m.id ORDER BY m.created_at DESC');
$mxs->execute([$uid]); $mixtapes = $mxs->fetchAll();

appOpen('Library');
?>

<div class="page-header" style="padding-top:calc(env(safe-area-inset-top,20px) + 12px)">
  <div class="page-header-row">
    <h1>Library</h1>
    <button id="new-pl-btn"
            style="width:34px;height:34px;border-radius:50%;background:var(--bg-card);border:1px solid var(--border-m);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t2)">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
    </button>
  </div>
</div>

<?php if (empty($playlists)): ?>
  <div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
    </svg>
    <div class="empty-state-title">No playlists yet</div>
    <div class="empty-state-text">Tap + to create your first playlist.</div>
  </div>
<?php else: ?>
  <div class="lib-list">
    <?php foreach ($playlists as $pl):
      // Favourites get a locked cover
      if ($pl['is_favorite']) {
        $cover = $b . '/assets/covers/favourite.png';
      } elseif ($pl['cover']) {
        $cover = $b . '/' . $pl['cover'];
      } else {
        $cover = '';
      }
    ?>
      <div class="lib-item" style="position:relative">
        <!-- Cover -->
        <div class="lib-item-art" onclick="window.navigate('pages/playlist.php','id=<?= (int)$pl['id'] ?>')" style="cursor:pointer">
          <?php if ($cover): ?>
            <img src="<?= h($cover) ?>" alt="">
          <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          <?php endif; ?>
        </div>
        <!-- Info -->
        <div class="lib-item-info" onclick="window.navigate('pages/playlist.php','id=<?= (int)$pl['id'] ?>')" style="cursor:pointer;flex:1;min-width:0">
          <div class="lib-item-name">
            <?= h($pl['name']) ?>
            <?php if ($pl['is_favorite']): ?>
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="var(--accent)" style="margin-left:4px;vertical-align:middle"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <?php endif; ?>
          </div>
          <div class="lib-item-sub">
            <?= (int)$pl['song_count'] ?> song<?= $pl['song_count'] != 1 ? 's' : '' ?>
            <?php if (!empty($pl['description'])): ?>
              · <span style="color:var(--tf)"><?= h(mb_strimwidth($pl['description'], 0, 40, '…')) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <!-- Edit button (non-favorites only) -->
        <?php if (!$pl['is_favorite']): ?>
          <button class="sr-action edit-pl-btn"
                  data-id="<?= (int)$pl['id'] ?>"
                  data-name="<?= h($pl['name']) ?>"
                  data-desc="<?= h($pl['description'] ?? '') ?>"
                  data-cover="<?= h($cover) ?>"
                  style="flex-shrink:0">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
            </svg>
          </button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Create playlist sheet -->
<div class="sheet-overlay" id="new-pl-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">New Playlist</div>
    <div style="padding:0 16px 20px">
      <div id="pl-create-err" class="alert alert-error" style="display:none"></div>
      <div class="form-group">
        <label>Name</label>
        <input type="text" id="pl-name-input" placeholder="My playlist…" maxlength="100">
      </div>
      <div class="form-group">
        <label>Description <span style="color:var(--tf);font-weight:400">(optional)</span></label>
        <input type="text" id="pl-desc-input" placeholder="Short description…" maxlength="200">
      </div>
      <button class="btn btn-primary" id="pl-create-btn" style="border-radius:10px">Create Playlist</button>
    </div>
  </div>
</div>

<!-- Edit playlist sheet -->
<div class="sheet-overlay" id="edit-pl-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title" id="edit-pl-title-label">Edit Playlist</div>
    <div style="padding:0 16px 20px">
      <div id="pl-edit-err" class="alert alert-error" style="display:none"></div>
      <div id="pl-edit-ok"  class="alert alert-success" style="display:none"></div>

      <!-- Cover picker -->
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
        <div id="edit-pl-cover-thumb"
             style="width:72px;height:72px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg>
        </div>
        <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
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
        <input type="text" id="edit-pl-desc" maxlength="200" placeholder="Optional description">
      </div>
      <button class="btn btn-primary" id="edit-pl-save-btn" style="border-radius:10px">Save changes</button>
    </div>
  </div>
</div>

<script>
var BASE = window.CUMU_BASE || '';
var editingPlId = null;

/* ── Create playlist ─────────────────────────────────────────── */
var newOverlay = document.getElementById('new-pl-overlay');
document.getElementById('new-pl-btn').onclick = function() {
  newOverlay.classList.add('open');
  setTimeout(function(){ document.getElementById('pl-name-input').focus(); }, 320);
};
newOverlay.onclick = function(e){ if(e.target===newOverlay) newOverlay.classList.remove('open'); };

document.getElementById('pl-create-btn').onclick = async function() {
  var name = document.getElementById('pl-name-input').value.trim();
  var desc = document.getElementById('pl-desc-input').value.trim();
  var err  = document.getElementById('pl-create-err');
  if (!name) { err.textContent='Name required.'; err.style.display='block'; return; }
  var r = await fetch(BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_playlist',name:name,description:desc})});
  var d = await r.json();
  if (d.ok) { window.navigate('pages/playlist.php','id='+d.data.id); newOverlay.classList.remove('open'); }
  else      { err.textContent=d.error; err.style.display='block'; }
};

/* ── Edit playlist ───────────────────────────────────────────── */
var editOverlay = document.getElementById('edit-pl-overlay');
editOverlay.onclick = function(e){ if(e.target===editOverlay) editOverlay.classList.remove('open'); };

document.querySelectorAll('.edit-pl-btn').forEach(function(btn){
  btn.onclick = function(e){
    e.stopPropagation();
    editingPlId = parseInt(btn.dataset.id);
    document.getElementById('edit-pl-name').value = btn.dataset.name || '';
    document.getElementById('edit-pl-desc').value = btn.dataset.desc || '';
    var thumb = document.getElementById('edit-pl-cover-thumb');
    if (btn.dataset.cover) {
      thumb.innerHTML = '<img src="'+btn.dataset.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">';
    } else {
      thumb.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg>';
    }
    document.getElementById('pl-edit-err').style.display='none';
    document.getElementById('pl-edit-ok').style.display='none';
    editOverlay.classList.add('open');
  };
});

// Cover preview
document.getElementById('edit-pl-cover-input').onchange = function(){
  var f=this.files[0]; if(!f) return;
  var r=new FileReader(); r.onload=function(e){ document.getElementById('edit-pl-cover-thumb').innerHTML='<img src="'+e.target.result+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'; }; r.readAsDataURL(f);
};

document.getElementById('edit-pl-save-btn').onclick = async function(){
  var btn=this; btn.textContent='Saving…'; btn.disabled=true;
  var err=$id('pl-edit-err'), ok=$id('pl-edit-ok');

  // 1. Save name + description
  var r=await fetch(BASE+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_playlist',playlist_id:editingPlId,name:document.getElementById('edit-pl-name').value.trim(),description:document.getElementById('edit-pl-desc').value.trim()})});
  var d=await r.json();
  if(!d.ok){ err.textContent=d.error; err.style.display='block'; btn.textContent='Save changes'; btn.disabled=false; return; }

  // 2. Upload cover if selected
  var coverFile=document.getElementById('edit-pl-cover-input').files[0];
  if(coverFile){
    var fd=new FormData(); fd.append('action','update_playlist_cover'); fd.append('playlist_id',editingPlId); fd.append('cover',coverFile);
    var r2=await fetch(BASE+'/backend/playlist_api.php',{method:'POST',body:fd});
    var d2=await r2.json();
    if(!d2.ok){ err.textContent=d2.error; err.style.display='block'; btn.textContent='Save changes'; btn.disabled=false; return; }
  }

  ok.textContent='Saved!'; ok.style.display='block'; err.style.display='none';
  btn.textContent='Save changes'; btn.disabled=false;
  // Reload after short delay
  setTimeout(function(){ editOverlay.classList.remove('open'); location.reload(); }, 800);
};

function $id(id){ return document.getElementById(id); }
</script>


<!-- Mixtapes Section -->
<?php if (!empty($mixtapes)): ?>
<div class="section-label" style="margin-top:8px">Mixtapes</div>
<div class="lib-list">
  <?php foreach ($mixtapes as $mx): ?>
    <div class="lib-item">
      <!-- Cassette mini art -->
      <div class="lib-item-art" onclick="window.navigate('pages/mixtape.php','id=<?= (int)$mx['id'] ?>')" style="cursor:pointer;background:#1e1a2e;border-radius:var(--r);overflow:hidden">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#aaa">
          <rect x="2" y="5" width="20" height="14" rx="2"/>
          <circle cx="8" cy="12" r="2"/><circle cx="16" cy="12" r="2"/>
          <path d="M8 12h8"/>
        </svg>
      </div>
      <div class="lib-item-info" onclick="window.navigate('pages/mixtape.php','id=<?= (int)$mx['id'] ?>')" style="cursor:pointer;flex:1;min-width:0">
        <div class="lib-item-name">📼 <?= h($mx['name']) ?></div>
        <div class="lib-item-sub">Mixtape · <?= (int)$mx['song_count'] ?> song<?= $mx['song_count'] != 1 ? 's' : '' ?></div>
      </div>
      <button class="sr-action" id="del-mx-<?= (int)$mx['id'] ?>" data-mxid="<?= (int)$mx['id'] ?>" title="Delete mixtape" style="flex-shrink:0">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
      </button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- New Mixtape button -->
<div style="padding:12px 16px 0">
  <button id="new-mx-btn" class="btn btn-secondary" style="width:auto;border-radius:10px;font-size:13px;padding:9px 18px;display:inline-flex;gap:8px;align-items:center">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Mixtape
  </button>
</div>

<script>
// Delete mixtape
document.querySelectorAll('[id^="del-mx-"]').forEach(function(btn){
  btn.addEventListener('click', async function(e){
    e.stopPropagation();
    if (!confirm('Delete this Mixtape?')) return;
    var mid = parseInt(btn.dataset.mxid);
    var r = await fetch(window.CUMU_BASE+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',mixtape_id:mid})});
    var d = await r.json();
    if (d.ok) btn.closest('.lib-item').remove();
  });
});

// New Mixtape
document.getElementById('new-mx-btn').addEventListener('click', async function(){
  var name = prompt('Mixtape name:');
  if (!name || !name.trim()) return;
  var r = await fetch(window.CUMU_BASE+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',name:name.trim()})});
  var d = await r.json();
  if (d.ok) window.navigate('pages/mixtape.php','id='+d.data.id);
});
</script>

<?php appClose('library'); ?>
