<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
require_once __DIR__ . '/../backend/id3_reader.php';
requireLogin();
$b = BASE_URL;

$result = null;

// Override the indexer in session.php to use our ID3 reader
function runIndexerWithID3(): array {
    if (!is_dir(MUSIC_DIR)) return ['added'=>0,'skipped'=>0,'errors'=>0,'log'=>['Music dir not found: '.MUSIC_DIR]];
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);

    $db = getDB();
    $added = $skipped = $errors = 0;
    $log = [];
    $ext = AUDIO_EXT;
    $coverNames = ['cover.jpg','cover.jpeg','cover.png','folder.jpg','artwork.jpg'];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(MUSIC_DIR, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        if (!in_array(strtolower($file->getExtension()), $ext, true)) continue;

        $real = $file->getRealPath();
        $rel  = ltrim(str_replace('\\','/',str_replace(realpath(MUSIC_DIR),'',$real)),'/');

        // Already indexed?
        $chk = $db->prepare('SELECT id FROM songs WHERE path=? LIMIT 1');
        $chk->execute([$rel]);
        if ($chk->fetch()) { $skipped++; continue; }

        // ── Read ID3 tags ─────────────────────────────────────────────────────
        $meta = readID3($real);

        // Fallback: parse from folder structure  Artist/Album/track.mp3
        $parts = explode('/', $rel);
        $filename  = pathinfo($real, PATHINFO_FILENAME);
        $artistName = ($meta['artist'] !== '') ? $meta['artist'] : (count($parts) >= 3 ? $parts[0] : 'Unknown Artist');
        $albumName  = ($meta['album']  !== '') ? $meta['album']  : (count($parts) >= 3 ? $parts[1] : (count($parts) === 2 ? $parts[0] : 'Unknown Album'));
        $title      = ($meta['title']  !== '') ? $meta['title']  : (preg_replace('/^\d+[\s.\-_]+/', '', $filename) ?: $filename);
        $track      = $meta['track']   ?: 0;
        $duration   = $meta['duration'];
        $year       = $meta['year']    ?: null;

        // ── Cover: embedded ID3 art first, then folder cover.jpg ─────────────
        $coverPath = saveCoverArt($meta);

        if (!$coverPath) {
            foreach ($coverNames as $cn) {
                $src = $file->getPath() . '/' . $cn;
                if (file_exists($src)) {
                    $dst = COVERS_DIR . '/' . md5($src) . '.' . pathinfo($src, PATHINFO_EXTENSION);
                    if (!file_exists($dst)) copy($src, $dst);
                    $coverPath = 'covers/' . basename($dst);
                    break;
                }
            }
        }

        try {
            $db->beginTransaction();

            // Artist
            $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artistName]);
            $ar = $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1'); $ar->execute([$artistName]);
            $artistId = (int)$ar->fetchColumn();

            // Album
            $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name,year) VALUES(?,?,?)')->execute([$artistId,$albumName,$year]);
            $alb = $db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1'); $alb->execute([$artistId,$albumName]);
            $albumId = (int)$alb->fetchColumn();

            if ($coverPath) {
                $db->prepare('UPDATE albums  SET cover=? WHERE id=? AND cover IS NULL')->execute([$coverPath,$albumId]);
                $db->prepare('UPDATE artists SET image=? WHERE id=? AND image IS NULL')->execute([$coverPath,$artistId]);
            }

            // Song
            $db->prepare('INSERT INTO songs(artist_id,album_id,title,path,duration,track_num) VALUES(?,?,?,?,?,?)')
               ->execute([$artistId,$albumId,$title,$rel,$duration,$track]);

            $db->commit();
            $log[] = "[OK]  $artistName – $title";
            $added++;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $log[] = "[ERR] $rel: " . $e->getMessage();
            $errors++;
        }
    }

    return compact('added','skipped','errors','log');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $result = runIndexerWithID3();
}

adminHead('Re-Index Library', 'indexer');
?>

<div style="max-width:640px">
  <div class="admin-card" style="margin-bottom:20px">
    <div class="admin-card-head">How it works</div>
    <div style="padding:16px 20px;font-size:14px;color:var(--t2);line-height:1.8">
      Scans the <code>music/</code> folder for new audio files and adds them to the database.<br>
      Reads ID3 tags (title, artist, album, track number, embedded artwork) automatically.<br>
      Already-indexed files are skipped.<br>
      <br>
      Folder structure: <code>music/Artist/Album/track.mp3</code>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="run" value="1">
    <button type="submit" class="btn btn-primary" style="width:auto;padding:12px 28px">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Run Indexer
    </button>
  </form>

  <?php if ($result !== null): ?>
    <div style="margin-top:24px">
      <!-- Stats -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--rl);padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:var(--accent);letter-spacing:-1px"><?= $result['added'] ?></div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tf);margin-top:4px">Added</div>
        </div>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--rl);padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:var(--t2);letter-spacing:-1px"><?= $result['skipped'] ?></div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tf);margin-top:4px">Skipped</div>
        </div>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--rl);padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:<?= $result['errors'] > 0 ? '#ff8080' : 'var(--tf)' ?>;letter-spacing:-1px"><?= $result['errors'] ?></div>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--tf);margin-top:4px">Errors</div>
        </div>
      </div>

      <!-- Log -->
      <?php if (!empty($result['log'])): ?>
        <div style="background:#0a0a0a;border:1px solid var(--border);border-radius:var(--rl);padding:16px;max-height:320px;overflow-y:auto">
          <?php foreach ($result['log'] as $line):
            $c = str_starts_with($line,'[OK]') ? '#4ade80' : (str_starts_with($line,'[ERR]') ? '#f87171' : 'var(--tf)');
          ?>
            <div style="font-family:var(--mono);font-size:12px;color:<?= $c ?>;padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($line) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($result['added'] > 0): ?>
        <a href="<?= $b ?>/pages/home.php" class="btn btn-primary" style="margin-top:16px">
          Go to App
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<?php adminFoot(); ?>
