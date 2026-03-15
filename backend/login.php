<?php
require_once __DIR__ . '/../backend/session.php';
if (!isSetupDone()) { header('Location:' . BASE_URL . '/setup.php'); exit; }
if (isLoggedIn())   { header('Location:' . BASE_URL . '/pages/home.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location:' . BASE_URL . '/index.php'); exit; }

$username = trim($_POST['username'] ?? '');
$password = $_POST['password']      ?? '';

if ($username === '' || $password === '') {
    $_SESSION['flash_error'] = 'Fill in all fields.';
    header('Location:' . BASE_URL . '/index.php'); exit;
}
try {
    $s = getDB()->prepare('SELECT id, username, password_hash, role FROM users WHERE username=? LIMIT 1');
    $s->execute([$username]);
    $user = $s->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        header('Location:' . BASE_URL . '/pages/home.php'); exit;
    }
} catch (Exception $e) { error_log($e->getMessage()); }

$_SESSION['flash_error'] = 'Invalid username or password.';
header('Location:' . BASE_URL . '/index.php'); exit;
