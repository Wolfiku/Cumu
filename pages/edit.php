<?php
/**
 * CUMU – pages/edit.php
 * Admin: search and edit songs, albums, artists, audiobooks.
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireAdmin();

$db = getDB();
$b  = BASE_URL;

adminHead('Edit Library', 'edit');
?>

<style>
.edit-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:0; }
.edit-tab  { flex:1; padding:14px 8px; border:none; background:none; font-family:var(--font); font-size:13px; font-weight:600; color:var(--tf); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; -webkit-tap-highlight-color:transparent; text-align:center; }
.edit-tab.active { color:var(--t1); border-bottom-color:var(--accent); }
.etab-body { display:none; padding:20px 0 0; }
.etab-body.active { display:block; }

.search-row { display:flex; gap:10px; margin-bottom:20px; align-items:center; }
.search-row input { flex:1; padding:10px 14px; border:1px solid var(--border-m); border-radius:10px; background:var(--bg-card); color:var(--t1); font-family:var(--font); font-size:15px; outline:none; }
.search-row input:focus { border-color:var(--accent); }

.result-list { display:flex; flex-direction:column; gap:6px; margin-bottom:20px; }
.edit-result { display:flex; align-items:center; gap:12px; padding:10px 12px; background:var(--bg-el); border:1px solid var(--border); border-radius:10px; cursor:pointer; transition:background .1s; }
.edit-result:hover { background:var(--bg-card); }
.edit-result-art { width:44px; height:44px; border-radius:8px; background:var(--bg-card); overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.edit-result-art img { width:100%; height:100%; object-fit:cover; display:block; }
.edit-result-name  { font-weight:600; font-size:14px; }
.edit-result-sub   { font-size:12px; color:var(--t2); margin-top:2px; }
.edit-result-arrow { margin-left:auto; color:var(--tf); }

.edit-form { background:var(--bg-el); border:1px solid var(--border); border-radius:14px; padding:20px; }
.edit-form-title { font-size:14px; font-weight:700; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.back-link { font-size:13px; color:var(--accent); cursor:pointer; background:none; border:none; padding:0; font-family:var(--font); }
.img-pick { display:flex; align-items:center; gap:14px; }
.img-thumb { width:80px; height:80px; border-radius:10px; background:var(--bg-card); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.img-thumb img { width:100%; height:100%; object-fit:cover; }
.img-thumb svg { color:var(--tf); }
</style>

<div style="padding:0 0 24px">
  <!-- Tabs -->
  <div class="edit-tabs" id="edit-tabs">
    <button class="edit-tab active" data-tab="songs">Songs</button>
    <button class="edit-tab" data-tab="albums">Albums</button>
    <button class="edit-tab" data-tab="artists">Artists</button>
    <button class="edit-tab" data-tab="audiobooks">Hörbücher</button>
  </div>

  <!-- ══ SONGS ══ -->
  <div class="etab-body active" id="etab-songs" style="padding:20px">
    <div class="search-row">
      <input type="search" id="song-search" placeholder="Search songs…" autocomplete="off">
    </div>
    <div class="result-list" id="song-results"></div>
    <div id="song-edit-form" style="display:none"></div>
  </div>

  <!-- ══ ALBUMS ══ -->
  <div class="etab-body" id="etab-albums" style="padding:20px">
    <div class="search-row">
      <input type="search" id="album-search" placeholder="Search albums…" autocomplete="off">
    </div>
    <div class="result-list" id="album-results"></div>
    <div id="album-edit-form" style="display:none"></div>
  </div>

  <!-- ══ ARTISTS ══ -->
  <div class="etab-body" id="etab-artists" style="padding:20px">
    <div class="search-row">
      <input type="search" id="artist-search" placeholder="Search artists…" autocomplete="off">
    </div>
    <div class="result-list" id="artist-results"></div>
    <div id="artist-edit-form" style="display:none"></div>
  </div>

  <!-- ══ AUDIOBOOKS ══ -->
  <div class="etab-body" id="etab-audiobooks" style="padding:20px">
    <div class="search-row">
      <input type="search" id="ab-search" placeholder="Search Hörbücher…" autocomplete="off">
    </div>
    <div class="result-list" id="ab-results"></div>
    <div id="ab-edit-form" style="display:none"></div>
  </div>
</div>

<script>
var BASE = window.CUMU_BASE || '';

/* ── Tab switching ─────────────────────────────────────────────────────── */
document.querySelectorAll('.edit-tab').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.edit-tab').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.etab-body').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var el = document.getElementById('etab-' + btn.dataset.tab);
    if (el) el.classList.add('active');
  });
});

