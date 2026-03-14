<?php
/**
 * CUMU – backend/register.php
 *
 * Accepts POST: username, password, password_confirm
 * Creates a new user account with bcrypt-hashed password.
 */

require_once __DIR__ . '/session.php';

// Already logged in? Go to dashboard.
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.php');
    exit;
}

// ── Collect and sanitize input ────────────────────────────────────────────────
$username        = trim($_POST['username']         ?? '');
$password        = $_POST['password']              ?? '';
$passwordConfirm = $_POST['password_confirm']      ?? '';

$error = null;

// ── Validation ────────────────────────────────────────────────────────────────
if ($username === '' || $password === '' || $passwordConfirm === '') {
    $error = 'Please fill in all fields.';

} elseif (strlen($username) < 3 || strlen($username) > 32) {
    $error = 'Username must be between 3 and 32 characters.';

} elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
    $error = 'Username may only contain letters, numbers, underscores, and hyphens.';

} elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters long.';

} elseif ($password !== $passwordConfirm) {
    $error = 'Passwords do not match.';

} else {
    try {
        $db = getDB();

        // Check if username is already taken (case-insensitive via COLLATE NOCASE)
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        } else {
            // Hash password with bcrypt (cost factor 12)
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Insert new user
            $stmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);

            $userId = (int) $db->lastInsertId();

            // Auto-login after registration
            session_regenerate_id(true);
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $username;

            $_SESSION['flash_success'] = 'Welcome to Cumu, ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '!';

            header('Location: ../dashboard.php');
            exit;
        }

    } catch (Exception $e) {
        $error = 'A server error occurred. Please try again.';
        error_log('[Cumu] Register error: ' . $e->getMessage());
    }
}

// ── Redirect back to register page with error ─────────────────────────────────
$_SESSION['flash_error'] = $error;
header('Location: ../register.php');
exit;
