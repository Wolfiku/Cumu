<?php
/**
 * CUMU – scanner/scan_music.php
 *
 * Scans the /music directory for MP3 files and adds them to the SQLite database.
 *
 * Run from the command line:
 *   php scanner/scan_music.php
 *
 * Or call via a protected web route (must be logged in as admin if desired).
 *
 * Expected folder structure:
 *   music/
 *   └── Artist Name/
 *       └── Album Name/
 *           ├── 01 - Track Title.mp3
 *           └── cover.jpg
 *
 * Dependencies (optional but recommended for ID3 metadata):
 *   composer require james-heinrich/getid3
 *   If getID3 is not present, metadata is inferred from the folder/filename.
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('CUMU_ROOT', realpath(__DIR__ . '/..'));
require_once CUMU_ROOT . '/backend/session.php';

// ── Configuration ─────────────────────────────────────────────────────────────
$MUSIC_DIR  = CUMU_ROOT . '/music';
$COVERS_DIR = CUMU_ROOT . '/covers';

// Supported audio extensions
$AUDIO_EXT  = ['mp3', 'flac', 'ogg', 'wav', 'm4a', 'aac', 'opus'];

// Cover image filenames to look for
$COVER_NAMES = ['cover.jpg', 'cover.jpeg', 'cover.png', 'folder.jpg', 'folder.png', 'artwork.jpg'];

// ─────────────────────────────────────────────────────────────────────────────
// Output helpers
// ─────────────────────────────────────────────────────────────────────────────

$isCli = php_sapi_name() === 'cli';

function output(string $line, string $type = 'info'): void
{
    global $isCli;

    if ($isCli) {
        $prefix = match($type) {
            'ok'   => '[OK]   ',
            'skip' => '[SKIP] ',
            'err'  => '[ERR]  ',
            default => '[INFO] ',
        };
        echo $prefix . $line . PHP_EOL;
    } else {
        $class = match($type) {
            'ok'   => 'color:green',
            'skip' => 'color:orange',
            'err'  => 'color:red',
            default => '',
        };
        echo '<p style="' . $class . '">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
        ob_flush();
        flush();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// getID3 metadata reader (optional)
// ─────────────────────────────────────────────────────────────────────────────

function tryGetID3Metadata(string $filePath): array
{
    $getid3Autoload = CUMU_ROOT . '/vendor/autoload.php';

    if (file_exists($getid3Autoload)) {
        require_once $getid3Autoload;

        try {
            $getID3   = new getID3();
            $fileInfo = $getID3->analyze($filePath);
            getid3_lib::CopyTagsToComments($fileInfo);

            $tags     = $fileInfo['tags_html']['id3v2']
                     ?? $fileInfo['tags_html']['id3v1']
                     ?? $fileInfo['tags']['id3v2']
                     ?? $fileInfo['tags']['id3v1']
                     ?? [];

            $comments = $fileInfo['comments'] ?? [];

            return [
                'title'    => trim(strip_tags($tags['title'][0]   ?? $comments['title'][0]   ?? '')),
                'artist'   => trim(strip_tags($tags['artist'][0]  ?? $comments['artist'][0]  ?? '')),
                'album'    => trim(strip_tags($tags['album'][0]   ?? $comments['album'][0]   ?? '')),
                'duration' => (int) ($fileInfo['playtime_seconds'] ?? 0),
            ];
        } catch (Exception $e) {
            // Fall through to filename-based parsing
        }
    }

    return [];
}

// ─────────────────────────────────────────────────────────────────────────────
// Fallback: infer metadata from path
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Derives title/artist/album from the file path.
 *
 * Expected structure: /music/<Artist>/<Album>/<Track>.ext
 */
