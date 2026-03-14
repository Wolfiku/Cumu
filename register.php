<?php
/**
 * CUMU – register.php  (Registration page)
 */

require_once __DIR__ . '/backend/session.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);

// Preserve form values on error
$oldUsername = h($_SESSION['flash_username'] ?? '');
unset($_SESSION['flash_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cumu – Create Account</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

  <div class="auth-card">

    <div class="auth-logo">Cu<span>mu</span></div>
    <div class="auth-tagline">Your personal music library</div>

    <div class="auth-title">Create account</div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="backend/register.php" autocomplete="off">

      <div class="form-group">
        <label for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          placeholder="3–32 characters, letters and numbers"
          autocomplete="username"
          required
          minlength="3"
          maxlength="32"
          pattern="[a-zA-Z0-9_\-]+"
          title="Letters, numbers, underscores, and hyphens only"
          value="<?= $oldUsername ?>"
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="At least 8 characters"
          autocomplete="new-password"
          required
          minlength="8"
        >
      </div>

      <div class="form-group">
        <label for="password_confirm">Confirm password</label>
        <input
          type="password"
          id="password_confirm"
          name="password_confirm"
          placeholder="Repeat your password"
          autocomplete="new-password"
          required
          minlength="8"
        >
      </div>

      <button type="submit" class="btn btn-primary">Create account</button>

    </form>

    <div class="auth-foot">
      Already have an account? <a href="index.php">Sign in</a>
    </div>

  </div>

</body>
</html>