/* ── Generic search ────────────────────────────────────────────────────── */
function debounce(fn, ms) {
  var t; return function() { clearTimeout(t); t = setTimeout(fn, ms); };
}

function imgThumb(cover, round) {
  var style = round ? 'border-radius:50%' : '';
  if (cover) return '<img src="'+BASE+'/'+cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block;'+style+'">';
  return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
}

/* ── SONGS ─────────────────────────────────────────────────────────────── */
document.getElementById('song-search').addEventListener('input', debounce(function() {
  var q = this.value.trim(); if (q.length < 2) { document.getElementById('song-results').innerHTML=''; return; }
  fetch(BASE+'/backend/meta_api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'search_songs',q:q})})
  .then(function(r){return r.json();})
  .then(function(d) {
    if (!d.ok) return;
    var html='';
    d.data.forEach(function(s) {
      html+='<div class="edit-result" data-id="'+s.id+'" data-type="song"><div class="edit-result-art">'+imgThumb(s.cover)+'</div><div><div class="edit-result-name">'+s.title+'</div><div class="edit-result-sub">'+s.artist+'</div></div><div class="edit-result-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div></div>';
    });
    document.getElementById('song-results').innerHTML = html;
    document.querySelectorAll('#song-results .edit-result').forEach(function(el) {
      el.addEventListener('click', function(){ loadSongForm(el.dataset.id); });
    });
  });
}, 300));

function loadSongForm(id) {
  fetch(BASE+'/backend/meta_api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_song',song_id:parseInt(id)})})
  .then(function(r){return r.json();})
  .then(function(d){
    if(!d.ok) return;
    var s=d.data;
    var form=document.getElementById('song-edit-form');
    form.style.display='block';
    document.getElementById('song-results').innerHTML='';
    document.getElementById('song-search').value='';
    form.innerHTML=`
      <div class="edit-form">
        <div class="edit-form-title">
          <button class="back-link" onclick="document.getElementById('song-edit-form').style.display='none';document.getElementById('song-search').value=''">← Back</button>
          Edit Song
        </div>
        <div class="alert alert-error" id="sf-err" style="display:none"></div>
        <div class="alert alert-success" id="sf-ok" style="display:none"></div>
        <div class="form-group"><label>Title</label><input type="text" id="sf-title" value="${escHtml(s.title||'')}"></div>
        <div class="form-group"><label>Artist</label><input type="text" id="sf-artist" value="${escHtml(s.artist||'')}"></div>
        <div class="form-group"><label>Album</label><input type="text" id="sf-album" value="${escHtml(s.album||'')}"></div>
        <div class="form-group">
          <label>Cover</label>
          <div class="img-pick">
            <div class="img-thumb" id="sf-thumb">${s.cover?'<img src="'+BASE+'/'+s.cover+'" alt="">':'<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'26\' height=\'26\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' style=\'color:var(--tf)\'><circle cx=\'12\' cy=\'12\' r=\'10\'/><circle cx=\'12\' cy=\'12\' r=\'3\'/></svg>'}</div>
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">Change cover<input type="file" id="sf-cover" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
          </div>
        </div>
        <button class="btn btn-primary" id="sf-save" style="border-radius:10px;margin-top:4px" onclick="saveSong(${s.id})">Save changes</button>
      </div>`;
    bindImgPreview('sf-cover','sf-thumb');
  });
}

