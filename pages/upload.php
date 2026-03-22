<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requirePublisher();

$db = getDB();
$b  = BASE_URL;

$artists   = $db->query('SELECT id, name FROM artists ORDER BY name ASC')->fetchAll();
try {
    $allSeries = $db->query('SELECT id, name FROM series ORDER BY name ASC')->fetchAll();
} catch (Exception $e) { $allSeries = []; }

appOpen('Upload');
?>

<style>
/* ── Upload page tabs ─── */
.up-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); background:var(--bg); position:sticky; top:0; z-index:10; }
.up-tab  { flex:1; padding:13px 6px; border:none; background:none; font-family:var(--font); font-size:13px; font-weight:600; color:var(--tf); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; -webkit-tap-highlight-color:transparent; text-align:center; }
.up-tab.active { color:var(--t1); border-bottom-color:var(--accent); }
.up-tab:active { opacity:.7; }
.up-pane { display:none; padding:16px; }
.up-pane.active { display:block; }

/* ── Upload sections ─── */
.up-section { background:var(--bg-el); border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:14px; }
.up-section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--tf); margin-bottom:14px; }

/* ── Drop zone ─── */
.dz { border:2px dashed var(--border-m); border-radius:10px; padding:32px 16px; text-align:center; cursor:pointer; transition:border-color .15s,background .15s; }
.dz.over { border-color:var(--accent); background:rgba(81,112,255,.06); }
.dz-icon { width:36px; height:36px; color:var(--tf); display:block; margin:0 auto 10px; }
.dz-label { font-size:14px; font-weight:700; margin-bottom:4px; }
.dz-sub   { font-size:12px; color:var(--t2); margin-bottom:12px; }

/* ── File pill ─── */
.file-pill { display:flex; align-items:center; gap:8px; padding:8px 12px; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; margin-top:6px; }
.fp-name   { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px; font-weight:500; }
.fp-size   { font-size:11px; color:var(--tf); flex-shrink:0; }
.fp-rm     { background:none; border:none; cursor:pointer; color:var(--tf); padding:2px; display:flex; align-items:center; border-radius:50%; -webkit-tap-highlight-color:transparent; }
.fp-rm:active { color:var(--t1); }

