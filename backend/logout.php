<?php
/**
 * CUMU – backend/logout.php
 *
 * Destroys the session and redirects to the login page.
 */

require_once __DIR__ . '/session.php';

// Clear session data
$_SESSION = [];

// Invalidate the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

header('Location: ../index.php');
exit;