function saveSong(id) {
  var btn=document.getElementById('sf-save'); btn.textContent='Saving…'; btn.disabled=true;
  var fd=new FormData();
  fd.append('action','update_song'); fd.append('song_id',id);
  fd.append('title', document.getElementById('sf-title').value.trim());
  fd.append('artist',document.getElementById('sf-artist').value.trim());
  fd.append('album', document.getElementById('sf-album').value.trim());
  var cv=document.getElementById('sf-cover').files[0]; if(cv) fd.append('cover',cv);
  fetch(BASE+'/backend/meta_api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    btn.textContent='Save changes'; btn.disabled=false;
    if(d.ok){ document.getElementById('sf-ok').textContent='Saved!'; document.getElementById('sf-ok').style.display='block'; document.getElementById('sf-err').style.display='none'; }
    else    { document.getElementById('sf-err').textContent=d.error; document.getElementById('sf-err').style.display='block'; }
  });
}

/* ── ALBUMS ────────────────────────────────────────────────────────────── */
document.getElementById('album-search').addEventListener('input', debounce(function() {
  var q=this.value.trim(); if(q.length<2){ document.getElementById('album-results').innerHTML=''; return; }
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'search_albums',q:q})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var html='';
    d.data.forEach(function(al){
      html+='<div class="edit-result" data-id="'+al.id+'"><div class="edit-result-art">'+imgThumb(al.cover)+'</div><div><div class="edit-result-name">'+al.name+'</div><div class="edit-result-sub">'+al.artist+'</div></div><div class="edit-result-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div></div>';
    });
    document.getElementById('album-results').innerHTML=html;
    document.querySelectorAll('#album-results .edit-result').forEach(function(el){
      el.addEventListener('click',function(){ loadAlbumForm(el.dataset.id); });
    });
  });
},300));

function loadAlbumForm(id) {
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_album',album_id:parseInt(id)})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var al=d.data;
    var form=document.getElementById('album-edit-form');
    form.style.display='block';
    document.getElementById('album-results').innerHTML='';
    document.getElementById('album-search').value='';
    form.innerHTML=`
      <div class="edit-form">
        <div class="edit-form-title"><button class="back-link" onclick="document.getElementById('album-edit-form').style.display='none'">← Back</button> Edit Album</div>
        <div class="alert alert-error" id="alf-err" style="display:none"></div>
        <div class="alert alert-success" id="alf-ok" style="display:none"></div>
        <div class="form-group"><label>Album Name</label><input type="text" id="alf-name" value="${escHtml(al.name||'')}"></div>
        <div class="form-group"><label>Artist</label><input type="text" id="alf-artist" value="${escHtml(al.artist||'')}"></div>
        <div class="form-group"><label>Genre</label><input type="text" id="alf-genre" value="${escHtml(al.genre||'')}"></div>
        <div class="form-group"><label>Year</label><input type="number" id="alf-year" value="${al.year||''}" min="1900" max="2099"></div>
        <div class="form-group">
          <label>Cover</label>
          <div class="img-pick">
            <div class="img-thumb" id="alf-thumb">${al.cover?'<img src="'+BASE+'/'+al.cover+'" alt="">':'<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'26\' height=\'26\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' style=\'color:var(--tf)\'><circle cx=\'12\' cy=\'12\' r=\'10\'/><circle cx=\'12\' cy=\'12\' r=\'3\'/></svg>'}</div>
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">Change cover<input type="file" id="alf-cover" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
          </div>
        </div>
        <button class="btn btn-primary" id="alf-save" style="border-radius:10px;margin-top:4px" onclick="saveAlbum(${al.id})">Save changes</button>
      </div>`;
    bindImgPreview('alf-cover','alf-thumb');
  });
}

function saveAlbum(id) {
  var btn=document.getElementById('alf-save'); btn.textContent='Saving…'; btn.disabled=true;
  var fd=new FormData();
  fd.append('action','update_album'); fd.append('album_id',id);
  fd.append('name',  document.getElementById('alf-name').value.trim());
  fd.append('artist',document.getElementById('alf-artist').value.trim());
  fd.append('genre', document.getElementById('alf-genre').value.trim());
  fd.append('year',  document.getElementById('alf-year').value);
  var cv=document.getElementById('alf-cover').files[0]; if(cv) fd.append('cover',cv);
  fetch(BASE+'/backend/meta_api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    btn.textContent='Save changes'; btn.disabled=false;
    if(d.ok){ document.getElementById('alf-ok').textContent='Saved!'; document.getElementById('alf-ok').style.display='block'; }
    else    { document.getElementById('alf-err').textContent=d.error; document.getElementById('alf-err').style.display='block'; }
  });
}

