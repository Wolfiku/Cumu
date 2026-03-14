<?php
/**
 * CUMU – diag.php
 * Diagnoseskript – nach dem Testen UNBEDINGT löschen!
 * Aufruf: https://cumu-test.wolfiku.eu/diag.php
 */
echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh">';

echo "=== PHP VERSION ===\n";
echo phpversion() . "\n\n";

echo "=== EXTENSIONS ===\n";
$need = array('pdo', 'pdo_sqlite', 'fileinfo', 'mbstring', 'json');
foreach ($need as $ext) {
    echo str_pad($ext, 15) . (extension_loaded($ext) ? "OK\n" : "MISSING\n");
}
echo "\n";

echo "=== DOCUMENT_ROOT ===\n";
echo ($_SERVER['DOCUMENT_ROOT'] ?? '(empty)') . "\n\n";

echo "=== __DIR__ ===\n";
echo __DIR__ . "\n\n";

echo "=== CONFIG LOAD ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "config.php loaded OK\n";
    echo "BASE_URL = '" . BASE_URL . "'\n";
    echo "DB_PATH  = " . DB_PATH . "\n";
    echo "DB dir writable: " . (is_writable(dirname(DB_PATH)) ? "YES" : "NO") . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

echo "=== SESSION LOAD ===\n";
try {
    require_once __DIR__ . '/backend/session.php';
    echo "session.php loaded OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

echo "=== DB CONNECT ===\n";
try {
    $db = getDB();
    echo "DB connected OK\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== SETUP DONE ===\n";
echo (isSetupDone() ? "YES – admin exists\n" : "NO – setup needed\n");
echo "\n";

echo "=== LAYOUT LOAD ===\n";
try {
    require_once __DIR__ . '/backend/layout.php';
    echo "layout.php loaded OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DONE – DELETE THIS FILE AFTER USE ===\n";
echo '</pre>';
