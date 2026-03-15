<?php
/**
 * CUMU – pages/upload.php
 * 3 tabs: Single | Album | Hörbuch/Hörspiel
 * Publishers + Admins only.
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requirePublisher();

$db = getDB();
$b  = BASE_URL;

// Fetch artists and series for dropdowns
$artists = $db->query('SELECT id, name FROM artists ORDER BY name ASC')->fetchAll();
$allSeries = $db->query('SELECT id, name FROM series ORDER BY name ASC')->fetchAll();

adminHead('Upload', 'upload');
?>

<style>
.upload-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:0; }
.upload-tab  { flex:1; padding:14px 8px; border:none; background:none; font-family:var(--font); font-size:14px; font-weight:600; color:var(--tf); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; -webkit-tap-highlight-color:transparent; }
.upload-tab.active { color:var(--t1); border-bottom-color:var(--accent); }
.upload-tab:active { opacity:.7; }
.tab-body { display:none; padding:24px 0 0; }
.tab-body.active { display:block; }

.upload-section { background:var(--bg-el); border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:16px; }
.upload-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--tf); margin-bottom:16px; }

.drop-zone { border:2px dashed var(--border-m); border-radius:12px; padding:36px 20px; text-align:center; cursor:pointer; transition:border-color .15s,background .15s; }
.drop-zone.over { border-color:var(--accent); background:rgba(81,112,255,.06); }
.drop-icon { width:44px; height:44px; margin:0 auto 12px; color:var(--tf); display:block; }
.drop-label { font-size:15px; font-weight:700; margin-bottom:4px; }
.drop-sub   { font-size:13px; color:var(--t2); margin-bottom:14px; }
.file-pill  { display:flex; align-items:center; gap:10px; padding:9px 12px; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; margin-top:8px; font-size:13px; }
.file-pill-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:500; }
.file-pill-size { color:var(--tf); flex-shrink:0; font-size:12px; }
.file-pill-rm   { background:none; border:none; cursor:pointer; color:var(--tf); padding:4px; display:flex; align-items:center; justify-content:center; border-radius:50%; flex-shrink:0; }
.file-pill-rm:hover { color:var(--t1); background:var(--bg-hover); }

.img-pick { display:flex; align-items:center; gap:14px; }
.img-thumb { width:80px; height:80px; border-radius:10px; background:var(--bg-card); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.img-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.img-thumb svg { color:var(--tf); }
.img-pick-btn { flex:1; }

.result-row { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); font-size:13.5px; }
.result-row:last-child { border-bottom:none; }
.result-ok  { color:var(--accent); font-size:18px; flex-shrink:0; }
.result-err { color:#ff9090;  font-size:18px; flex-shrink:0; }
.result-name { flex:1; }

.prog-bar { height:3px; background:var(--bg-hover); border-radius:2px; margin-top:12px; overflow:hidden; }
.prog-fill { height:100%; background:var(--accent); width:0%; transition:width .3s; }
</style>

<div style="padding:0 0 24px">

  <!-- Tab navigation -->
  <div class="upload-tabs" id="utabs">
    <button class="upload-tab active" data-tab="single">Single</button>
    <button class="upload-tab"        data-tab="album">Album</button>
    <button class="upload-tab"        data-tab="audio">Hörbuch / Hörspiel</button>
  </div>

  <!-- ══════════════════════ TAB: SINGLE ══════════════════════ -->
  <div class="tab-body active" id="tab-single" style="padding:20px">

    <div class="upload-section">
      <div class="upload-section-title">Audio File</div>
      <div class="drop-zone" id="s-dz">
        <svg class="drop-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
        </svg>
        <div class="drop-label">Drop your song here</div>
        <div class="drop-sub">MP3, FLAC, OGG, WAV, M4A · Max 100 MB</div>
        <label class="btn btn-secondary" style="width:auto;padding:9px 20px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Browse
          <input type="file" id="s-file" accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
        </label>
      </div>
      <div id="s-file-list"></div>
    </div>

    <div class="upload-section">
      <div class="upload-section-title">Details</div>
      <div class="form-group">
        <label>Song Title</label>
        <input type="text" id="s-title" placeholder="Song title (read from file if empty)">
      </div>
      <div class="form-group">
        <label>Artist / Interpreter</label>
        <input type="text" id="s-artist" placeholder="Artist name" list="artist-datalist" autocomplete="off">
        <datalist id="artist-datalist">
          <?php foreach($artists as $a): ?>
            <option value="<?= h($a['name']) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Cover</label>
        <div class="img-pick">
          <div class="img-thumb" id="s-cover-thumb">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
          <div class="img-pick-btn">
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
              Choose image
              <input type="file" id="s-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
            </label>
            <div style="font-size:12px;color:var(--tf);margin-top:6px">JPEG, PNG or WebP · Max 5 MB</div>
          </div>
        </div>
      </div>
    </div>

    <div id="s-prog-wrap" style="display:none"><div class="prog-bar"><div class="prog-fill" id="s-prog"></div></div></div>
    <button class="btn btn-primary" id="s-upload-btn" disabled style="opacity:.4;border-radius:10px;margin-top:16px">Upload Single</button>
    <div id="s-results" style="margin-top:14px"></div>

  </div>

  <!-- ══════════════════════ TAB: ALBUM ══════════════════════ -->
  <div class="tab-body" id="tab-album" style="padding:20px">

    <div class="upload-section">
      <div class="upload-section-title">Album Details</div>
      <div class="form-group">
        <label>Album Title</label>
        <input type="text" id="al-title" placeholder="Album name" required>
      </div>
      <div class="form-group">
        <label>Artist / Interpreter</label>
        <input type="text" id="al-artist" placeholder="Artist name" list="artist-datalist" autocomplete="off">
      </div>
      <div class="form-group">
        <label>Genre <span style="color:var(--tf);font-weight:400">(optional)</span></label>
        <input type="text" id="al-genre" placeholder="e.g. Pop, Rock, Electronic">
      </div>
      <div class="form-group">
        <label>Year <span style="color:var(--tf);font-weight:400">(optional)</span></label>
        <input type="number" id="al-year" placeholder="2024" min="1900" max="2099">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Album Cover</label>
        <div class="img-pick">
          <div class="img-thumb" id="al-cover-thumb">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
          <div class="img-pick-btn">
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
              Choose cover
              <input type="file" id="al-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="upload-section">
      <div class="upload-section-title">Tracks</div>
      <div class="drop-zone" id="al-dz">
        <svg class="drop-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <div class="drop-label">Drop all tracks here</div>
        <div class="drop-sub">Multiple files allowed · Order by filename (01, 02 …)</div>
        <label class="btn btn-secondary" style="width:auto;padding:9px 20px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Browse
          <input type="file" id="al-files" multiple accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
        </label>
      </div>
      <div id="al-file-list" style="margin-top:8px"></div>
    </div>

    <div id="al-prog-wrap" style="display:none"><div class="prog-bar"><div class="prog-fill" id="al-prog"></div></div></div>
    <button class="btn btn-primary" id="al-upload-btn" disabled style="opacity:.4;border-radius:10px;margin-top:16px">Upload Album</button>
    <div id="al-results" style="margin-top:14px"></div>

  </div>

  <!-- ══════════════════════ TAB: HÖRBUCH ══════════════════════ -->
  <div class="tab-body" id="tab-audio" style="padding:20px">

    <div class="upload-section">
      <div class="upload-section-title">Audio File</div>
      <div class="drop-zone" id="ab-dz">
        <svg class="drop-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 18v-6a9 9 0 0 1 18 0v6"/>
          <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>
        </svg>
        <div class="drop-label">Drop audio file here</div>
        <div class="drop-sub">One file per Hörbuch/Hörspiel · Max 500 MB</div>
        <label class="btn btn-secondary" style="width:auto;padding:9px 20px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Browse
          <input type="file" id="ab-file" accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus,.m4b" style="display:none">
        </label>
      </div>
      <div id="ab-file-list"></div>
    </div>

    <div class="upload-section">
      <div class="upload-section-title">Details</div>
      <div class="form-group">
        <label>Title</label>
        <input type="text" id="ab-title" placeholder="Hörspiel / Hörbuch title" required>
      </div>
      <div class="form-group">
        <label>Reihe / Serie <span style="color:var(--tf);font-weight:400">(optional)</span></label>
        <input type="text" id="ab-series" placeholder="New or existing series…" list="series-datalist" autocomplete="off">
        <datalist id="series-datalist">
          <?php foreach($allSeries as $sr): ?>
            <option value="<?= h($sr['name']) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Sprecher / Narrator <span style="color:var(--tf);font-weight:400">(optional)</span></label>
        <input type="text" id="ab-narrator" placeholder="Narrator name">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Cover</label>
        <div class="img-pick">
          <div class="img-thumb" id="ab-cover-thumb">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
          <div class="img-pick-btn">
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
              Choose cover
              <input type="file" id="ab-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
            </label>
          </div>
        </div>
      </div>
    </div>

    <div id="ab-prog-wrap" style="display:none"><div class="prog-bar"><div class="prog-fill" id="ab-prog"></div></div></div>
    <button class="btn btn-primary" id="ab-upload-btn" disabled style="opacity:.4;border-radius:10px;margin-top:16px">Upload Hörbuch</button>
    <div id="ab-results" style="margin-top:14px"></div>

  </div>

</div><!-- /padding wrapper -->

<script>
var BASE = window.CUMU_BASE || '';

/* ── Tab switching ──────────────────────────────────────────────────────── */
document.querySelectorAll('.upload-tab').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.upload-tab').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-body').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var el = document.getElementById('tab-' + btn.dataset.tab);
    if (el) el.classList.add('active');
  });
});