/* ── ARTISTS ───────────────────────────────────────────────────────────── */
document.getElementById('artist-search').addEventListener('input', debounce(function() {
  var q=this.value.trim(); if(q.length<2){ document.getElementById('artist-results').innerHTML=''; return; }
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'search_artists',q:q})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var html='';
    d.data.forEach(function(a){
      html+='<div class="edit-result" data-id="'+a.id+'"><div class="edit-result-art" style="border-radius:50%">'+imgThumb(a.image,true)+'</div><div><div class="edit-result-name">'+a.name+'</div></div><div class="edit-result-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div></div>';
    });
    document.getElementById('artist-results').innerHTML=html;
    document.querySelectorAll('#artist-results .edit-result').forEach(function(el){
      el.addEventListener('click',function(){ loadArtistForm(el.dataset.id); });
    });
  });
},300));

function loadArtistForm(id) {
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_artist',artist_id:parseInt(id)})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var a=d.data;
    var form=document.getElementById('artist-edit-form');
    form.style.display='block';
    document.getElementById('artist-results').innerHTML='';
    document.getElementById('artist-search').value='';
    form.innerHTML=`
      <div class="edit-form">
        <div class="edit-form-title"><button class="back-link" onclick="document.getElementById('artist-edit-form').style.display='none'">← Back</button> Edit Artist</div>
        <div class="alert alert-error" id="arf-err" style="display:none"></div>
        <div class="alert alert-success" id="arf-ok" style="display:none"></div>
        <div class="form-group"><label>Name</label><input type="text" id="arf-name" value="${escHtml(a.name||'')}"></div>
        <div class="form-group">
          <label>Profile Picture</label>
          <div class="img-pick">
            <div class="img-thumb" style="border-radius:50%" id="arf-img-thumb">${a.image?'<img src="'+BASE+'/'+a.image+'" alt="" style="border-radius:50%">':'<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'26\' height=\'26\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' style=\'color:var(--tf)\'><path d=\'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\'/><circle cx=\'12\' cy=\'7\' r=\'4\'/></svg>'}</div>
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">Change photo<input type="file" id="arf-image" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
          </div>
        </div>
        <div class="form-group">
          <label>Banner</label>
          <div style="width:100%;height:80px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border);overflow:hidden;margin-bottom:10px;display:flex;align-items:center;justify-content:center" id="arf-banner-thumb">${a.banner?'<img src="'+BASE+'/'+a.banner+'" alt="" style="width:100%;height:100%;object-fit:cover">':'<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'28\' height=\'28\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' style=\'color:var(--tf)\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg>'}</div>
          <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">Change banner<input type="file" id="arf-banner" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
        </div>
        <button class="btn btn-primary" id="arf-save" style="border-radius:10px;margin-top:4px" onclick="saveArtist(${a.id})">Save changes</button>
      </div>`;
    bindImgPreview('arf-image','arf-img-thumb');
    bindImgPreview('arf-banner','arf-banner-thumb');
  });
}

function saveArtist(id) {
  var btn=document.getElementById('arf-save'); btn.textContent='Saving…'; btn.disabled=true;
  var fd=new FormData();
  fd.append('action','update_artist'); fd.append('artist_id',id);
  fd.append('name',document.getElementById('arf-name').value.trim());
  var img=document.getElementById('arf-image').files[0]; if(img) fd.append('image',img);
  var ban=document.getElementById('arf-banner').files[0]; if(ban) fd.append('banner',ban);
  fetch(BASE+'/backend/meta_api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    btn.textContent='Save changes'; btn.disabled=false;
    if(d.ok){ document.getElementById('arf-ok').textContent='Saved!'; document.getElementById('arf-ok').style.display='block'; }
    else    { document.getElementById('arf-err').textContent=d.error; document.getElementById('arf-err').style.display='block'; }
  });
}

