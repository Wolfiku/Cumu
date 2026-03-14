<?php
if (defined('CUMU_SESSION_LOADED')) return;
define('CUMU_SESSION_LOADED', true);

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

// ═══════════════════════════════════════════════════════════
// DATABASE
// ═══════════════════════════════════════════════════════════

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    _initSchema($pdo);
    return $pdo;
}

function _initSchema(PDO $db): void {
    $db->exec("
        -- Auth
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER  PRIMARY KEY AUTOINCREMENT,
            username      TEXT     NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT     NOT NULL,
            role          TEXT     NOT NULL DEFAULT 'user',
            created_at    DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );

        -- Music library
        CREATE TABLE IF NOT EXISTS artists (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            image      TEXT,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS albums (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            artist_id  INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
            name       TEXT    NOT NULL,
            cover      TEXT,
            year       INTEGER,
            created_at DATETIME DEFAULT (datetime('now')),
            UNIQUE (artist_id, name)
        );
        CREATE TABLE IF NOT EXISTS songs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            artist_id  INTEGER REFERENCES artists(id) ON DELETE SET NULL,
            album_id   INTEGER REFERENCES albums(id)  ON DELETE SET NULL,
            title      TEXT    NOT NULL,
            path       TEXT    NOT NULL UNIQUE,
            duration   INTEGER DEFAULT 0,
            track_num  INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT (datetime('now'))
        );

        -- Playlists
        CREATE TABLE IF NOT EXISTS playlists (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name       TEXT    NOT NULL,
            cover      TEXT,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS playlist_songs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL REFERENCES playlists(id) ON DELETE CASCADE,
            song_id     INTEGER NOT NULL REFERENCES songs(id)     ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0,
            added_at    DATETIME DEFAULT (datetime('now')),
            UNIQUE (playlist_id, song_id)
        );

        -- Indexes for fast lookups
        CREATE INDEX IF NOT EXISTS idx_songs_artist  ON songs(artist_id);
        CREATE INDEX IF NOT EXISTS idx_songs_album   ON songs(album_id);
        CREATE INDEX IF NOT EXISTS idx_albums_artist ON albums(artist_id);
        CREATE INDEX IF NOT EXISTS idx_pl_songs_pl   ON playlist_songs(playlist_id);
        CREATE INDEX IF NOT EXISTS idx_pl_songs_song ON playlist_songs(song_id);
    ");
}

// ═══════════════════════════════════════════════════════════
// SETUP / SESSION HELPERS
// ═══════════════════════════════════════════════════════════

function isSetupDone(): bool {
    try { return (int)getDB()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0; }
    catch (Exception) { return false; }
}
function requireSetup(): void {
    if (!isSetupDone()) { header('Location: '.BASE_URL.'/setup.php'); exit; }
}
function isLoggedIn(): bool   { return !empty($_SESSION['user_id']); }
function isAdmin(): bool      { return ($_SESSION['role'] ?? '') === 'admin'; }
function currentUserId(): ?int  { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function currentUsername(): ?string { return $_SESSION['username'] ?? null; }

function requireLogin(): void {
    requireSetup();
    if (!isLoggedIn()) { header('Location: '.BASE_URL.'/index.php'); exit; }
}
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) { header('Location: '.BASE_URL.'/pages/dashboard.php'); exit; }
}

// ═══════════════════════════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════════════════════════

function getSetting(string $k, string $d = ''): string {
    try { $s = getDB()->prepare('SELECT value FROM settings WHERE key=? LIMIT 1'); $s->execute([$k]); $r = $s->fetchColumn(); return $r !== false ? $r : $d; }
    catch (Exception) { return $d; }
}
function setSetting(string $k, string $v): void {
    getDB()->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute([$k,$v]);
}

// ═══════════════════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════════════════

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function fmtDuration(int $s): string { if($s<=0) return '--'; return intdiv($s,60).':'.str_pad((string)($s%60),2,'0',STR_PAD_LEFT); }
function jsonOk(mixed $data): void  { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'data'=>$data]); exit; }
function jsonErr(string $msg, int $code = 400): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// ═══════════════════════════════════════════════════════════
// MUSIC INDEXER
// ═══════════════════════════════════════════════════════════