/* ── Image picker ─── */
.img-pick { display:flex; align-items:center; gap:12px; }
.img-thumb { width:72px; height:72px; border-radius:10px; background:var(--bg-card); border:1px solid var(--border); overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.img-thumb img { width:100%; height:100%; object-fit:cover; display:block; }

/* ── Progress bar ─── */
.prog-wrap { height:3px; background:var(--bg-hover); border-radius:2px; margin-top:12px; overflow:hidden; }
.prog-fill { height:100%; background:var(--accent); width:0%; transition:width .3s; border-radius:2px; }

/* ── Result rows ─── */
.result-ok  { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--border); font-size:13px; }
.result-ok:last-child { border-bottom:none; }
.result-ok-icon  { color:var(--accent); font-size:16px; flex-shrink:0; }
.result-err-icon { color:#ff9090; font-size:16px; flex-shrink:0; }

/* Fix btn-primary in non-shell context */
.up-pane .btn-primary { width:100%; }
.up-pane .btn-secondary { width:auto; }
</style>

<!-- Tab bar -->
<div class="up-tabs" id="up-tabs">
  <button class="up-tab active" data-tab="single">Single</button>
  <button class="up-tab"        data-tab="album">Album</button>
  <button class="up-tab"        data-tab="hoerbuch">Hörbuch</button>
  <button class="up-tab"        data-tab="mixtape">Mixtape</button>
</div>

<!-- ═══════════════════════ SINGLE ═══════════════════════ -->
<div class="up-pane active" id="up-single">

  <div class="up-section">
    <div class="up-section-label">Audio File</div>
    <div class="dz" id="s-dz">
      <svg class="dz-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
      <div class="dz-label">Drop song here</div>
      <div class="dz-sub">MP3, FLAC, OGG, WAV, M4A · Max 100 MB</div>
      <label class="btn btn-secondary" style="padding:8px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
        Browse <input type="file" id="s-file" accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
      </label>
    </div>
    <div id="s-file-list"></div>
  </div>

  <div class="up-section">
    <div class="up-section-label">Details <span style="font-weight:400;color:var(--tf)">(leave blank to use ID3 tags)</span></div>
    <div class="form-group">
      <label>Title</label>
      <input type="text" id="s-title" placeholder="Read from file if empty">
    </div>
    <div class="form-group">
      <label>Artist</label>
      <input type="text" id="s-artist" placeholder="Read from file if empty" list="artist-dl" autocomplete="off">
      <datalist id="artist-dl"><?php foreach($artists as $a): ?><option value="<?= h($a['name']) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label>Cover</label>
      <div class="img-pick">
        <div class="img-thumb" id="s-cover-thumb"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
        <label class="btn btn-secondary" style="padding:8px 16px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Choose <input type="file" id="s-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </div>
    </div>
  </div>

  <div class="prog-wrap" id="s-prog-wrap" style="display:none"><div class="prog-fill" id="s-prog"></div></div>
  <button class="btn btn-primary" id="s-btn" disabled style="opacity:.4;border-radius:10px;margin-top:14px">Upload Single</button>
  <div id="s-result" style="margin-top:12px"></div>
</div>

<!-- ═══════════════════════ ALBUM ═══════════════════════ -->
<div class="up-pane" id="up-album">

  <div class="up-section">
    <div class="up-section-label">Album Details</div>
    <div class="form-group">
      <label>Album Title</label>
      <input type="text" id="al-title" placeholder="Album name" required>
    </div>
    <div class="form-group">
      <label>Artist</label>
      <input type="text" id="al-artist" placeholder="Artist name" list="artist-dl" autocomplete="off">
    </div>
    <div class="form-group">
      <label>Genre <span style="font-weight:400;color:var(--tf)">(optional)</span></label>
      <input type="text" id="al-genre" placeholder="e.g. Pop, Rock">
    </div>
    <div class="form-group">
      <label>Year <span style="font-weight:400;color:var(--tf)">(optional)</span></label>
      <input type="number" id="al-year" placeholder="2024" min="1900" max="2099">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label>Cover</label>
      <div class="img-pick">
        <div class="img-thumb" id="al-cover-thumb"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
        <label class="btn btn-secondary" style="padding:8px 16px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Choose <input type="file" id="al-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </div>
    </div>
  </div>

  <div class="up-section">
    <div class="up-section-label">Tracks</div>
    <div class="dz" id="al-dz">
      <svg class="dz-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <div class="dz-label">Drop all tracks here</div>
      <div class="dz-sub">Multiple files · sorted by filename</div>
      <label class="btn btn-secondary" style="padding:8px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
        Browse <input type="file" id="al-files" multiple accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
      </label>
    </div>
    <div id="al-file-list"></div>
  </div>

  <div class="prog-wrap" id="al-prog-wrap" style="display:none"><div class="prog-fill" id="al-prog"></div></div>
  <button class="btn btn-primary" id="al-btn" disabled style="opacity:.4;border-radius:10px;margin-top:14px">Upload Album</button>
  <div id="al-result" style="margin-top:12px"></div>
</div>

<!-- ═══════════════════════ HÖRBUCH ═══════════════════════ -->
<div class="up-pane" id="up-hoerbuch">

  <div class="up-section">
    <div class="up-section-label">Audio File</div>
    <div class="dz" id="ab-dz">
      <svg class="dz-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
      <div class="dz-label">Drop audio file here</div>
      <div class="dz-sub">One file per Hörbuch · Max 500 MB</div>
      <label class="btn btn-secondary" style="padding:8px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
        Browse <input type="file" id="ab-file" accept=".mp3,.flac,.ogg,.wav,.m4a,.m4b,.aac,.opus" style="display:none">
      </label>
    </div>
    <div id="ab-file-list"></div>
  </div>

  <div class="up-section">
    <div class="up-section-label">Details</div>
    <div class="form-group">
      <label>Title</label>
      <input type="text" id="ab-title" placeholder="Hörspiel title" required>
    </div>
    <div class="form-group">
      <label>Reihe / Serie <span style="font-weight:400;color:var(--tf)">(optional)</span></label>
      <input type="text" id="ab-series" placeholder="Series name" list="series-dl" autocomplete="off">
      <datalist id="series-dl"><?php foreach($allSeries as $sr): ?><option value="<?= h($sr['name']) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="form-group">
      <label>Sprecher <span style="font-weight:400;color:var(--tf)">(optional)</span></label>
      <input type="text" id="ab-narrator" placeholder="Narrator">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label>Cover</label>
      <div class="img-pick">
        <div class="img-thumb" id="ab-cover-thumb"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></div>
        <label class="btn btn-secondary" style="padding:8px 16px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">
          Choose <input type="file" id="ab-cover" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </div>
    </div>
  </div>

  <div class="prog-wrap" id="ab-prog-wrap" style="display:none"><div class="prog-fill" id="ab-prog"></div></div>
  <button class="btn btn-primary" id="ab-btn" disabled style="opacity:.4;border-radius:10px;margin-top:14px">Upload Hörbuch</button>
  <div id="ab-result" style="margin-top:12px"></div>
</div>

<!-- ═══════════════════════ MIXTAPE ═══════════════════════ -->
<div class="up-pane" id="up-mixtape">

  <div style="background:var(--accent-soft);border:1px solid rgba(81,112,255,.2);border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:var(--t2);line-height:1.6">
    Mixtapes sind wie Alben — du stellst sie zusammen und alle User können sie hören. Nur Publisher &amp; Admins können Mixtapes erstellen und bearbeiten.
  </div>

  <div class="up-section">
    <div class="up-section-label">1. Mixtape erstellen</div>
    <div class="form-group" style="margin-bottom:12px">
      <label>Mixtape Name</label>
      <input type="text" id="mx-name" placeholder="Name des Mixtapes" maxlength="100">
    </div>
    <button class="btn btn-primary" id="mx-create-btn" style="border-radius:10px">Erstellen</button>
    <div id="mx-create-msg" style="margin-top:10px"></div>
  </div>

  <div id="mx-step2" style="display:none">
    <div class="up-section">
      <div class="up-section-label">2. Songs suchen &amp; hinzufügen</div>
      <input type="search" id="mx-search" placeholder="Song suchen…"
             style="width:100%;padding:10px 14px;border:1px solid var(--border-m);border-radius:10px;background:var(--bg-card);color:var(--t1);font-family:var(--font);font-size:14px;outline:none;margin-bottom:10px">
      <div id="mx-search-results"></div>
    </div>

    <div class="up-section">
      <div class="up-section-label">Songs im Mixtape</div>
      <div id="mx-song-list"><p style="font-size:13px;color:var(--tf)">Noch keine Songs hinzugefügt.</p></div>
    </div>

    <a id="mx-view-btn" href="#" class="btn btn-secondary" style="border-radius:10px;display:inline-flex;width:auto;margin-top:4px">
      Mixtape ansehen →
    </a>
  </div>

</div>

<script>
var B = window.CUMU_BASE || '';

/* ── Tab switching ─────────────────────────────────── */
document.querySelectorAll('.up-tab').forEach(function(tab){
  tab.addEventListener('click', function(){
    document.querySelectorAll('.up-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.up-pane').forEach(function(p){ p.classList.remove('active'); });
    tab.classList.add('active');
    var el = document.getElementById('up-'+tab.dataset.tab);
    if (el) el.classList.add('active');
  });
});

/* ── Helpers ───────────────────────────────────────── */
function fmtSz(b){ return b<1048576 ? Math.round(b/1024)+'KB' : (b/1048576).toFixed(1)+'MB'; }

function previewImg(inputId, thumbId){
  document.getElementById(inputId).addEventListener('change', function(){
    var f=this.files[0]; if(!f) return;
    var r=new FileReader(); r.onload=function(e){ document.getElementById(thumbId).innerHTML='<img src="'+e.target.result+'">'; }; r.readAsDataURL(f);
  });
}
previewImg('s-cover','s-cover-thumb');
previewImg('al-cover','al-cover-thumb');
previewImg('ab-cover','ab-cover-thumb');

function renderPills(listId, files, onRemove){
  var c=document.getElementById(listId); c.innerHTML='';
  files.forEach(function(f,i){
    var d=document.createElement('div'); d.className='file-pill';
    d.innerHTML='<span class="fp-name">'+f.name+'</span><span class="fp-size">'+fmtSz(f.size)+'</span>'
      +'<button class="fp-rm" data-i="'+i+'"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
    c.appendChild(d);
  });
  c.querySelectorAll('.fp-rm').forEach(function(btn){ btn.onclick=function(){ onRemove(parseInt(btn.dataset.i)); }; });
}

function bindDrop(dzId, onFiles){
  var dz=document.getElementById(dzId);
  dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('over');});
  dz.addEventListener('dragleave',function(){dz.classList.remove('over');});
  dz.addEventListener('drop',function(e){e.preventDefault();dz.classList.remove('over');onFiles(e.dataTransfer.files);});
  dz.addEventListener('click',function(e){ if(!e.target.closest('label')){ var inp=dz.querySelector('input[type="file"]'); if(inp)inp.click(); }});
}

