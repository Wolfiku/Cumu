<?php
/**
 * CUMU – backend/session.php
 *
 * Provides:
 *  - Database connection (SQLite via PDO)
 *  - Table initialization on first run
 *  - Session management helpers
 */

// ── Database path ─────────────────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/../database/cumu.db');

// ── Start session if not already active ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,          // until browser close
        'path'     => '/',
        'secure'   => false,      // set to true if using HTTPS
        'httponly' => true,       // block JS access to cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Returns a PDO connection to the SQLite database.
 * Creates all required tables on first run.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Ensure the database directory exists
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0750, true);
        }

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable WAL mode for better concurrency
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');

        initTables($pdo);
    }

    return $pdo;
}

/**
 * Creates the database tables if they do not exist yet.
 *
 * @param PDO $pdo
 */
function initTables(PDO $pdo): void
{
    $pdo->exec("
        -- Users table
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER  PRIMARY KEY AUTOINCREMENT,
            username   TEXT     NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT  NOT NULL,
            created_at DATETIME DEFAULT (datetime('now'))
        );

        -- Songs table
        CREATE TABLE IF NOT EXISTS songs (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            title    TEXT,
            artist   TEXT,
            album    TEXT,
            path     TEXT    NOT NULL UNIQUE,
            cover    TEXT,
            duration INTEGER DEFAULT 0
        );

        -- Playlists table
        CREATE TABLE IF NOT EXISTS playlists (
            id         INTEGER  PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER  NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name       TEXT     NOT NULL,
            created_at DATETIME DEFAULT (datetime('now'))
        );

        -- Playlist–song junction table
        CREATE TABLE IF NOT EXISTS playlist_songs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL REFERENCES playlists(id) ON DELETE CASCADE,
            song_id     INTEGER NOT NULL REFERENCES songs(id)     ON DELETE CASCADE,
            UNIQUE (playlist_id, song_id)
        );
    ");
}

// ─────────────────────────────────────────────────────────────────────────────
// Session helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns true if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Redirect to login page if the user is not authenticated.
 * Call this at the top of every protected page.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Returns the currently logged-in user's ID, or null.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Returns the currently logged-in user's username, or null.
 */
function currentUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

/**
 * Escapes output for safe HTML rendering.
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