/**
 * Scans MUSIC_DIR, upserts artists/albums/songs into DB.
 * Returns ['added'=>N, 'skipped'=>N, 'errors'=>N, 'log'=>[...]]
 */
function runIndexer(): array {
    if (!is_dir(MUSIC_DIR)) return ['added'=>0,'skipped'=>0,'errors'=>0,'log'=>['Music dir not found: '.MUSIC_DIR]];
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);

    $db     = getDB();
    $added = $skipped = $errors = 0;
    $log    = [];
    $ext    = AUDIO_EXT;

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(MUSIC_DIR, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        if (!in_array(strtolower($file->getExtension()), $ext, true)) continue;

        $real = $file->getRealPath();
        $rel  = ltrim(str_replace('\\','/',str_replace(realpath(MUSIC_DIR),'',$real)),'/');

        // Already indexed?
        $exists = $db->prepare('SELECT id FROM songs WHERE path=? LIMIT 1');
        $exists->execute([$rel]);
        if ($exists->fetch()) { $skipped++; continue; }

        // Parse path: Artist/Album/track.mp3  OR  Artist/track.mp3  OR  track.mp3
        $parts     = explode('/', $rel);
        $filename  = pathinfo($real, PATHINFO_FILENAME);
        $artistName = count($parts) >= 3 ? $parts[0] : 'Unknown Artist';
        $albumName  = count($parts) >= 3 ? $parts[1] : (count($parts) === 2 ? $parts[0] : 'Unknown Album');
        $title      = preg_replace('/^\d+[\s.\-_]+/', '', $filename) ?: $filename;

        // Track number from filename
        preg_match('/^(\d+)/', $filename, $tm);
        $trackNum = isset($tm[1]) ? (int)$tm[1] : 0;

        // Cover: look in same dir as the file
        $coverPath = null;
        foreach (['cover.jpg','cover.jpeg','cover.png','folder.jpg','artwork.jpg'] as $cn) {
            $src = $file->getPath() . '/' . $cn;
            if (file_exists($src)) {
                $dst = COVERS_DIR . '/' . md5($src) . '.' . pathinfo($src, PATHINFO_EXTENSION);
                if (!file_exists($dst)) copy($src, $dst);
                $coverPath = 'covers/' . basename($dst);
                break;
            }
        }

        try {
            $db->beginTransaction();

            // Upsert artist
            $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artistName]);
            $artistId = (int)$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1')
                              ->execute([$artistName]) ? $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1')->execute([$artistName]) && ($r=$db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1')) && $r->execute([$artistName]) ? (int)$r->fetchColumn() : 0 : 0;
            // Simpler artist fetch:
            $ar = $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1'); $ar->execute([$artistName]); $artistId = (int)$ar->fetchColumn();

            // Upsert album
            $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name,cover) VALUES(?,?,?)')->execute([$artistId,$albumName,$coverPath]);
            $alb = $db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1'); $alb->execute([$artistId,$albumName]); $albumId = (int)$alb->fetchColumn();
            // Update cover on album if we found one and it's not set yet
            if ($coverPath) $db->prepare('UPDATE albums SET cover=? WHERE id=? AND cover IS NULL')->execute([$coverPath,$albumId]);

            // Insert song
            $db->prepare('INSERT INTO songs(artist_id,album_id,title,path,track_num) VALUES(?,?,?,?,?)')
               ->execute([$artistId,$albumId,$title,$rel,$trackNum]);

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

// ═══════════════════════════════════════════════════════════
// SVG ICONS
// ═══════════════════════════════════════════════════════════

function icon(string $n): string {
    static $i = null;
    if (!$i) $i = [
        'home'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'music'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
        'list'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'users'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'upload'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
        'logout'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'search'     => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'play'       => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
        'note'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
        'skip-prev'  => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>',
        'skip-next'  => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>',
        'volume'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
        'plus'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'trash'      => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>',
        'check'      => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'link'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'shield'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'mic'        => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
        'disc'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>',
        'refresh'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
    ];
    return $i[$n] ?? '';
}