function doXHR(fd, progId, progWrapId, btnId, resultId, onDone){
  var btn=document.getElementById(btnId), prog=document.getElementById(progId), wrap=document.getElementById(progWrapId), res=document.getElementById(resultId);
  btn.disabled=true; btn.style.opacity='.5';
  wrap.style.display='block'; prog.style.width='0%';
  var xhr=new XMLHttpRequest(); xhr.open('POST',B+'/backend/upload_api.php');
  xhr.upload.onprogress=function(e){ if(e.lengthComputable) prog.style.width=(e.loaded/e.total*100)+'%'; };
  xhr.onload=function(){
    prog.style.width='100%'; btn.disabled=false; btn.style.opacity='1';
    try{
      var d=JSON.parse(xhr.responseText);
      if(d.ok){
        var html=''; (d.data.uploaded||[]).forEach(function(u){ html+='<div class="result-ok"><span class="result-ok-icon">✓</span><span>'+u+'</span></div>'; });
        (d.data.errors||[]).forEach(function(e){ html+='<div class="result-ok"><span class="result-err-icon">✕</span><span style="color:#ff9090">'+e+'</span></div>'; });
        res.innerHTML=html;
        if(onDone) onDone(d.data);
      } else { res.innerHTML='<div class="alert alert-error">'+d.error+'</div>'; }
    }catch(e){ res.innerHTML='<div class="alert alert-error">Parse error</div>'; }
  };
  xhr.onerror=function(){ res.innerHTML='<div class="alert alert-error">Network error</div>'; btn.disabled=false; btn.style.opacity='1'; };
  xhr.send(fd);
}

