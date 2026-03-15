<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireAdmin();

$db = getDB(); $b = BASE_URL;
$error = $success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $u    = trim($_POST['username'] ?? '');
        $p    = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'listener';
        if (!in_array($role, ['listener','publisher','admin'], true)) $role = 'listener';
        if ($u === '' || $p === '') { $error = 'Fill in all fields.'; }
        elseif (strlen($u) < 3 || strlen($u) > 32) { $error = 'Username 3–32 chars.'; }
        elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $u)) { $error = 'Letters, numbers, _ - only.'; }
        elseif (strlen($p) < 8) { $error = 'Password min 8 chars.'; }
        else {
            $c = $db->prepare('SELECT id FROM users WHERE username=? LIMIT 1'); $c->execute([$u]);
            if ($c->fetch()) { $error = 'Username already taken.'; }
            else {
                $db->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)')->execute([$u, password_hash($p, PASSWORD_BCRYPT, ['cost'=>12]), $role]);
                $success = 'User "' . h($u) . '" created as ' . $role . '.';
            }
        }
    }
    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === currentUserId()) { $error = 'Cannot delete yourself.'; }
        elseif ($uid > 0) { $db->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); $success = 'User deleted.'; }
    }
    if ($action === 'change_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['listener','publisher','admin'], true)) { $error = 'Invalid role.'; }
        elseif ($uid === currentUserId()) { $error = 'Cannot change own role.'; }
        elseif ($uid > 0) { $db->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $uid]); $success = 'Role updated.'; }
    }
}

$users      = $db->query('SELECT id, username, role, created_at FROM users ORDER BY created_at ASC')->fetchAll();
$totalSongs = (int)$db->query('SELECT COUNT(*) FROM songs')->fetchColumn();
$totalArt   = (int)$db->query('SELECT COUNT(*) FROM artists')->fetchColumn();
$totalAlb   = (int)$db->query('SELECT COUNT(*) FROM albums')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cumu Admin</title>
  <link rel="stylesheet" href="<?php echo $b; ?>/style.css">
</head>
<body style="background:var(--bg)">
<div class="admin-wrap">

  <!-- Top bar -->
  <div class="admin-topbar">
    <div>
      <span class="admin-logo">Cu<span>mu</span></span>
      <span class="admin-logo-sub">Admin</span>
    </div>
    <div class="admin-topbar-right">
      <a href="<?php echo $b; ?>/pages/home.php" class="btn btn-secondary"
         style="width:auto;padding:7px 14px;font-size:13px;border-radius:var(--r)">
        ← App
      </a>
      <a href="<?php echo $b; ?>/backend/logout.php" class="btn btn-secondary"
         style="width:auto;padding:7px 14px;font-size:13px;border-radius:var(--r)">
        Sign out
      </a>
    </div>
  </div>

  <div class="admin-body">

    <!-- Stats row -->
    <div class="admin-stat-grid">
      <div class="admin-stat">
        <div class="admin-stat-val accent"><?php echo $totalSongs; ?></div>
        <div class="admin-stat-label">Songs</div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-val"><?php echo $totalArt; ?></div>
        <div class="admin-stat-label">Artists</div>
      </div>
      <div class="admin-stat">
        <div class="admin-stat-val"><?php echo $totalAlb; ?></div>
        <div class="admin-stat-label">Albums</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
      <a href="<?php echo $b; ?>/pages/admin.php"   class="admin-tab active">Users</a>
      <a href="<?php echo $b; ?>/pages/upload.php"  class="admin-tab">Upload</a>
      <a href="<?php echo $b; ?>/pages/indexer.php" class="admin-tab">Re-Index</a>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"   style="margin-bottom:16px"><?php echo h($error);   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px"><?php echo h($success); ?></div><?php endif; ?>

    <!-- Create user card -->
    <div class="admin-card">
      <div class="admin-card-head">
        Create User
        <span style="font-size:12px;font-weight:500;color:var(--tf)"><?php echo count($users); ?> total</span>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="admin-form-row">
          <div class="form-group" style="margin:0">
            <label>Username</label>
            <input type="text" name="username" required minlength="3" maxlength="32"
                   pattern="[a-zA-Z0-9_\-]+" placeholder="username" autocomplete="off">
          </div>
          <div class="form-group" style="margin:0">
            <label>Password</label>
            <input type="password" name="password" required minlength="8" placeholder="min. 8 characters">
          </div>
          <div class="form-group" style="margin:0">
            <label>Role</label>
            <select name="role">
              <option value="listener">Listener</option>
              <option value="publisher">Publisher</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary"
                  style="width:auto;padding:11px 20px;align-self:flex-end;border-radius:var(--r)">
            Create
          </button>
        </div>
      </form>
      <div style="padding:0 20px 14px;font-size:13px;color:var(--tf);line-height:1.9;border-top:1px solid var(--border);padding-top:12px">
        <strong style="color:var(--t2)">Listener</strong> – stream + playlists &nbsp;·&nbsp;
        <strong style="color:var(--t2)">Publisher</strong> – + upload &nbsp;·&nbsp;
        <strong style="color:var(--t2)">Admin</strong> – full access
      </div>
    </div>

    <!-- Users table -->
    <div class="admin-card">
      <div class="admin-card-head">All Users</div>
      <table class="admin-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Change Role</th>
            <th>Since</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
          $self = ($u['id'] === currentUserId());
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="user-avatar-sm"><?php echo strtoupper(substr(h($u['username']),0,1)); ?></div>
                <span style="font-weight:600"><?php echo h($u['username']); ?></span>
                <?php if ($self): ?>
                  <span class="tag tag-accent" style="font-size:10px;padding:2px 7px">You</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <span class="role-badge role-<?php echo $u['role']; ?>"><?php echo h($u['role']); ?></span>
            </td>
            <td>
              <?php if (!$self): ?>
                <form method="POST" style="display:flex;gap:6px;align-items:center">
                  <input type="hidden" name="action"  value="change_role">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  <select name="role"
                    style="padding:6px 10px;border:1px solid var(--border-m);border-radius:var(--r);background:var(--bg-card);color:var(--t1);font-size:13px;outline:none">
                    <option value="listener"  <?php echo $u['role']==='listener'  ?'selected':''; ?>>Listener</option>
                    <option value="publisher" <?php echo $u['role']==='publisher' ?'selected':''; ?>>Publisher</option>
                    <option value="admin"     <?php echo $u['role']==='admin'     ?'selected':''; ?>>Admin</option>
                  </select>
                  <button type="submit" class="btn btn-secondary"
                          style="padding:6px 12px;font-size:12px;border-radius:var(--r)">Save</button>
                </form>
              <?php else: ?>
                <span style="font-size:13px;color:var(--tf)">—</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--tf);font-size:13px">
              <?php echo h(date('d M Y', strtotime($u['created_at']))); ?>
            </td>
            <td style="text-align:right">
              <?php if (!$self): ?>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete <?php echo h(addslashes($u['username'])); ?>?')">
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  <button type="submit" class="btn btn-secondary"
                          style="padding:6px 12px;font-size:12px;border-radius:var(--r)">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</body>
</html>