/* ── AUDIOBOOKS ────────────────────────────────────────────────────────── */
document.getElementById('ab-search').addEventListener('input', debounce(function() {
  var q=this.value.trim(); if(q.length<2){ document.getElementById('ab-results').innerHTML=''; return; }
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'search_audiobooks',q:q})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var html='';
    d.data.forEach(function(ab){
      html+='<div class="edit-result" data-id="'+ab.id+'"><div class="edit-result-art">'+imgThumb(ab.cover)+'</div><div><div class="edit-result-name">'+ab.title+'</div><div class="edit-result-sub">'+(ab.series||'No series')+'</div></div><div class="edit-result-arrow"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div></div>';
    });
    document.getElementById('ab-results').innerHTML=html;
    document.querySelectorAll('#ab-results .edit-result').forEach(function(el){
      el.addEventListener('click',function(){ loadAbForm(el.dataset.id); });
    });
  });
},300));

function loadAbForm(id) {
  fetch(BASE+'/backend/meta_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_audiobook',audiobook_id:parseInt(id)})})
  .then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    var ab=d.data;
    var form=document.getElementById('ab-edit-form');
    form.style.display='block';
    document.getElementById('ab-results').innerHTML='';
    document.getElementById('ab-search').value='';
    form.innerHTML=`
      <div class="edit-form">
        <div class="edit-form-title"><button class="back-link" onclick="document.getElementById('ab-edit-form').style.display='none'">← Back</button> Edit Hörbuch</div>
        <div class="alert alert-error" id="abf-err" style="display:none"></div>
        <div class="alert alert-success" id="abf-ok" style="display:none"></div>
        <div class="form-group"><label>Title</label><input type="text" id="abf-title" value="${escHtml(ab.title||'')}"></div>
        <div class="form-group"><label>Reihe / Serie</label><input type="text" id="abf-series" value="${escHtml(ab.series||'')}"></div>
        <div class="form-group"><label>Sprecher</label><input type="text" id="abf-narrator" value="${escHtml(ab.narrator||'')}"></div>
        <div class="form-group">
          <label>Cover</label>
          <div class="img-pick">
            <div class="img-thumb" id="abf-thumb">${ab.cover?'<img src="'+BASE+'/'+ab.cover+'" alt="">':'<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'26\' height=\'26\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' style=\'color:var(--tf)\'><circle cx=\'12\' cy=\'12\' r=\'10\'/><circle cx=\'12\' cy=\'12\' r=\'3\'/></svg>'}</div>
            <label class="btn btn-secondary" style="width:auto;padding:9px 18px;font-size:13px;border-radius:8px;cursor:pointer;display:inline-flex">Change cover<input type="file" id="abf-cover" accept="image/jpeg,image/png,image/webp" style="display:none"></label>
          </div>
        </div>
        <button class="btn btn-primary" id="abf-save" style="border-radius:10px;margin-top:4px" onclick="saveAb(${ab.id})">Save changes</button>
      </div>`;
    bindImgPreview('abf-cover','abf-thumb');
  });
}

function saveAb(id) {
  var btn=document.getElementById('abf-save'); btn.textContent='Saving…'; btn.disabled=true;
  var fd=new FormData();
  fd.append('action','update_audiobook'); fd.append('audiobook_id',id);
  fd.append('title',   document.getElementById('abf-title').value.trim());
  fd.append('series',  document.getElementById('abf-series').value.trim());
  fd.append('narrator',document.getElementById('abf-narrator').value.trim());
  var cv=document.getElementById('abf-cover').files[0]; if(cv) fd.append('cover',cv);
  fetch(BASE+'/backend/meta_api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    btn.textContent='Save changes'; btn.disabled=false;
    if(d.ok){ document.getElementById('abf-ok').textContent='Saved!'; document.getElementById('abf-ok').style.display='block'; }
    else    { document.getElementById('abf-err').textContent=d.error; document.getElementById('abf-err').style.display='block'; }
  });
}

/* ── Shared helpers ─────────────────────────────────────────────────────── */
function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function bindImgPreview(inputId, thumbId) {
  var inp=document.getElementById(inputId);
  if(!inp) return;
  inp.addEventListener('change',function(){
    var f=this.files[0]; if(!f) return;
    var r=new FileReader();
    r.onload=function(e){ var t=document.getElementById(thumbId); if(t) t.innerHTML='<img src="'+e.target.result+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'; };
    r.readAsDataURL(f);
  });
}
</script>

<?php adminFoot(); ?>
