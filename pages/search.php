<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();

$db   = getDB();
$b    = BASE_URL;
$q    = trim($_GET['q'] ?? '');
$songs = $artists = $albums = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $ss = $db->prepare('SELECT s.id,s.title,a.name AS artist,al.cover,a.id AS artist_id
        FROM songs s LEFT JOIN artists a ON a.id=s.artist_id LEFT JOIN albums al ON al.id=s.album_id
        WHERE s.title LIKE ? OR a.name LIKE ? OR al.name LIKE ? ORDER BY s.title LIMIT 40');
    $ss->execute([$like,$like,$like]); $songs = $ss->fetchAll();

    $as = $db->prepare('SELECT id,name,image FROM artists WHERE name LIKE ? ORDER BY name LIMIT 8');
    $as->execute([$like]); $artists = $as->fetchAll();

    $als = $db->prepare('SELECT al.id,al.name,al.cover,a.name AS artist_name
        FROM albums al LEFT JOIN artists a ON a.id=al.artist_id
        WHERE al.name LIKE ? OR a.name LIKE ? ORDER BY al.name LIMIT 8');
    $als->execute([$like,$like]); $albums = $als->fetchAll();
}

$allArtists = [];
if ($q === '') {
    $allArtists = $db->query('SELECT id,name,image FROM artists ORDER BY name LIMIT 40')->fetchAll();
}

appOpen('Search');
?>
<style>
/* ── Search page ─────────────────────────────────────────── */
.search-sticky {
  position: sticky; top: 0; z-index: 10;
  padding: calc(env(safe-area-inset-top,16px) + 8px) 16px 12px;
  background: var(--bg);
}
.search-input-wrap {
  display: flex; align-items: center; gap: 10px;
  background: var(--bg-el);
  border: 1px solid var(--border-m);
  border-radius: 14px;
  padding: 0 14px; height: 48px;
  transition: border-color .15s;
}
.search-input-wrap:focus-within { border-color: var(--accent); }
.search-input-wrap svg { color: var(--tf); flex-shrink: 0; }
.search-input-wrap input {
  flex: 1; background: none; border: none; outline: none;
  font-family: var(--font); font-size: 16px; color: var(--t1);
  padding: 0; -webkit-appearance: none;
}
.search-input-wrap input::placeholder { color: var(--tf); }

/* Artist grid (browse state) */
.artist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: 16px 10px;
  padding: 4px 16px 16px;
}
.artist-grid-item {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  text-decoration: none;
}
.artist-grid-item:active { opacity: .7; }
.artist-avatar {
  width: 72px; height: 72px; border-radius: 50%;
  background: var(--bg-card);
  overflow: hidden; display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border);
  flex-shrink: 0;
}
.artist-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.artist-avatar-initial {
  font-size: 22px; font-weight: 800; color: var(--tf);
  letter-spacing: -.5px;
}
.artist-grid-name {
  font-size: 12px; font-weight: 600; text-align: center;
  color: var(--t1); line-height: 1.3;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 88px;
}

/* Results section label */
.results-section {
  padding: 4px 16px 6px;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .08em;
  color: var(--tf);
}

/* Result rows */
.result-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 16px;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  transition: background .1s; text-decoration: none; color: inherit;
}
.result-row:active { background: var(--bg-el); }
.result-art {
  width: 46px; height: 46px; border-radius: 8px;
  background: var(--bg-card); overflow: hidden; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.result-art.round { border-radius: 50%; }
.result-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
.result-art svg { color: var(--tf); }
.result-name { font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.result-sub  { font-size: 13px; color: var(--t2); margin-top: 2px; }
.result-chevron { margin-left: auto; flex-shrink: 0; color: var(--tf); opacity: .5; }

/* Divider between sections */
.results-divider { height: 1px; background: var(--border); margin: 4px 0; }
</style>

<!-- Sticky search bar -->
<div class="search-sticky">
  <form method="GET" style="margin:0" id="search-form">
    <div class="search-input-wrap">
      <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="search" name="q" id="q-input"
             value="<?= h($q) ?>"
             placeholder="Artists, songs, albums…"
             autocomplete="off" autocorrect="off" spellcheck="false">
      <?php if ($q !== ''): ?>
        <a href="?" style="color:var(--tf);display:flex;align-items:center;padding:4px;-webkit-tap-highlight-color:transparent">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($q === ''): ?>
  <!-- Browse: artist grid -->
  <?php if (!empty($allArtists)): ?>
    <div class="section-label" style="padding-top:4px">Artists</div>
    <div class="artist-grid">
      <?php foreach ($allArtists as $a):
        $img = $a['image'] ? $b . '/' . $a['image'] : '';
      ?>
        <a href="<?= $b ?>/pages/artist.php?id=<?= (int)$a['id'] ?>" class="artist-grid-item">
          <div class="artist-avatar">
            <?php if ($img): ?>
              <img src="<?= h($img) ?>" alt="">
            <?php else: ?>
              <span class="artist-avatar-initial"><?= strtoupper(mb_substr(h($a['name']), 0, 1)) ?></span>
            <?php endif; ?>
          </div>
          <span class="artist-grid-name"><?= h($a['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state" style="padding-top:60px">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <div class="empty-state-title">Search Cumu</div>
      <div class="empty-state-text">Find songs, artists and albums.</div>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- Search results -->
  <?php if (empty($songs) && empty($artists) && empty($albums)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <div class="empty-state-title">No results</div>
      <div class="empty-state-text">Nothing found for "<?= h($q) ?>"</div>
    </div>
  <?php endif; ?>

  <?php if (!empty($artists)): ?>
    <div class="results-section">Artists</div>
    <?php foreach ($artists as $a):
      $img = $a['image'] ? $b . '/' . $a['image'] : '';
    ?>
      <a href="<?= $b ?>/pages/artist.php?id=<?= (int)$a['id'] ?>" class="result-row">
        <div class="result-art round">
          <?php if ($img): ?>
            <img src="<?= h($img) ?>" alt="">
          <?php else: ?>
            <span style="font-size:16px;font-weight:800;color:var(--tf)"><?= strtoupper(mb_substr(h($a['name']), 0, 1)) ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div class="result-name"><?= h($a['name']) ?></div>
          <div class="result-sub">Artist</div>
        </div>
        <div class="result-chevron">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($albums)): ?>
    <?php if (!empty($artists)): ?><div class="results-divider"></div><?php endif; ?>
    <div class="results-section">Albums</div>
    <?php foreach ($albums as $al):
      $cover = $al['cover'] ? $b . '/' . $al['cover'] : '';
    ?>
      <a href="<?= $b ?>/pages/album.php?id=<?= (int)$al['id'] ?>" class="result-row">
        <div class="result-art">
          <?php if ($cover): ?>
            <img src="<?= h($cover) ?>" alt="">
          <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div class="result-name"><?= h($al['name']) ?></div>
          <div class="result-sub"><?= h($al['artist_name'] ?? '') ?></div>
        </div>
        <div class="result-chevron">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($songs)): ?>
    <?php if (!empty($artists) || !empty($albums)): ?><div class="results-divider"></div><?php endif; ?>
    <div class="results-section">Songs</div>
    <?php foreach ($songs as $i => $s): songRow($s, $i, $b); endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

<?php appClose('search'); ?>