/* ── SINGLE ─────────────────────────────────────────── */
var sFile=null, sBtn=document.getElementById('s-btn');
function setSingle(files){
  var exts=['mp3','flac','ogg','wav','m4a','aac','opus'];
  var f=files[0]; if(!f) return;
  if(exts.indexOf(f.name.split('.').pop().toLowerCase())<0) return;
  sFile=f;
  renderPills('s-file-list',[f],function(){ sFile=null; renderPills('s-file-list',[],function(){}); sBtn.disabled=true; sBtn.style.opacity='.4'; });
  sBtn.disabled=false; sBtn.style.opacity='1';
}
bindDrop('s-dz',setSingle);
document.getElementById('s-file').addEventListener('change',function(){ setSingle(this.files); });
sBtn.addEventListener('click',function(){
  if(!sFile) return;
  var fd=new FormData(); fd.append('type','single'); fd.append('files[]',sFile);
  fd.append('title',document.getElementById('s-title').value.trim());
  fd.append('artist',document.getElementById('s-artist').value.trim()||'Unknown Artist');
  var cv=document.getElementById('s-cover').files[0]; if(cv) fd.append('cover',cv);
  doXHR(fd,'s-prog','s-prog-wrap','s-btn','s-result',function(){ sFile=null; renderPills('s-file-list',[],function(){}); });
});

