<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireAdmin();

$db = getDB();
$b  = BASE_URL;
$error = $success = null;

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $u    = trim($_POST['username'] ?? '');
    $p    = $_POST['password'] ?? '';
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';

    if ($u === '' || $p === '') {
        $error = 'Fill in all fields.';
    } elseif (strlen($u) < 3 || strlen($u) > 32) {
        $error = 'Username must be 3–32 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $u)) {
        $error = 'Letters, numbers, _ and - only.';
    } elseif (strlen($p) < 8) {
        $error = 'Password min 8 characters.';
    } else {
        $c = $db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $c->execute([$u]);
        if ($c->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)')->execute([$u, $hash, $role]);
            $success = 'User "' . h($u) . '" created.';
        }
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid === currentUserId()) {
        $error = 'Cannot delete your own account.';
    } elseif ($uid > 0) {
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
        $success = 'User deleted.';
    }
}

$users = $db->query('SELECT id, username, role, created_at FROM users ORDER BY created_at ASC')->fetchAll();

adminHead('Users', 'users');
?>

<?php if ($error):   ?><div class="alert alert-error"  style="margin-bottom:16px"><?= h($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:16px"><?= h($success) ?></div><?php endif; ?>

<!-- Create user -->
<div class="admin-card" style="margin-bottom:24px">
  <div class="admin-card-head">Create User</div>
  <form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="admin-form-row">
      <div class="form-group" style="margin:0">
        <label>Username</label>
        <input type="text" name="username" required minlength="3" maxlength="32" pattern="[a-zA-Z0-9_\-]+" placeholder="username">
      </div>
      <div class="form-group" style="margin:0">
        <label>Password</label>
        <input type="password" name="password" required minlength="8" placeholder="min. 8 characters">
      </div>
      <div class="form-group" style="margin:0">
        <label>Role</label>
        <select name="role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:auto;padding:11px 20px;align-self:flex-end">Create</button>
    </div>
  </form>
</div>

<!-- Users list -->
<div class="admin-card">
  <div class="admin-card-head">All Users (<?= count($users) ?>)</div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Username</th>
        <th>Role</th>
        <th>Created</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
      $self = $u['id'] === currentUserId();
    ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:rgba(30,215,96,.12);border:1px solid rgba(30,215,96,.3);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--accent);flex-shrink:0">
              <?= strtoupper(substr(h($u['username']), 0, 1)) ?>
            </div>
            <span style="font-weight:600"><?= h($u['username']) ?></span>
            <?php if ($self): ?>
              <span class="tag tag-accent">You</span>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <span class="tag <?= $u['role'] === 'admin' ? 'tag-accent' : 'tag-muted' ?>">
            <?= h($u['role']) ?>
          </span>
        </td>
        <td style="color:var(--tf);font-size:13px"><?= h(date('d M Y', strtotime($u['created_at']))) ?></td>
        <td style="text-align:right">
          <?php if (!$self): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete user <?= h(addslashes($u['username'])) ?>?')">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-secondary" style="padding:6px 14px;font-size:12px;border-radius:50px">
                Delete
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php adminFoot(); ?>