function inferMetadataFromPath(string $filePath, string $musicDir): array
{
    $relative = ltrim(str_replace($musicDir, '', $filePath), '/\\');
    $parts    = explode(DIRECTORY_SEPARATOR, $relative);

    $filename = pathinfo($filePath, PATHINFO_FILENAME);
    // Strip leading track numbers: "01 - Title" or "01. Title" → "Title"
    $title    = preg_replace('/^\d+[\s\.\-]+/', '', $filename);

    return [
        'title'    => $title                        ?: $filename,
        'artist'   => count($parts) >= 3 ? $parts[0] : 'Unknown Artist',
        'album'    => count($parts) >= 3 ? $parts[1] : 'Unknown Album',
        'duration' => 0,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Cover image detection
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Looks for a cover image in the same directory as the audio file,
 * copies it to /covers/<hash>.jpg, and returns the relative path.
 */
function findAndCacheCover(string $dir, string $coversDir, array $coverNames): ?string
{
    foreach ($coverNames as $name) {
        $src = $dir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($src)) {
            // Use a hash of the source path as filename to avoid collisions
            $hash    = md5($src);
            $ext     = pathinfo($src, PATHINFO_EXTENSION);
            $dstName = $hash . '.' . $ext;
            $dst     = $coversDir . DIRECTORY_SEPARATOR . $dstName;

            if (!file_exists($dst)) {
                copy($src, $dst);
            }
            return 'covers/' . $dstName;
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main scan routine
// ─────────────────────────────────────────────────────────────────────────────

function scanMusicDirectory(
    string $musicDir,
    string $coversDir,
    array  $audioExt,
    array  $coverNames
): void {
    if (!is_dir($musicDir)) {
        output("Music directory not found: $musicDir", 'err');
        return;
    }

    if (!is_dir($coversDir)) {
        mkdir($coversDir, 0750, true);
    }

    $db      = getDB();
    $added   = 0;
    $skipped = 0;
    $errors  = 0;

    // Recursive iterator over all files in /music
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($musicDir, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $audioExt, true)) continue;

        $realPath  = $file->getRealPath();
        // Store relative path (from project root) for portability
        $relPath   = 'music' . str_replace($musicDir, '', $realPath);
        $relPath   = str_replace('\\', '/', $relPath); // normalize on Windows

        // Check if already in database
        $check = $db->prepare('SELECT id FROM songs WHERE path = ? LIMIT 1');
        $check->execute([$relPath]);

        if ($check->fetch()) {
            output("Skipped (already indexed): $relPath", 'skip');
            $skipped++;
            continue;
        }

        // Read metadata
        $meta = tryGetID3Metadata($realPath);
        if (empty($meta['title'])) {
            $meta = array_merge(
                inferMetadataFromPath($realPath, $musicDir),
                $meta  // keep duration from ID3 if available
            );
        }

        // Ensure fallbacks
        $title    = $meta['title']    ?: pathinfo($realPath, PATHINFO_FILENAME);
        $artist   = $meta['artist']   ?: 'Unknown Artist';
        $album    = $meta['album']    ?: 'Unknown Album';
        $duration = (int) ($meta['duration'] ?? 0);

        // Find cover
        $cover = findAndCacheCover(
            $file->getPath(),
            $coversDir,
            $coverNames
        );

        // Insert into DB
        try {
            $stmt = $db->prepare(
                'INSERT INTO songs (title, artist, album, path, cover, duration)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$title, $artist, $album, $relPath, $cover, $duration]);

            output("Added: $artist – $title  [$album]", 'ok');
            $added++;

        } catch (Exception $e) {
            output("Error inserting '$relPath': " . $e->getMessage(), 'err');
            $errors++;
        }
    }

    output("", 'info');
    output("Scan complete — Added: $added | Skipped: $skipped | Errors: $errors", 'info');
}

// ─────────────────────────────────────────────────────────────────────────────
// Run
// ─────────────────────────────────────────────────────────────────────────────

if (!$isCli) {
    // Web access: require login (optional, enable for production)
    // require_once CUMU_ROOT . '/backend/session.php';
    // if (!isLoggedIn()) { http_response_code(403); exit('Forbidden'); }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Cumu Scanner</title></head><body>';
    echo '<h2 style="font-family:sans-serif">Cumu Music Scanner</h2>';
    echo '<pre style="font-family:monospace; line-height:1.8">';
}

output('Starting scan of: ' . $MUSIC_DIR);
output(str_repeat('-', 60));

scanMusicDirectory($MUSIC_DIR, $COVERS_DIR, $AUDIO_EXT, $COVER_NAMES);

if (!$isCli) {
    echo '</pre></body></html>';
}
