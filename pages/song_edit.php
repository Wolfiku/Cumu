<?php
/**
 * CUMU – pages/song_edit.php
 * Admin: edit song title, artist, album, and cover image.
 * Access: /pages/song_edit.php?id=<song_id>
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireAdmin();

$db  = getDB();
$b   = BASE_URL;
$sid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$sid) { header('Location: ' . $b . '/pages/home.php'); exit; }

// Fetch song with artist + album
$sq = $db->prepare('SELECT s.*,a.name AS artist_name,al.name AS album_name,al.cover AS album_cover
    FROM songs s
    LEFT JOIN artists a  ON a.id=s.artist_id
    LEFT JOIN albums  al ON al.id=s.album_id
    WHERE s.id=? LIMIT 1');
$sq->execute([$sid]); $song = $sq->fetch();
if (!$song) { header('Location: ' . $b . '/pages/home.php'); exit; }

$cover = $song['album_cover'] ? $b . '/' . $song['album_cover'] : '';
$error = $success = null;

adminHead(h($song['title']) . ' – Edit', '');
?>

<div style="max-width:560px">

  <div style="margin-bottom:20px">
    <a href="javascript:history.back()" style="font-size:13px;color:var(--t2);display:inline-flex;align-items:center;gap:4px">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
  </div>

  <div style="font-size:20px;font-weight:800;margin-bottom:20px">Edit Song</div>

  <div id="msg-error"   class="alert alert-error"   style="display:none"></div>
  <div id="msg-success" class="alert alert-success" style="display:none"></div>

  <!-- Metadata form -->
  <div class="admin-card" style="margin-bottom:20px">
    <div class="admin-card-head">Metadata</div>
    <div style="padding:20px">
      <div class="form-group">
        <label>Title</label>
        <input type="text" id="f-title"  value="<?= h($song['title']) ?>" maxlength="200">
      </div>
      <div class="form-group">
        <label>Artist</label>
        <input type="text" id="f-artist" value="<?= h($song['artist_name'] ?? '') ?>" maxlength="200">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Album</label>
        <input type="text" id="f-album"  value="<?= h($song['album_name'] ?? '') ?>" maxlength="200">
      </div>
    </div>
    <div style="padding:0 20px 20px">
      <button class="btn btn-primary" id="save-meta-btn" style="width:auto;padding:11px 24px">Save changes</button>
    </div>
  </div>

  <!-- Cover image -->
  <div class="admin-card">
    <div class="admin-card-head">Cover Image</div>
    <div style="padding:20px;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
      <div id="cover-preview" style="width:120px;height:120px;border-radius:var(--rl);background:var(--bg-card);border:1px solid var(--border);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?php if ($cover): ?>
          <img src="<?= h($cover) ?>" alt="" style="width:100%;height:100%;object-fit:cover" id="cover-img">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--tf)"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:200px">
        <div style="font-size:13px;color:var(--t2);margin-bottom:14px;line-height:1.6">
          Upload a new cover image. This updates the cover for the entire album and artist.
          <br>JPEG, PNG or WebP · Max 5 MB
        </div>
        <label class="btn btn-secondary" style="width:auto;cursor:pointer;display:inline-flex">
          Choose image
          <input type="file" id="cover-input" accept="image/jpeg,image/png,image/webp" style="display:none">
        </label>
        <div id="cover-file-name" style="font-size:12px;color:var(--tf);margin-top:8px"></div>
      </div>
    </div>
    <div style="padding:0 20px 20px">
      <button class="btn btn-primary" id="save-cover-btn" disabled style="width:auto;padding:11px 24px;opacity:.4">
        Upload cover
      </button>
    </div>
  </div>

  <!-- Song file path (read-only info) -->
  <div style="margin-top:16px;padding:14px 16px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--rl)">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--tf);margin-bottom:6px">File Path</div>
    <code style="font-size:12px;color:var(--t2);word-break:break-all"><?= h($song['path']) ?></code>
  </div>

</div>

<script>
const SID  = <?= $sid ?>;
const BASE = window.CUMU_BASE || '';

const errEl = document.getElementById('msg-error');
const okEl  = document.getElementById('msg-success');

function showErr(msg) { errEl.textContent=msg; errEl.style.display='block'; okEl.style.display='none'; window.scrollTo(0,0); }
function showOk(msg)  { okEl.textContent=msg;  okEl.style.display='block'; errEl.style.display='none'; window.scrollTo(0,0); }

// Save metadata
document.getElementById('save-meta-btn').addEventListener('click', async () => {
  const title  = document.getElementById('f-title').value.trim();
  const artist = document.getElementById('f-artist').value.trim();
  const album  = document.getElementById('f-album').value.trim();
  if (!title) { showErr('Title is required.'); return; }

  const btn = document.getElementById('save-meta-btn');
  btn.textContent = 'Saving…'; btn.disabled = true;

  const r = await fetch(BASE + '/backend/meta_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'update_song', song_id: SID, title, artist, album })
  });
  const d = await r.json();
  btn.textContent = 'Save changes'; btn.disabled = false;
  if (d.ok) { showOk('Saved! Artist: ' + d.data.artist + ' — Album: ' + d.data.album); }
  else { showErr(d.error); }
});

// Cover file picker preview
const coverInput = document.getElementById('cover-input');
const coverBtn   = document.getElementById('save-cover-btn');

coverInput.addEventListener('change', () => {
  const file = coverInput.files[0];
  if (!file) return;
  document.getElementById('cover-file-name').textContent = file.name + ' (' + (file.size/1024/1024).toFixed(1) + ' MB)';
  coverBtn.disabled = false; coverBtn.style.opacity = '1';

  // Preview
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('cover-preview');
    let img = document.getElementById('cover-img');
    if (!img) { img = document.createElement('img'); img.id='cover-img'; img.style.cssText='width:100%;height:100%;object-fit:cover'; preview.innerHTML=''; preview.appendChild(img); }
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
});

// Upload cover
coverBtn.addEventListener('click', async () => {
  const file = coverInput.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('action', 'update_cover');
  fd.append('song_id', SID);
  fd.append('cover', file);

  coverBtn.textContent = 'Uploading…'; coverBtn.disabled = true;

  const r = await fetch(BASE + '/backend/meta_api.php', { method: 'POST', body: fd });
  const d = await r.json();
  coverBtn.textContent = 'Upload cover'; coverBtn.disabled = false; coverBtn.style.opacity = '1';

  if (d.ok) {
    showOk('Cover updated!');
    const img = document.getElementById('cover-img');
    if (img) img.src = d.data.cover + '?t=' + Date.now();
  } else {
    showErr(d.error);
  }
});
</script>

<?php adminFoot(); ?>
