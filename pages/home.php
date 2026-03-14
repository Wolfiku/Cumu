<?php
/**
 * CUMU – pages/home.php (PHP 7.2+ compatible, verbose error mode)
 */

// Show errors so we can see what breaks
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';

requireLogin();

$db  = getDB();
$uid = currentUserId();
$b   = BASE_URL;

// Time-based greeting
$hour     = (int) date('G');
$greeting = 'Good evening';
if ($hour < 12) { $greeting = 'Good morning'; }
elseif ($hour < 18) { $greeting = 'Good afternoon'; }

// Recent songs (last 20)
$recent = $db->query(
    'SELECT s.id, s.title, a.name AS artist, al.name AS album,
            al.cover, a.id AS artist_id
     FROM songs s
     LEFT JOIN artists a  ON a.id = s.artist_id
     LEFT JOIN albums  al ON al.id = s.album_id
     ORDER BY s.created_at DESC LIMIT 20'
)->fetchAll();

// Recent albums (last 8)
$recentAlbums = $db->query(
    'SELECT al.id, al.name, al.cover, a.name AS artist, a.id AS artist_id
     FROM albums al
     LEFT JOIN artists a ON a.id = al.artist_id
     ORDER BY al.created_at DESC LIMIT 8'
)->fetchAll();

// User playlists (first 4)
$stmt = $db->prepare('SELECT id, name, cover FROM playlists WHERE user_id = ? ORDER BY created_at DESC LIMIT 4');
$stmt->execute(array($uid));
$playlists = $stmt->fetchAll();

$totalSongs = (int) $db->query('SELECT COUNT(*) FROM songs')->fetchColumn();

appOpen(h($greeting));
?>

<div class="page-header" style="padding-top:calc(env(safe-area-inset-top,20px) + 12px);padding-bottom:0">
  <div class="page-header-row">
    <div style="font-size:22px;font-weight:800"><?php echo h($greeting); ?></div>
    <?php if (isAdmin()): ?>
    <a href="<?php echo $b; ?>/pages/admin.php"
       style="width:32px;height:32px;border-radius:50%;background:var(--bg-card);display:flex;align-items:center;justify-content:center;color:var(--t2)">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($playlists) || !empty($recentAlbums)):
    // Build quick-grid items the PHP 7 way (no arrow functions)
    $items = array();
    foreach ($playlists as $p) {
        $items[] = array(
            'url'   => $b . '/pages/playlist.php?id=' . $p['id'],
            'name'  => $p['name'],
            'cover' => $p['cover'] ? $b . '/' . $p['cover'] : '',
        );
    }
    foreach ($recentAlbums as $al) {
        $items[] = array(
            'url'   => $b . '/pages/album.php?id=' . $al['id'],
            'name'  => $al['name'],
            'cover' => $al['cover'] ? $b . '/' . $al['cover'] : '',
        );
    }
    $items = array_slice($items, 0, 6);
?>
<div class="quick-grid" style="padding:16px 16px 4px">
  <?php foreach ($items as $item): ?>
    <a href="<?php echo h($item['url']); ?>" class="q-item">
      <div class="q-item-art">
        <?php if ($item['cover']): ?>
          <img src="<?php echo h($item['cover']); ?>" alt="">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18V5l12-2v13"/>
            <circle cx="6" cy="18" r="3"/>
            <circle cx="18" cy="16" r="3"/>
          </svg>
        <?php endif; ?>
      </div>
      <div class="q-item-name"><?php echo h($item['name']); ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($recentAlbums)): ?>
<div class="section-label">Recently Added</div>
<div class="recent-row" style="padding-bottom:8px">
  <?php foreach ($recentAlbums as $al):
      $cover = $al['cover'] ? $b . '/' . $al['cover'] : '';
  ?>
    <a href="<?php echo $b; ?>/pages/album.php?id=<?php echo (int) $al['id']; ?>" class="r-card">
      <div class="r-card-art">
        <?php if ($cover): ?>
          <img src="<?php echo h($cover); ?>" alt="">
        <?php else: ?>
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        <?php endif; ?>
      </div>
      <div class="r-card-title"><?php echo h($al['name']); ?></div>
      <div class="r-card-sub"><?php echo h($al['artist']); ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($recent)): ?>
<div class="section-label">
  Songs
  <span style="font-size:13px;font-weight:500;color:var(--tf)"><?php echo $totalSongs; ?> total</span>
</div>
<?php
  $idx = 0;
  foreach ($recent as $s) {
      songRow($s, $idx, $b);
      $idx++;
  }
?>
<div style="padding:16px;text-align:center">
  <a href="<?php echo $b; ?>/pages/search.php"
     style="font-size:14px;font-weight:600;color:var(--accent)">Search all songs →</a>
</div>

<?php else: ?>
<div class="empty-state" style="padding-top:48px">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
       fill="none" stroke="currentColor" stroke-width="1.5">
    <path d="M9 18V5l12-2v13"/>
    <circle cx="6" cy="18" r="3"/>
    <circle cx="18" cy="16" r="3"/>
  </svg>
  <div class="empty-state-title">No music yet</div>
  <div class="empty-state-text">
    <?php if (isAdmin()): ?>
      <a href="<?php echo $b; ?>/pages/upload.php" style="color:var(--accent)">Upload music</a>
      or drop files in <code>music/</code> and
      <a href="<?php echo $b; ?>/pages/indexer.php" style="color:var(--accent)">re-index</a>.
    <?php else: ?>
      Ask your admin to upload music.
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php appClose('home'); ?>