/* ── Helpers ────────────────────────────────────────────────────────────── */
function fmtSize(b) { return b < 1048576 ? Math.round(b/1024)+'KB' : (b/1048576).toFixed(1)+'MB'; }

function previewImg(inputId, thumbId) {
  document.getElementById(inputId).addEventListener('change', function() {
    var f = this.files[0]; if (!f) return;
    var r = new FileReader();
    r.onload = function(e) {
      var t = document.getElementById(thumbId);
      t.innerHTML = '<img src="'+e.target.result+'" alt="">';
    };
    r.readAsDataURL(f);
  });
}
previewImg('s-cover',  's-cover-thumb');
previewImg('al-cover', 'al-cover-thumb');
previewImg('ab-cover', 'ab-cover-thumb');

function renderPills(containerId, files, removeCallback) {
  var c = document.getElementById(containerId);
  c.innerHTML = '';
  files.forEach(function(f, i) {
    var d = document.createElement('div');
    d.className = 'file-pill';
    d.innerHTML = '<span class="file-pill-name">'+f.name+'</span>'
      +'<span class="file-pill-size">'+fmtSize(f.size)+'</span>'
      +'<button class="file-pill-rm" data-idx="'+i+'" title="Remove">'
      +'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
      +'</button>';
    c.appendChild(d);
  });
  c.querySelectorAll('.file-pill-rm').forEach(function(btn) {
    btn.addEventListener('click', function() { removeCallback(parseInt(btn.dataset.idx)); });
  });
}

