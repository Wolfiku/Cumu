<?php
require_once __DIR__ . '/backend/session.php';
if (!isSetupDone()) { header('Location: ' . BASE_URL . '/setup.php'); exit; }
if (isLoggedIn())   { header('Location: ' . BASE_URL . '/app.php'); exit; }
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$b = BASE_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Cumu</title>
  <link rel="stylesheet" href="<?= $b ?>/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="auth-logo">Cu<span>mu</span></div>
  <div class="auth-tagline">Your personal music library</div>
  <div class="auth-title">Sign in</div>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="<?= $b ?>/backend/login.php">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required autofocus autocomplete="username" placeholder="your username">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
</div>
</body>
</html>
