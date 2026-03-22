<?php
/**
 * CUMU – pages/mixtape.php
 * View + manage a single mixtape.
 */
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();

$db  = getDB();
$b   = BASE_URL;
$uid = currentUserId();

$mid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$mid) { header('Location:' . $b . '/pages/library.php'); exit; }

// Fetch mixtape
$mq = $db->prepare('SELECT m.*, u.username AS creator FROM mixtapes m LEFT JOIN users u ON u.id=m.user_id WHERE m.id=? LIMIT 1');
$mq->execute([$mid]); $mixtape = $mq->fetch();
if (!$mixtape) { header('Location:' . $b . '/pages/library.php'); exit; }

// Fetch songs with artist metadata from ID3 / DB
$sq = $db->prepare(
  'SELECT s.id, s.title, s.duration, a.name AS artist, al.name AS album, al.cover, a.id AS artist_id, ms.position
   FROM mixtape_songs ms
   JOIN songs s ON s.id = ms.song_id
   LEFT JOIN artists a  ON a.id = s.artist_id
   LEFT JOIN albums  al ON al.id = s.album_id
   WHERE ms.mixtape_id = ?
   ORDER BY ms.position, ms.added_at'
);
$sq->execute([$mid]); $songs = $sq->fetchAll();

$canEdit = ($mixtape['user_id'] === $uid || isAdmin());
$songCount = count($songs);

appOpen(h($mixtape['name']));
?>