function renderResults(containerId, uploaded, errors) {
  var c = document.getElementById(containerId);
  var html = '';
  uploaded.forEach(function(u) {
    html += '<div class="result-row"><span class="result-ok">✓</span><span class="result-name">'+u+'</span></div>';
  });
  errors.forEach(function(e) {
    html += '<div class="result-row"><span class="result-err">✕</span><span class="result-name" style="color:#ff9090">'+e+'</span></div>';
  });
  c.innerHTML = html;
}

function bindDrop(dzId, onFiles) {
  var dz = document.getElementById(dzId);
  dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('over'); });
  dz.addEventListener('dragleave', function(){ dz.classList.remove('over'); });
  dz.addEventListener('drop', function(e){ e.preventDefault(); dz.classList.remove('over'); onFiles(e.dataTransfer.files); });
  dz.addEventListener('click', function(e){ if(!e.target.closest('label')) { var inp=dz.querySelector('input[type="file"]'); if(inp)inp.click(); } });
}

function doUpload(fd, progId, progWrapId, btnId, resultsId) {
  var btn  = document.getElementById(btnId);
  var prog = document.getElementById(progId);
  var wrap = document.getElementById(progWrapId);
  btn.disabled = true; btn.style.opacity = '.5';
  wrap.style.display = 'block'; prog.style.width = '0%';
  var xhr = new XMLHttpRequest();
  xhr.open('POST', BASE + '/backend/upload_api.php');
  xhr.upload.onprogress = function(e) { if(e.lengthComputable) prog.style.width=(e.loaded/e.total*100)+'%'; };
  xhr.onload = function() {
    prog.style.width = '100%';
    try {
      var d = JSON.parse(xhr.responseText);
      if (d.ok) { renderResults(resultsId, d.data.uploaded||[], d.data.errors||[]); }
      else       { document.getElementById(resultsId).innerHTML='<div class="alert alert-error">'+d.error+'</div>'; }
    } catch(e){ document.getElementById(resultsId).innerHTML='<div class="alert alert-error">Parse error</div>'; }
    btn.disabled = false; btn.style.opacity = '1';
  };
  xhr.onerror = function() {
    document.getElementById(resultsId).innerHTML = '<div class="alert alert-error">Network error</div>';
    btn.disabled = false; btn.style.opacity = '1';
  };
  xhr.send(fd);
}

/* ── SINGLE UPLOAD ──────────────────────────────────────────────────────── */
var sFile = null;
var sBtn  = document.getElementById('s-upload-btn');

