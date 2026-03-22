<?php
if (defined('CUMU_SESSION_LOADED')) return;
define('CUMU_SESSION_LOADED', true);
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

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
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER  PRIMARY KEY AUTOINCREMENT,
            username      TEXT     NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT     NOT NULL,
            role          TEXT     NOT NULL DEFAULT 'listener',
            profile_image TEXT,
            created_at    DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
        CREATE TABLE IF NOT EXISTS artists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE,
            image TEXT,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS albums (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            artist_id INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
            name TEXT NOT NULL, cover TEXT, year INTEGER,
            created_at DATETIME DEFAULT (datetime('now')),
            UNIQUE(artist_id, name)
        );
        CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            artist_id INTEGER REFERENCES artists(id) ON DELETE SET NULL,
            album_id  INTEGER REFERENCES albums(id)  ON DELETE SET NULL,
            title TEXT NOT NULL,
            path  TEXT NOT NULL UNIQUE,
            duration  INTEGER DEFAULT 0,
            track_num INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS playlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            description TEXT,
            cover TEXT,
            is_favorite INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS playlist_songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL REFERENCES playlists(id) ON DELETE CASCADE,
            song_id     INTEGER NOT NULL REFERENCES songs(id)     ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0,
            added_at    DATETIME DEFAULT (datetime('now')),
            UNIQUE(playlist_id, song_id)
        );
        CREATE TABLE IF NOT EXISTS recently_played (
            id       INTEGER  PRIMARY KEY AUTOINCREMENT,
            user_id  INTEGER  NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
            song_id  INTEGER  NOT NULL REFERENCES songs(id)  ON DELETE CASCADE,
            played_at DATETIME DEFAULT (datetime('now'))
        );
        -- Audiobooks / Hörspiele
        CREATE TABLE IF NOT EXISTS series (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            cover      TEXT,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS audiobooks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            series_id  INTEGER REFERENCES series(id) ON DELETE SET NULL,
            title      TEXT    NOT NULL,
            narrator   TEXT,
            path       TEXT    NOT NULL UNIQUE,
            cover      TEXT,
            duration   INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_songs_artist   ON songs(artist_id);
        CREATE INDEX IF NOT EXISTS idx_songs_album    ON songs(album_id);
        CREATE INDEX IF NOT EXISTS idx_albums_artist  ON albums(artist_id);
        CREATE INDEX IF NOT EXISTS idx_rp_user        ON recently_played(user_id, played_at);
        CREATE INDEX IF NOT EXISTS idx_ab_series      ON audiobooks(series_id);
        -- Mixtapes (album-like, created by publishers/admins)
        CREATE TABLE IF NOT EXISTS mixtapes (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            creator_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
            name        TEXT    NOT NULL,
            created_at  DATETIME DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS mixtape_songs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            mixtape_id  INTEGER NOT NULL REFERENCES mixtapes(id)  ON DELETE CASCADE,
            song_id     INTEGER NOT NULL REFERENCES songs(id)     ON DELETE CASCADE,
            position    INTEGER NOT NULL DEFAULT 0,
            added_at    DATETIME DEFAULT (datetime('now')),
            UNIQUE(mixtape_id, song_id)
        );
        CREATE INDEX IF NOT EXISTS idx_mx_creator ON mixtapes(creator_id);
        CREATE INDEX IF NOT EXISTS idx_mxs_mt     ON mixtape_songs(mixtape_id);
    ");
    /* Add missing columns to existing DBs */
    try { $db->exec("ALTER TABLE songs ADD COLUMN type TEXT DEFAULT 'song'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE albums ADD COLUMN genre TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE albums ADD COLUMN featured INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE artists ADD COLUMN banner TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE playlists ADD COLUMN is_favorite INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE playlists ADD COLUMN cover TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE playlists ADD COLUMN description TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN profile_image TEXT"); } catch (Exception $e) {}
    /* Migrate existing 'user' role to 'listener' */
    $db->exec("UPDATE users SET role='listener' WHERE role='user'");
    /* Migrate existing 'admin' stays admin, add publisher if missing */
}