<style>
/* ── Mixtape header ─────────────────────────────────────── */
.mx-header {
  position: relative;
  padding: 20px 16px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  background: linear-gradient(180deg,#1e1a2e 0%,var(--bg) 100%);
}
.mx-cassette-art {
  width: 200px; height: 134px;
  margin-bottom: 16px;
  border-radius: 8px;
  overflow: visible;
  position: relative;
}
/* Scale cassette to fit */
.mx-cassette-art .card {
  transform: scale(0.665);
  transform-origin: top left;
}
.mx-title   { font-size:20px;font-weight:800;letter-spacing:-.4px;margin-bottom:4px; }
.mx-creator { font-size:13px;color:var(--t2); }
.mx-meta    { font-size:12px;color:var(--tf);margin-top:4px; }

/* ── Back button ──────────────────────────────────────────── */
.back-btn-inline {
  position:absolute;top:calc(env(safe-area-inset-top,0px)+10px);left:10px;
  width:36px;height:36px;background:rgba(0,0,0,.4);border:none;border-radius:50%;
  cursor:pointer;color:var(--t1);display:flex;align-items:center;justify-content:center;
  -webkit-tap-highlight-color:transparent;
}
</style>

<!-- Header with cassette art -->
<div class="mx-header">
  <button class="back-btn-inline" onclick="history.back()">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>

  <!-- Cassette preview -->
  <div class="mx-cassette-art">
    <div class="card" id="mx-card">
      <div class="ups">
        <div class="screw1">+</div>
        <div class="screw2">+</div>
      </div>
      <div class="card1">
        <div class="line1"></div>
        <div class="line2"></div>
        <div class="mixtape-label-name"><?= h(mb_strimwidth($mixtape['name'], 0, 30, '…')) ?></div>
        <div class="yl">
          <div class="roll">
            <div class="s_wheel"></div>
            <div class="tape"></div>
            <div class="e_wheel"></div>
          </div>
          <p class="num"><?= $songCount ?></p>
        </div>
        <div class="or">
          <p class="time"><?= h(mb_strimwidth($mixtape['name'], 0, 28, '…')) ?></p>
        </div>
      </div>
      <div class="card2_main">
        <div class="card2">
          <div class="c1"></div>
          <div class="t1"></div>
          <div class="screw5">+</div>
          <div class="t2"></div>
          <div class="c2"></div>
        </div>
      </div>
      <div class="downs">
        <div class="screw3">+</div>
        <div class="screw4">+</div>
      </div>
    </div>
  </div>

  <div class="mx-title"><?= h($mixtape['name']) ?></div>
  <div class="mx-creator">by <?= h($mixtape['creator']) ?></div>
  <div class="mx-meta"><?= $songCount ?> song<?= $songCount != 1 ? 's' : '' ?></div>
</div>

<!-- Play all row -->
<?php if (!empty($songs)): ?>
<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 20px 4px">
  <?php if ($canEdit): ?>
    <button id="rename-btn" style="background:none;border:none;cursor:pointer;font-size:13px;color:var(--accent);font-family:var(--font);padding:0;-webkit-tap-highlight-color:transparent">Rename</button>
  <?php else: ?>
    <div></div>
  <?php endif; ?>
  <button class="play-all" id="play-all-btn">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>
  </button>
</div>

<!-- Song list -->
<?php foreach ($songs as $i => $s):
  $cover = $s['cover'] ? $b . '/' . $s['cover'] : '';
?>
  <div class="song-row"
       data-id="<?= (int)$s['id'] ?>"
       data-idx="<?= $i ?>"
       data-stream="<?= $b ?>/backend/stream.php?id=<?= (int)$s['id'] ?>"
       data-title="<?= h($s['title']) ?>"
       data-artist="<?= h($s['artist'] ?? '') ?>"
       data-cover="<?= h($cover) ?>"
       data-artist-id="<?= (int)($s['artist_id'] ?? 0) ?>"
       data-mixtape-id="<?= $mid ?>"
       data-mixtape-name="<?= h($mixtape['name']) ?>">
    <div class="sr-num"><?= $i + 1 ?></div>
    <div class="sr-info">
      <div class="sr-title"><?= h($s['title']) ?></div>
      <div class="sr-artist"><?= h($s['artist'] ?? 'Unknown') ?></div>
    </div>
    <?php if ($canEdit): ?>
      <button class="sr-action rm-from-mx" data-sid="<?= (int)$s['id'] ?>" title="Remove">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
      </button>
    <?php else: ?>
      <button class="sr-action sr-more"
              data-song-id="<?= (int)$s['id'] ?>"
              data-artist-id="<?= (int)($s['artist_id'] ?? 0) ?>"
              data-title="<?= h($s['title']) ?>"
              data-artist="<?= h($s['artist'] ?? '') ?>"
              data-cover="<?= h($cover) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
      </button>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php else: ?>
<div class="empty-state">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
  <div class="empty-state-title">Empty Mixtape</div>
  <div class="empty-state-text">Tap ··· on any song and choose "Add to Mixtape".</div>
</div>
<?php endif; ?>

<!-- Rename sheet -->
<?php if ($canEdit): ?>
<div class="sheet-overlay" id="rename-overlay" style="z-index:310">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Rename Mixtape</div>
    <div style="padding:0 16px 20px">
      <div class="form-group">
        <label>Name</label>
        <input type="text" id="rename-input" value="<?= h($mixtape['name']) ?>" maxlength="100">
      </div>
      <button class="btn btn-primary" id="rename-save" style="border-radius:10px">Save</button>
    </div>
  </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="<?= $b ?>/cassette.css">
<script>
var BASE = window.CUMU_BASE || '';
var MX_ID   = <?= $mid ?>;
var MX_NAME = <?= json_encode($mixtape['name']) ?>;
var MX_COUNT= <?= $songCount ?>;

/* Play all – sends mixtape play message to shell */
document.getElementById('play-all-btn')?.addEventListener('click', function(){
  var rows = document.querySelectorAll('.song-row[data-id]');
  if (!rows.length) return;
  var queue = [];
  rows.forEach(function(row, i){
    row.dataset.idx = i;
    queue.push({
      id: row.dataset.id, stream: row.dataset.stream,
      title: row.dataset.title, artist: row.dataset.artist,
      cover: row.dataset.cover, artistId: row.dataset.artistId
    });
  });
  // Send as mixtape-mode play
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({
      type: 'play_mixtape',
      queue: queue,
      idx: 0,
      mixtape: { id: MX_ID, name: MX_NAME, count: MX_COUNT }
    }, '*');
  }
});

/* Remove from mixtape */
document.querySelectorAll('.rm-from-mx').forEach(function(btn){
  btn.addEventListener('click', async function(e){
    e.stopPropagation();
    var r = await fetch(BASE+'/backend/mixtape_api.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'remove_song',mixtape_id:MX_ID,song_id:parseInt(btn.dataset.sid)})
    });
    var d = await r.json();
    if (d.ok) btn.closest('.song-row').remove();
  });
});

/* Rename */
document.getElementById('rename-btn')?.addEventListener('click', function(){
  document.getElementById('rename-overlay').classList.add('open');
  setTimeout(function(){ document.getElementById('rename-input').focus(); }, 320);
});
document.getElementById('rename-overlay')?.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
document.getElementById('rename-save')?.addEventListener('click', async function(){
  var name = document.getElementById('rename-input').value.trim();
  if (!name) return;
  var r = await fetch(BASE+'/backend/mixtape_api.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'rename',mixtape_id:MX_ID,name:name})
  });
  var d = await r.json();
  if (d.ok) { document.getElementById('rename-overlay').classList.remove('open'); location.reload(); }
});
</script>

<?php appClose('library'); ?>