function setSingleFile(files) {
  var exts = ['mp3','flac','ogg','wav','m4a','aac','opus'];
  var f = files[0]; if (!f) return;
  var ext = f.name.split('.').pop().toLowerCase();
  if (exts.indexOf(ext) < 0) return;
  sFile = f;
  renderPills('s-file-list', [f], function(){ sFile=null; renderPills('s-file-list',[],function(){}); sBtn.disabled=true; sBtn.style.opacity='.4'; });
  sBtn.disabled = false; sBtn.style.opacity = '1';
}

bindDrop('s-dz', setSingleFile);
document.getElementById('s-file').addEventListener('change', function(){ setSingleFile(this.files); });

sBtn.addEventListener('click', function() {
  if (!sFile) return;
  var fd = new FormData();
  fd.append('type', 'single');
  fd.append('files[]', sFile);
  fd.append('title',  document.getElementById('s-title').value.trim());
  fd.append('artist', document.getElementById('s-artist').value.trim() || 'Unknown Artist');
  var cover = document.getElementById('s-cover').files[0];
  if (cover) fd.append('cover', cover);
  doUpload(fd, 's-prog', 's-prog-wrap', 's-upload-btn', 's-results');
  sFile = null; renderPills('s-file-list',[],function(){});
});

/* ── ALBUM UPLOAD ───────────────────────────────────────────────────────── */
var alFiles = [];
var alBtn   = document.getElementById('al-upload-btn');

function addAlbumFiles(fileList) {
  var exts = ['mp3','flac','ogg','wav','m4a','aac','opus'];
  Array.prototype.forEach.call(fileList, function(f) {
    var ext = f.name.split('.').pop().toLowerCase();
    if (exts.indexOf(ext) >= 0 && !alFiles.find(function(x){ return x.name===f.name&&x.size===f.size; }))
      alFiles.push(f);
  });
  alFiles.sort(function(a,b){ return a.name.localeCompare(b.name); });
  renderPills('al-file-list', alFiles, function(i){ alFiles.splice(i,1); renderPills('al-file-list',alFiles,arguments.callee); alBtn.disabled=!alFiles.length; alBtn.style.opacity=alFiles.length?'1':'.4'; });
  alBtn.disabled = !alFiles.length; alBtn.style.opacity = alFiles.length?'1':'.4';
}

bindDrop('al-dz', addAlbumFiles);
document.getElementById('al-files').addEventListener('change', function(){ addAlbumFiles(this.files); });

alBtn.addEventListener('click', function() {
  if (!alFiles.length) return;
  var albumTitle = document.getElementById('al-title').value.trim();
  if (!albumTitle) { alert('Please enter an album title.'); return; }
  var fd = new FormData();
  fd.append('type',   'album');
  fd.append('album',  albumTitle);
  fd.append('artist', document.getElementById('al-artist').value.trim() || 'Unknown Artist');
  fd.append('genre',  document.getElementById('al-genre').value.trim());
  fd.append('year',   document.getElementById('al-year').value);
  alFiles.forEach(function(f){ fd.append('files[]', f); });
  var cover = document.getElementById('al-cover').files[0];
  if (cover) fd.append('cover', cover);
  doUpload(fd, 'al-prog', 'al-prog-wrap', 'al-upload-btn', 'al-results');
  alFiles = []; renderPills('al-file-list',[],function(){}); alBtn.disabled=true; alBtn.style.opacity='.4';
});

/* ── HÖRBUCH UPLOAD ─────────────────────────────────────────────────────── */
var abFile = null;
var abBtn  = document.getElementById('ab-upload-btn');

function setAbFile(files) {
  var f = files[0]; if (!f) return;
  abFile = f;
  renderPills('ab-file-list', [f], function(){ abFile=null; renderPills('ab-file-list',[],function(){}); abBtn.disabled=true; abBtn.style.opacity='.4'; });
  abBtn.disabled = false; abBtn.style.opacity = '1';
}

bindDrop('ab-dz', setAbFile);
document.getElementById('ab-file').addEventListener('change', function(){ setAbFile(this.files); });

abBtn.addEventListener('click', function() {
  if (!abFile) return;
  var title = document.getElementById('ab-title').value.trim();
  if (!title) { alert('Please enter a title.'); return; }
  var fd = new FormData();
  fd.append('type',     'audiobook');
  fd.append('files[]',  abFile);
  fd.append('title',    title);
  fd.append('series',   document.getElementById('ab-series').value.trim());
  fd.append('narrator', document.getElementById('ab-narrator').value.trim());
  var cover = document.getElementById('ab-cover').files[0];
  if (cover) fd.append('cover', cover);
  doUpload(fd, 'ab-prog', 'ab-prog-wrap', 'ab-upload-btn', 'ab-results');
  abFile = null; renderPills('ab-file-list',[],function(){}); abBtn.disabled=true; abBtn.style.opacity='.4';
});
</script>

<?php adminFoot(); ?>
