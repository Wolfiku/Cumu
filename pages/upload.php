<?php
/**
 * CUMU – pages/upload.php
 * 3 tabs: Song Upload | Album Editor | Artist Editor
 * Publishers and admins only.
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requirePublisher();

$db = getDB();
$b  = BASE_URL;

// Fetch artists and albums for selects
$artists = $db->query('SELECT id, name FROM artists ORDER BY name ASC')->fetchAll();
$albums  = $db->query('SELECT al.id, al.name, a.name AS artist_name FROM albums al LEFT JOIN artists a ON a.id=al.artist_id ORDER BY a.name, al.name')->fetchAll();

adminHead('Upload & Manage', 'upload');
?>

<style>
/* Tab styles already in style.css – extra upload-page specifics */
.meta-fields{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--rl);padding:16px;margin-top:16px}
.meta-fields .form-group:last-child{margin-bottom:0}
.art-type-row{display:flex;gap:8px;margin-bottom:0}
.art-type-btn{flex:1;padding:10px 8px;border:2px solid var(--border-m);border-radius:var(--r);background:none;color:var(--t2);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;transition:border-color .15s,color .15s,background .15s;text-align:center}
.art-type-btn.active{border-color:var(--accent);color:var(--accent);background:var(--accent-soft)}
.prog{height:3px;background:var(--bg-hover);border-radius:2px;margin-top:14px;overflow:hidden}
.prog-fill{height:100%;background:var(--accent);width:0%;transition:width .3s;border-radius:2px}
.img-preview{width:100%;aspect-ratio:1;border-radius:var(--r);background:var(--bg-hover);display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border);margin-bottom:10px}
.img-preview img{width:100%;height:100%;object-fit:cover;display:block}
.artist-banner-preview{width:100%;height:100px;border-radius:var(--r);background:var(--bg-hover);display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border);margin-bottom:10px}
.artist-banner-preview img{width:100%;height:100%;object-fit:cover;display:block}
</style>

<!-- Tab bar -->
<div class="tab-bar" id="tab-bar">
  <button class="tab-btn active" data-tab="song">Song Upload</button>
  <button class="tab-btn" data-tab="album">Album Editor</button>
  <button class="tab-btn" data-tab="artist">Artist Editor</button>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 1 – SONG UPLOAD
     ══════════════════════════════════════════════════════════ -->
