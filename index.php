<?php
/**
 * CUMU – index.php  (Login page)
 */

require_once __DIR__ . '/backend/session.php';

// Already logged in? Forward to dashboard.
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Consume flash messages
$error   = $_SESSION['flash_error']   ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cumu – Sign In</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

  <div class="auth-card">

    <div class="auth-logo">Cu<span>mu</span></div>
    <div class="auth-tagline">Your personal music library</div>

    <div class="auth-title">Sign in</div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="backend/login.php" autocomplete="on">

      <div class="form-group">
        <label for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          placeholder="your username"
          autocomplete="username"
          required
          maxlength="32"
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          autocomplete="current-password"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary">Sign in</button>

    </form>

    <div class="auth-foot">
      No account? <a href="register.php">Create one</a>
    </div>

  </div>

</body>
</html>