/* ── Role helpers ──────────────────────────────────────────────────────────
 * Roles (lowest → highest):
 *   listener  – can stream, manage own playlists
 *   publisher – listener + can upload music & run indexer
 *   admin     – publisher + user management + metadata edit
 */
function isSetupDone(): bool {
    try { return (int)getDB()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0; }
    catch (Exception $e) { return false; }
}
function _iframeRedirect(string $url): void {
    echo '<!DOCTYPE html><html><head><script>';
    echo 'if(window.parent!==window){window.parent.location.href=' . json_encode($url) . ';}';
    echo 'else{window.location.href=' . json_encode($url) . ';}';
    echo '</script></head><body></body></html>';
    exit;
}
function requireSetup(): void {
    if (!isSetupDone()) {
        $target = BASE_URL . '/setup.php';
        echo '<!DOCTYPE html><html><head><script>';
        echo 'if(window.parent!==window){window.parent.location.href=' . json_encode($target) . ';}';
        echo 'else{window.location.href=' . json_encode($target) . ';}';
        echo '</script></head><body></body></html>';
        exit;
    }
}
function isLoggedIn(): bool   { return !empty($_SESSION['user_id']); }
function currentRole(): string { return $_SESSION['role'] ?? ''; }
function isAdmin(): bool      { return currentRole() === 'admin'; }
function isPublisher(): bool  { return currentRole() === 'publisher' || currentRole() === 'admin'; }
function isListener(): bool   { return isLoggedIn(); } // all logged-in users can listen
function currentUserId(): ?int   { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function currentUsername(): ?string { return $_SESSION['username'] ?? null; }
function requireLogin(): void {
    requireSetup();
    if (!isLoggedIn()) { _iframeRedirect(BASE_URL . '/index.php'); }
}
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) { header('Location:' . BASE_URL . '/pages/home.php'); exit; }
}
function requirePublisher(): void {
    requireLogin();
    if (!isPublisher()) { header('Location:' . BASE_URL . '/pages/home.php'); exit; }
}

/* ── Settings ──────────────────────────────────────────────────────────── */
function getSetting(string $k, string $d = ''): string {
    try {
        $s = getDB()->prepare('SELECT value FROM settings WHERE key=? LIMIT 1');
        $s->execute([$k]);
        $r = $s->fetchColumn();
        return $r !== false ? $r : $d;
    } catch (Exception $e) { return $d; }
}
function setSetting(string $k, string $v): void {
    getDB()->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute([$k, $v]);
}

/* ── Recently played ───────────────────────────────────────────────────── */
function recordPlay(int $uid, int $sid): void {
    try {
        getDB()->prepare('INSERT INTO recently_played(user_id,song_id) VALUES(?,?)')->execute([$uid, $sid]);
        getDB()->prepare(
            'DELETE FROM recently_played WHERE user_id=? AND id NOT IN
             (SELECT id FROM recently_played WHERE user_id=? ORDER BY played_at DESC LIMIT 100)'
        )->execute([$uid, $uid]);
    } catch (Exception $e) {}
}

/* ── Utilities ─────────────────────────────────────────────────────────── */
function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fmtDuration(int $s): string {
    if ($s <= 0) return '--';
    return intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT);
}
function jsonOk($d): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $d]);
    exit;
}
function jsonErr(string $m, int $c = 400): void {
    http_response_code($c);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $m]);
    exit;
}

