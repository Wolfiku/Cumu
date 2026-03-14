<?php
/**
 * CUMU – backend/login.php
 *
 * Accepts POST: username, password
 * Validates credentials and starts the session.
 */

require_once __DIR__ . '/session.php';

// Already logged in? Go to dashboard.
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// ── Collect and sanitize input ────────────────────────────────────────────────
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$error = null;

// ── Basic validation ──────────────────────────────────────────────────────────
if ($username === '' || $password === '') {
    $error = 'Please fill in all fields.';
} else {
    try {
        $db = getDB();

        // Fetch user by username (prepared statement)
        $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ── Regenerate session ID to prevent session fixation ─────────────
            session_regenerate_id(true);

            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: ../dashboard.php');
            exit;
        } else {
            // Generic error to prevent username enumeration
            $error = 'Invalid username or password.';
        }

    } catch (Exception $e) {
        $error = 'A server error occurred. Please try again.';
        error_log('[Cumu] Login error: ' . $e->getMessage());
    }
}

// ── Redirect back to login with error stored in session flash ─────────────────
$_SESSION['flash_error'] = $error;
header('Location: ../index.php');
exit;