/* ── ALBUM ──────────────────────────────────────────── */
var alFiles=[], alBtn=document.getElementById('al-btn');
function addAlFiles(list){
  var exts=['mp3','flac','ogg','wav','m4a','aac','opus'];
  Array.prototype.forEach.call(list,function(f){ if(exts.indexOf(f.name.split('.').pop().toLowerCase())>=0&&!alFiles.find(function(x){return x.name===f.name&&x.size===f.size;})) alFiles.push(f); });
  alFiles.sort(function(a,b){return a.name.localeCompare(b.name);});
  function rmAl(i){ alFiles.splice(i,1); renderPills('al-file-list',alFiles,rmAl); alBtn.disabled=!alFiles.length; alBtn.style.opacity=alFiles.length?'1':'.4'; }
  renderPills('al-file-list',alFiles,rmAl);
  alBtn.disabled=!alFiles.length; alBtn.style.opacity=alFiles.length?'1':'.4';
}
bindDrop('al-dz',addAlFiles);
document.getElementById('al-files').addEventListener('change',function(){ addAlFiles(this.files); });
alBtn.addEventListener('click',function(){
  if(!alFiles.length) return;
  var title=document.getElementById('al-title').value.trim();
  if(!title){ alert('Album title required.'); return; }
  var fd=new FormData(); fd.append('type','album'); fd.append('album',title);
  fd.append('artist',document.getElementById('al-artist').value.trim()||'Unknown Artist');
  fd.append('genre',document.getElementById('al-genre').value.trim());
  fd.append('year',document.getElementById('al-year').value);
  alFiles.forEach(function(f){ fd.append('files[]',f); });
  var cv=document.getElementById('al-cover').files[0]; if(cv) fd.append('cover',cv);
  doXHR(fd,'al-prog','al-prog-wrap','al-btn','al-result',function(){ alFiles=[]; renderPills('al-file-list',[],function(){}); alBtn.disabled=true; alBtn.style.opacity='.4'; });
});

/* ── HÖRBUCH ────────────────────────────────────────── */
var abFile=null, abBtn=document.getElementById('ab-btn');
function setAb(files){
  var f=files[0]; if(!f) return;
  abFile=f;
  renderPills('ab-file-list',[f],function(){ abFile=null; renderPills('ab-file-list',[],function(){}); abBtn.disabled=true; abBtn.style.opacity='.4'; });
  abBtn.disabled=false; abBtn.style.opacity='1';
}
bindDrop('ab-dz',setAb);
document.getElementById('ab-file').addEventListener('change',function(){ setAb(this.files); });
abBtn.addEventListener('click',function(){
  if(!abFile) return;
  var title=document.getElementById('ab-title').value.trim();
  if(!title){ alert('Title required.'); return; }
  var fd=new FormData(); fd.append('type','audiobook'); fd.append('files[]',abFile);
  fd.append('title',title); fd.append('series',document.getElementById('ab-series').value.trim());
  fd.append('narrator',document.getElementById('ab-narrator').value.trim());
  var cv=document.getElementById('ab-cover').files[0]; if(cv) fd.append('cover',cv);
  doXHR(fd,'ab-prog','ab-prog-wrap','ab-btn','ab-result',function(){ abFile=null; renderPills('ab-file-list',[],function(){}); abBtn.disabled=true; abBtn.style.opacity='.4'; });
});

/* ── MIXTAPE ─────────────────────────────────────────── */
var mxId = null;
var mxSongs = []; // songs added to this mixtape