/* ── Music indexer ─────────────────────────────────────────────────────── */
function runIndexer(): array {
    require_once __DIR__ . '/id3_reader.php';
    if (!is_dir(MUSIC_DIR)) return ['added'=>0,'skipped'=>0,'errors'=>0,'log'=>['Music dir not found']];
    if (!is_dir(COVERS_DIR)) mkdir(COVERS_DIR, 0750, true);
    $db = getDB(); $added = $skipped = $errors = 0; $log = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MUSIC_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        if (!in_array(strtolower($file->getExtension()), AUDIO_EXT, true)) continue;
        $real = $file->getRealPath();
        $rel  = ltrim(str_replace('\\', '/', str_replace(realpath(MUSIC_DIR), '', $real)), '/');
        $ex   = $db->prepare('SELECT id FROM songs WHERE path=? LIMIT 1'); $ex->execute([$rel]);
        if ($ex->fetch()) { $skipped++; continue; }
        $tags   = readID3Tags($real);
        $parts  = explode('/', $rel);
        $title  = $tags['title']  ?: (preg_replace('/^\d+[\s.\-_]+/', '', pathinfo($real, PATHINFO_FILENAME)) ?: pathinfo($real, PATHINFO_FILENAME));
        $artist = $tags['artist'] ?: (count($parts) >= 3 ? $parts[0] : 'Unknown Artist');
        $album  = $tags['album']  ?: (count($parts) >= 3 ? $parts[1] : (count($parts) === 2 ? $parts[0] : 'Unknown Album'));
        $coverRel = null;
        if (!empty($tags['cover_data']) && strlen($tags['cover_data']) > 64) {
            $ch = md5($rel) . '.' . $tags['cover_ext'];
            $cp = COVERS_DIR . '/' . $ch;
            if (!file_exists($cp)) file_put_contents($cp, $tags['cover_data']);
            $coverRel = 'covers/' . $ch;
        } else {
            foreach (['cover.jpg','cover.jpeg','cover.png','folder.jpg'] as $cn) {
                $src = $file->getPath() . '/' . $cn;
                if (file_exists($src)) {
                    $dst = COVERS_DIR . '/' . md5($src) . '.' . pathinfo($src, PATHINFO_EXTENSION);
                    if (!file_exists($dst)) copy($src, $dst);
                    $coverRel = 'covers/' . basename($dst);
                    break;
                }
            }
        }
        try {
            $db->beginTransaction();
            $db->prepare('INSERT OR IGNORE INTO artists(name) VALUES(?)')->execute([$artist]);
            $ar = $db->prepare('SELECT id FROM artists WHERE name=? LIMIT 1'); $ar->execute([$artist]); $aid = (int)$ar->fetchColumn();
            $db->prepare('INSERT OR IGNORE INTO albums(artist_id,name,year) VALUES(?,?,?)')->execute([$aid, $album, (int)($tags['year'] ?? 0)]);
            $alb = $db->prepare('SELECT id FROM albums WHERE artist_id=? AND name=? LIMIT 1'); $alb->execute([$aid, $album]); $alid = (int)$alb->fetchColumn();
            if ($coverRel) $db->prepare('UPDATE albums SET cover=? WHERE id=? AND cover IS NULL')->execute([$coverRel, $alid]);
            $db->prepare('INSERT INTO songs(artist_id,album_id,title,path,track_num) VALUES(?,?,?,?,?)')->execute([$aid, $alid, $title, $rel, (int)($tags['track'] ?? 0)]);
            $db->commit();
            $log[] = "[OK]  $artist – $title"; $added++;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $log[] = "[ERR] $rel: " . $e->getMessage(); $errors++;
        }
    }
    return compact('added', 'skipped', 'errors', 'log');
}

/* ── SVG Icons ─────────────────────────────────────────────────────────── */
function icon(string $n): string {
    static $i = null;
    if (!$i) $i = [
        'home'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L2 12h3v8h6v-5h2v5h6v-8h3L12 3z"/></svg>',
        'home-o'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'search'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'library'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="3" height="18" rx="1"/><rect x="10" y="3" width="3" height="18" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>',
        'library-o'=> '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h4v18H3zM10 3h4v18h-4zM17 3h4v18h-4z"/></svg>',
        'play'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
        'pause'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>',
        'prev'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>',
        'next'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>',
        'note'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
        'plus'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'more'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>',
        'more-h'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>',
        'chevron-d'=> '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
        'chevron-r'=> '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
        'heart'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'shuffle'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>',
        'repeat'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
        'mic'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
        'disc'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>',
        'trash'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4h6v2"/></svg>',
        'edit'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'check'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'upload'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
        'users'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'refresh'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        'logout'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'shield'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'link'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'list'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
    ];
    return isset($i[$n]) ? $i[$n] : '';
}
