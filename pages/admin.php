<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireAdmin();
$db=getDB();$b=BASE_URL;$error=null;$success=null;

if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='create'){
    $u=trim($_POST['username']??'');$p=$_POST['password']??'';$role=$_POST['role']==='admin'?'admin':'user';
    if($u===''||$p===''){$error='Fill in all fields.';}
    elseif(strlen($u)<3||strlen($u)>32){$error='Username 3–32 chars.';}
    elseif(!preg_match('/^[a-zA-Z0-9_\-]+$/',$u)){$error='Letters, numbers, _ - only.';}
    elseif(strlen($p)<8){$error='Password min 8 chars.';}
    else{
        $c=$db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');$c->execute([$u]);
        if($c->fetch()){$error='Username already taken.';}
        else{
            $hash=password_hash($p,PASSWORD_BCRYPT,['cost'=>12]);
            $db->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,?)")->execute([$u,$hash,$role]);
            $success='User "'.h($u).'" created.';
        }
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='delete'){
    $uid=(int)($_POST['user_id']??0);
    if($uid===currentUserId()){$error='Cannot delete your own account.';}
    elseif($uid>0){$db->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);$success='User deleted.';}
}

$users=$db->query('SELECT id,username,role,created_at FROM users ORDER BY created_at ASC')->fetchAll();
layoutHead('Users');
?>
<div class="app-wrap">
<?php layoutSidebar('admin');?>
<div class="main-content">
  <header class="page-header"><h1 class="page-title">User Management</h1></header>
  <main class="page-body">
    <?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?>
    <?php if($success):?><div class="alert alert-success"><?=h($success)?></div><?php endif;?>

    <div class="section-title">Create User</div>
    <div class="song-table-wrap" style="margin-bottom:28px">
      <div style="padding:20px">
        <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 160px auto;gap:12px;align-items:end">
          <input type="hidden" name="action" value="create">
          <div class="form-group" style="margin-bottom:0"><label>Username</label><input type="text" name="username" required minlength="3" maxlength="32" pattern="[a-zA-Z0-9_\-]+"></div>
          <div class="form-group" style="margin-bottom:0"><label>Password</label><input type="password" name="password" required minlength="8"></div>
          <div class="form-group" style="margin-bottom:0"><label>Role</label>
            <select name="role" style="padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:15px;background:var(--bg);color:var(--text);outline:none;width:100%">
              <option value="user">User</option><option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 20px;white-space:nowrap">Create user</button>
        </form>
      </div>
    </div>

    <div class="section-title">All Users</div>
    <div class="song-table-wrap">
      <table class="song-table">
        <thead><tr><th>Username</th><th>Role</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach($users as $u):$self=$u['id']===currentUserId();?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:10px">
              <div class="user-avatar" style="width:28px;height:28px;font-size:11px"><?=strtoupper(substr(h($u['username']),0,1))?></div>
              <span style="font-weight:600"><?=h($u['username'])?></span>
              <?php if($self):?><span style="font-size:11px;background:var(--accent-soft);color:var(--accent);padding:2px 8px;border-radius:20px;font-weight:600">You</span><?php endif;?>
            </div></td>
            <td><span style="font-size:12px;font-weight:700;padding:3px 9px;border-radius:20px;<?=$u['role']==='admin'?'background:var(--accent-soft);color:var(--accent)':'background:var(--bg-muted);color:var(--text-muted)'?>"><?=h($u['role'])?></span></td>
            <td style="color:var(--text-faint);font-size:13px"><?=h(date('d M Y',strtotime($u['created_at'])))?></td>
            <td style="text-align:right"><?php if(!$self):?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?=h(addslashes($u['username']))?>')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>">
                <button type="submit" class="btn btn-secondary" style="font-size:12px;padding:6px 12px;display:inline-flex;align-items:center;gap:5px"><?=icon('trash')?> Delete</button>
              </form>
            <?php endif;?></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