document.getElementById('mx-create-btn').addEventListener('click', async function(){
  var name = document.getElementById('mx-name').value.trim();
  var msg  = document.getElementById('mx-create-msg');
  if (!name) { msg.innerHTML='<div class="alert alert-error">Name required.</div>'; return; }

  var btn = this;
  btn.textContent = 'Erstellen…';
  btn.disabled = true;

  try {
    var resp = await fetch(B + '/backend/mixtape_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', name: name })
    });
    var data = await resp.json();

    if (data.ok) {
      mxId = data.data.id;
      msg.innerHTML = '<div class="alert alert-success">Mixtape "'+name+'" erstellt!</div>';
      document.getElementById('mx-step2').style.display = 'block';
      document.getElementById('mx-view-btn').href = B + '/pages/mixtape.php?id=' + mxId;
      btn.textContent = 'Erneut erstellen';
      btn.disabled = false;
    } else {
      msg.innerHTML = '<div class="alert alert-error">' + (data.error || 'Fehler') + '</div>';
      btn.textContent = 'Erstellen';
      btn.disabled = false;
    }
  } catch(e) {
    msg.innerHTML = '<div class="alert alert-error">Netzwerkfehler: ' + e.message + '</div>';
    btn.textContent = 'Erstellen';
    btn.disabled = false;
  }
});

// Search songs to add to mixtape
var mxTimer = null;
document.getElementById('mx-search').addEventListener('input', function(){
  clearTimeout(mxTimer);
  var q = this.value.trim();
  if (q.length < 2) { document.getElementById('mx-search-results').innerHTML=''; return; }
  mxTimer = setTimeout(async function(){
    var res = document.getElementById('mx-search-results');
    try {
      var r = await fetch(B+'/backend/meta_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'search_songs', q:q})
      });
      var d = await r.json();
      if (!d.ok || !d.data.length) { res.innerHTML='<p style="font-size:13px;color:var(--tf);padding:8px 0">Keine Ergebnisse</p>'; return; }
      var html='';
      d.data.forEach(function(song){
        html += '<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">'
          +'<div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+song.title+'</div>'
          +'<div style="font-size:12px;color:var(--t2)">'+song.artist+'</div></div>'
          +'<button class="btn btn-secondary" data-sid="'+song.id+'" data-title="'+song.title+'" data-artist="'+song.artist+'"'
          +' style="padding:6px 12px;font-size:12px;border-radius:8px;flex-shrink:0">Add</button>'
          +'</div>';
      });
      res.innerHTML = html;
      res.querySelectorAll('button[data-sid]').forEach(function(btn){
        btn.addEventListener('click', async function(){
          if (!mxId) { alert('Erstelle zuerst ein Mixtape.'); return; }
          btn.textContent='…'; btn.disabled=true;
          var r2 = await fetch(B+'/backend/mixtape_api.php',{
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'add_song', mixtape_id:mxId, song_id:parseInt(btn.dataset.sid)})
          });
          var d2 = await r2.json();
          if(d2.ok){
            btn.textContent='✓'; btn.style.color='var(--accent)';
            mxSongs.push({title:btn.dataset.title, artist:btn.dataset.artist, id:btn.dataset.sid});
            renderMxSongs();
          } else {
            btn.textContent='Add'; btn.disabled=false;
          }
        });
      });
    } catch(e){ res.innerHTML='<p style="font-size:13px;color:#ff9090;padding:8px 0">Error: '+e.message+'</p>'; }
  }, 320);
});

function renderMxSongs(){
  var list = document.getElementById('mx-song-list');
  if (!mxSongs.length){ list.innerHTML='<p style="font-size:13px;color:var(--tf)">Noch keine Songs.</p>'; return; }
  list.innerHTML = mxSongs.map(function(s,i){
    return '<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">'
      +'<div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:600">'+s.title+'</div><div style="font-size:12px;color:var(--t2)">'+s.artist+'</div></div>'
      +'</div>';
  }).join('');
}
</script>

<?php appClose('library'); ?>