<div class="tab-pane active" id="tab-song">

  <!-- Drop zone -->
  <div class="drop-zone" id="dz">
    <svg class="drop-zone-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="17 8 12 3 7 8"/>
      <line x1="12" y1="3" x2="12" y2="15"/>
    </svg>
    <div class="drop-zone-label">Drop audio files here</div>
    <div class="drop-zone-sub">MP3, FLAC, OGG, WAV, M4A · Max 100 MB each</div>
    <label class="btn btn-secondary" style="width:auto;cursor:pointer;font-size:13px;padding:9px 18px">
      Browse files
      <input type="file" id="file-input" multiple
             accept=".mp3,.flac,.ogg,.wav,.m4a,.aac,.opus" style="display:none">
    </label>
  </div>

  <div id="file-list" style="margin-top:12px"></div>

  <!-- Metadata override (shown when file has no/incomplete tags) -->
  <div class="meta-fields" id="meta-fields" style="display:none">
    <div style="font-size:13px;font-weight:700;color:var(--t2);margin-bottom:14px;text-transform:uppercase;letter-spacing:.06em">
      Fallback Metadata
      <span style="font-size:11px;font-weight:500;color:var(--tf);text-transform:none;letter-spacing:0;margin-left:6px">(used only if ID3 tags are missing)</span>
    </div>

    <!-- Art type: single / album -->
    <div class="form-group">
      <label>Type</label>
      <div class="art-type-row">
        <button class="art-type-btn active" id="type-single" type="button">Single</button>
        <button class="art-type-btn" id="type-album"  type="button">Album</button>
      </div>
    </div>

    <div class="form-group">
      <label>Artist / Interpreter</label>
      <input type="text" id="up-artist" placeholder="Artist name" list="artist-list" autocomplete="off">
      <datalist id="artist-list">
        <?php foreach ($artists as $a): ?>
          <option value="<?php echo h($a['name']); ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <!-- Album select (shown only for album type) -->
    <div class="form-group" id="album-group" style="display:none">
      <label>Album</label>
      <select id="up-album-select">
        <option value="">— Select existing album —</option>
        <?php foreach ($albums as $al): ?>
          <option value="<?php echo h($al['name']); ?>" data-artist="<?php echo h($al['artist_name']); ?>">
            <?php echo h($al['artist_name'] . ' – ' . $al['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Cover upload -->
    <div class="form-group">
      <label>Album Cover</label>
      <div class="img-preview" id="cover-preview-song" style="width:100px;height:100px">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <label class="btn btn-secondary" style="width:auto;cursor:pointer;font-size:13px;padding:9px 18px;border-radius:var(--r)">
        Choose cover
        <input type="file" id="cover-input-song" accept="image/jpeg,image/png,image/webp" style="display:none">
      </label>
    </div>

    <!-- Feature toggle -->
    <div class="toggle-row">
      <div>
        <div class="toggle-label">Feature on Home</div>
        <div class="toggle-sub">Shows this song/single in a featured banner on the home screen</div>
      </div>
      <label class="toggle">
        <input type="checkbox" id="up-featured">
        <div class="toggle-track"></div>
        <div class="toggle-thumb"></div>
      </label>
    </div>
  </div>

  <div class="prog" id="up-prog" style="display:none"><div class="prog-fill" id="up-prog-fill"></div></div>

  <button class="btn btn-primary" id="upload-btn" disabled
          style="margin-top:16px;opacity:.4;border-radius:var(--r)">
    Upload
  </button>

  <div id="up-results" style="margin-top:16px"></div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 2 – ALBUM EDITOR
     ══════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-album">

  <!-- Select existing album -->
  <div class="form-group">
    <label>Select Album</label>
    <select id="al-select">
      <option value="">— Choose album to edit —</option>
      <?php foreach ($albums as $al): ?>
        <option value="<?php echo (int)$al['id']; ?>">
          <?php echo h($al['artist_name'] . ' – ' . $al['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="al-editor" style="display:none">
    <div class="meta-fields">
      <div class="form-group">
        <label>Album Name</label>
        <input type="text" id="al-name" placeholder="Album name">
      </div>
      <div class="form-group">
        <label>Artist</label>
        <input type="text" id="al-artist" placeholder="Artist name" list="artist-list">
      </div>
      <div class="form-group">
        <label>Genre</label>
        <input type="text" id="al-genre" placeholder="e.g. Electronic, Hip-Hop">
      </div>
      <div class="form-group">
        <label>Year</label>
        <input type="number" id="al-year" placeholder="2024" min="1900" max="2099">
      </div>
      <div class="form-group">
        <label>Album Cover</label>
        <div style="display:flex;gap:12px;align-items:flex-start">
          <div class="img-preview" id="al-cover-preview" style="width:90px;height:90px;flex-shrink:0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
          <label class="btn btn-secondary" style="width:auto;cursor:pointer;font-size:13px;padding:9px 18px;border-radius:var(--r);margin-top:0;align-self:flex-end">
            Change cover
            <input type="file" id="al-cover-input" accept="image/jpeg,image/png,image/webp" style="display:none">
          </label>
        </div>
      </div>
      <!-- Feature toggle -->
      <div class="toggle-row">
        <div>
          <div class="toggle-label">Feature on Home</div>
          <div class="toggle-sub">Shows this album in a featured banner on the home screen</div>
        </div>
        <label class="toggle">
          <input type="checkbox" id="al-featured">
          <div class="toggle-track"></div>
          <div class="toggle-thumb"></div>
        </label>
      </div>
    </div>

    <div id="al-msg" style="margin-top:12px"></div>
    <button class="btn btn-primary" id="al-save-btn"
            style="margin-top:14px;border-radius:var(--r)">Save Album</button>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 3 – ARTIST EDITOR
     ══════════════════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-artist">

  <div class="form-group">
    <label>Select Artist</label>
    <select id="ar-select">
      <option value="">— Choose artist to edit —</option>
      <?php foreach ($artists as $a): ?>
        <option value="<?php echo (int)$a['id']; ?>">
          <?php echo h($a['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="ar-editor" style="display:none">
    <div class="meta-fields">
      <div class="form-group">
        <label>Artist Name</label>
        <input type="text" id="ar-name" placeholder="Artist name">
      </div>

      <div class="form-group">
        <label>Profile Picture</label>
        <div style="display:flex;gap:12px;align-items:flex-end">
          <div class="img-preview" id="ar-img-preview" style="width:80px;height:80px;border-radius:50%;flex-shrink:0">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <label class="btn btn-secondary" style="width:auto;cursor:pointer;font-size:13px;padding:9px 18px;border-radius:var(--r)">
            Upload photo
            <input type="file" id="ar-img-input" accept="image/jpeg,image/png,image/webp" style="display:none">
          </label>
        </div>
      </div>

      <div class="form-group">
        <label>Banner Image <span style="font-size:11px;color:var(--tf);font-weight:400">(shown on artist page header)</span></label>
        <div class="artist-banner-preview" id="ar-banner-preview">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <label class="btn btn-secondary" style="width:auto;cursor:pointer;font-size:13px;padding:9px 18px;border-radius:var(--r)">
          Upload banner
          <input type="file" id="ar-banner-input" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
      </div>
    </div>

    <div id="ar-msg" style="margin-top:12px"></div>
    <button class="btn btn-primary" id="ar-save-btn"
            style="margin-top:14px;border-radius:var(--r)">Save Artist</button>
  </div>
</div>

<script>
var BASE = window.CUMU_BASE || '';

/* ── Tab switching ────────────────────────────────── */
document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

/* ── Art type toggle ──────────────────────────────── */
var artType = 'single';
document.getElementById('type-single').addEventListener('click', function() {
  artType = 'single';
  document.getElementById('type-single').classList.add('active');
  document.getElementById('type-album').classList.remove('active');
  document.getElementById('album-group').style.display = 'none';
});
document.getElementById('type-album').addEventListener('click', function() {
  artType = 'album';
  document.getElementById('type-album').classList.add('active');
  document.getElementById('type-single').classList.remove('active');
  document.getElementById('album-group').style.display = 'block';
});

/* ── File size formatter ──────────────────────────── */
function fmtSize(b) {
  return b < 1048576 ? Math.round(b/1024) + ' KB' : (b/1048576).toFixed(1) + ' MB';
}

/* ── Image file preview ───────────────────────────── */
function previewImg(input, previewEl) {
  input.addEventListener('change', function() {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      previewEl.innerHTML = '<img src="' + e.target.result + '" alt="">';
    };
    reader.readAsDataURL(file);
  });
}
previewImg(document.getElementById('cover-input-song'),  document.getElementById('cover-preview-song'));
previewImg(document.getElementById('al-cover-input'),     document.getElementById('al-cover-preview'));
previewImg(document.getElementById('ar-img-input'),       document.getElementById('ar-img-preview'));
previewImg(document.getElementById('ar-banner-input'),    document.getElementById('ar-banner-preview'));

/* ── Song upload ──────────────────────────────────── */
var files = [];
var dz     = document.getElementById('dz');
var fi     = document.getElementById('file-input');
var fl     = document.getElementById('file-list');
var ubtn   = document.getElementById('upload-btn');

function renderFileList() {
  fl.innerHTML = '';
  files.forEach(function(f, i) {
    var div = document.createElement('div');
    div.className = 'file-item';
    div.id = 'fi-' + i;
    div.innerHTML = '<div class="file-item-name">' + f.name + '</div><div class="file-item-size">' + fmtSize(f.size) + '</div>';
    fl.appendChild(div);
  });
  ubtn.disabled = files.length === 0;
  ubtn.style.opacity = files.length ? '1' : '.4';
  if (files.length > 0) document.getElementById('meta-fields').style.display = 'block';
}

function addFiles(list) {
  var exts = ['mp3','flac','ogg','wav','m4a','aac','opus'];
  Array.prototype.forEach.call(list, function(f) {
    var ext = f.name.split('.').pop().toLowerCase();
    if (exts.indexOf(ext) >= 0 && !files.find(function(x) { return x.name === f.name && x.size === f.size; })) {
      files.push(f);
    }
  });
  renderFileList();
}

dz.addEventListener('dragover',  function(e) { e.preventDefault(); dz.classList.add('over'); });
dz.addEventListener('dragleave', function()  { dz.classList.remove('over'); });
dz.addEventListener('drop',      function(e) { e.preventDefault(); dz.classList.remove('over'); addFiles(e.dataTransfer.files); });
dz.addEventListener('click',     function(e) { if (!e.target.closest('label')) fi.click(); });
fi.addEventListener('change',    function()  { addFiles(fi.files); });

ubtn.addEventListener('click', function() {
  if (!files.length) return;
  ubtn.disabled = true; ubtn.textContent = 'Uploading…';
  var prog = document.getElementById('up-prog');
  var pf   = document.getElementById('up-prog-fill');
  prog.style.display = 'block'; pf.style.width = '0%';

  var fd = new FormData();
  files.forEach(function(f) { fd.append('files[]', f); });
  fd.append('artist',   document.getElementById('up-artist').value.trim() || 'Unknown Artist');
  fd.append('album',    artType === 'album' ? (document.getElementById('up-album-select').value || 'Unknown Album') : '');
  fd.append('art_type', artType);
  fd.append('featured', document.getElementById('up-featured').checked ? '1' : '0');

  var cover = document.getElementById('cover-input-song').files[0];
  if (cover) fd.append('cover', cover);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', BASE + '/backend/upload_api.php');
  xhr.upload.onprogress = function(e) {
    if (e.lengthComputable) pf.style.width = (e.loaded / e.total * 100) + '%';
  };
  xhr.onload = function() {
    pf.style.width = '100%';
    var res = document.getElementById('up-results');
    try {
      var d = JSON.parse(xhr.responseText);
      if (d.ok) {
        var html = '';
        d.data.uploaded.forEach(function(u) {
          html += '<div class="file-item"><div class="file-item-name"><strong>' + u.title + '</strong> — ' + u.artist + '</div><div class="file-item-status" style="color:var(--accent)">✓</div></div>';
        });
        d.data.errors.forEach(function(e) {
          html += '<div class="file-item"><div class="file-item-name" style="color:#ff9090">' + e + '</div></div>';
        });
        res.innerHTML = html;
        if (d.data.uploaded.length) { files = []; renderFileList(); }
      } else {
        res.innerHTML = '<div class="alert alert-error">' + d.error + '</div>';
      }
    } catch(e) {
      res.innerHTML = '<div class="alert alert-error">Parse error</div>';
    }
    ubtn.disabled = false; ubtn.textContent = 'Upload';
  };
  xhr.onerror = function() {
    document.getElementById('up-results').innerHTML = '<div class="alert alert-error">Network error</div>';
    ubtn.disabled = false; ubtn.textContent = 'Upload';
  };
  xhr.send(fd);
});

/* ── Album editor ─────────────────────────────────── */
document.getElementById('al-select').addEventListener('change', function() {
  var id = this.value;
  if (!id) { document.getElementById('al-editor').style.display = 'none'; return; }
  fetch(BASE + '/backend/meta_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_album', album_id: parseInt(id) })
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (!d.ok) return;
    var al = d.data;
    document.getElementById('al-name').value   = al.name   || '';
    document.getElementById('al-artist').value = al.artist || '';
    document.getElementById('al-genre').value  = al.genre  || '';
    document.getElementById('al-year').value   = al.year   || '';
    document.getElementById('al-featured').checked = al.featured == 1;
    var prev = document.getElementById('al-cover-preview');
    if (al.cover) {
      prev.innerHTML = '<img src="' + BASE + '/' + al.cover + '" alt="">';
    }
    document.getElementById('al-editor').style.display = 'block';
  });
});

document.getElementById('al-save-btn').addEventListener('click', function() {
  var id = document.getElementById('al-select').value;
  if (!id) return;
  var btn = this; btn.textContent = 'Saving…'; btn.disabled = true;
  var fd = new FormData();
  fd.append('action',   'update_album');
  fd.append('album_id', id);
  fd.append('name',     document.getElementById('al-name').value.trim());
  fd.append('artist',   document.getElementById('al-artist').value.trim());
  fd.append('genre',    document.getElementById('al-genre').value.trim());
  fd.append('year',     document.getElementById('al-year').value);
  fd.append('featured', document.getElementById('al-featured').checked ? '1' : '0');
  var cover = document.getElementById('al-cover-input').files[0];
  if (cover) fd.append('cover', cover);

  fetch(BASE + '/backend/meta_api.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var msg = document.getElementById('al-msg');
      if (d.ok) { msg.innerHTML = '<div class="alert alert-success">Saved!</div>'; }
      else       { msg.innerHTML = '<div class="alert alert-error">' + d.error + '</div>'; }
      btn.textContent = 'Save Album'; btn.disabled = false;
    });
});

/* ── Artist editor ────────────────────────────────── */
document.getElementById('ar-select').addEventListener('change', function() {
  var id = this.value;
  if (!id) { document.getElementById('ar-editor').style.display = 'none'; return; }
  fetch(BASE + '/backend/meta_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_artist', artist_id: parseInt(id) })
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (!d.ok) return;
    var ar = d.data;
    document.getElementById('ar-name').value = ar.name || '';
    if (ar.image) document.getElementById('ar-img-preview').innerHTML = '<img src="' + BASE + '/' + ar.image + '" alt="">';
    if (ar.banner) document.getElementById('ar-banner-preview').innerHTML = '<img src="' + BASE + '/' + ar.banner + '" alt="">';
    document.getElementById('ar-editor').style.display = 'block';
  });
});

document.getElementById('ar-save-btn').addEventListener('click', function() {
  var id = document.getElementById('ar-select').value;
  if (!id) return;
  var btn = this; btn.textContent = 'Saving…'; btn.disabled = true;
  var fd = new FormData();
  fd.append('action',    'update_artist');
  fd.append('artist_id', id);
  fd.append('name',      document.getElementById('ar-name').value.trim());
  var img    = document.getElementById('ar-img-input').files[0];
  var banner = document.getElementById('ar-banner-input').files[0];
  if (img)    fd.append('image',  img);
  if (banner) fd.append('banner', banner);

  fetch(BASE + '/backend/meta_api.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var msg = document.getElementById('ar-msg');
      if (d.ok) { msg.innerHTML = '<div class="alert alert-success">Saved!</div>'; }
      else       { msg.innerHTML = '<div class="alert alert-error">' + d.error + '</div>'; }
      btn.textContent = 'Save Artist'; btn.disabled = false;
    });
});
</script>

<?php adminFoot(); ?>
