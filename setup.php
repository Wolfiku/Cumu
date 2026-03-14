<?php
require_once __DIR__ . '/backend/session.php';
if (isSetupDone()){header('Location:'.BASE_URL.'/index.php');exit;}
$step=(int)($_GET['step']??1);
$error=$_SESSION['setup_error']??null;unset($_SESSION['setup_error']);
$b=BASE_URL;

if ($_SERVER['REQUEST_METHOD']==='POST'&&$step===1) {
    $u=trim($_POST['username']??''); $p=$_POST['password']??''; $c=$_POST['confirm']??'';
    if($u===''||$p===''||$c===''){$_SESSION['setup_error']='Fill in all fields.';}
    elseif(strlen($u)<3||strlen($u)>32){$_SESSION['setup_error']='Username 3–32 chars.';}
    elseif(!preg_match('/^[a-zA-Z0-9_\-]+$/',$u)){$_SESSION['setup_error']='Letters, numbers, _ - only.';}
    elseif(strlen($p)<8){$_SESSION['setup_error']='Password min 8 chars.';}
    elseif($p!==$c){$_SESSION['setup_error']='Passwords do not match.';}
    else {
        $hash=password_hash($p,PASSWORD_BCRYPT,['cost'=>12]);
        getDB()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,'".str_replace("'","''",$hash)."','admin')")->execute([$u]);
        // Actually use prepared properly:
        getDB()->prepare("DELETE FROM users WHERE username=?")->execute([$u]);
        getDB()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')")->execute([$u,$hash]);
        session_regenerate_id(true);
        $r=getDB()->prepare('SELECT id,username,role FROM users WHERE username=? LIMIT 1');$r->execute([$u]);$usr=$r->fetch();
        $_SESSION['user_id']=$usr['id'];$_SESSION['username']=$usr['username'];$_SESSION['role']=$usr['role'];
        header('Location:'.$b.'/setup.php?step=2');exit;
    }
    header('Location:'.$b.'/setup.php?step=1');exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST'&&$step===2) {
    setSetting('setup_complete','1');
    if(($_POST['wolfiku']??'skip')==='connect'){header('Location:https://connect.wolfiku.de/cumu/registerdevice');exit;}
    header('Location:'.$b.'/pages/dashboard.php');exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cumu – Setup</title><link rel="stylesheet" href="<?=$b?>/style.css">
<style>
.step-row{display:flex;align-items:center;gap:8px;margin-bottom:32px}
.step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:var(--bg-muted);color:var(--text-faint);border:2px solid var(--border);flex-shrink:0}
.step-dot.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.step-dot.done{background:var(--accent-soft);color:var(--accent);border-color:var(--accent-mid)}
.step-line{flex:1;height:2px;background:var(--border);border-radius:1px}
.step-line.done{background:var(--accent-mid)}
.w-card{border:2px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;cursor:pointer;display:flex;align-items:center;gap:14px;margin-bottom:10px;transition:border-color .15s,background .15s}
.w-card:hover{border-color:var(--accent-mid);background:var(--accent-soft)}
.w-card.sel{border-color:var(--accent);background:var(--accent-soft)}
.w-card-icon{width:38px;height:38px;border-radius:50%;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.w-card.sel .w-card-icon{background:var(--accent);color:#fff}
.setup-tag{display:inline-flex;align-items:center;gap:6px;background:var(--accent-soft);color:var(--accent);font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:4px 10px;border-radius:20px;margin-bottom:16px}
</style></head>
<body class="auth-page"><div class="auth-card" style="max-width:460px">
  <div class="auth-logo">Cu<span>mu</span></div>
  <div class="auth-tagline" style="margin-bottom:24px">First-time setup</div>
  <div class="step-row">
    <div class="step-dot <?=$step===1?'active':($step>1?'done':'')?>">
      <?=$step>1?'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>':'1'?>
    </div>
    <div class="step-line <?=$step>1?'done':''?>"></div>
    <div class="step-dot <?=$step===2?'active':''?>">2</div>
  </div>

  <?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?>

  <?php if($step===1):?>
  <div class="setup-tag"><?=icon('shield')?> Step 1 of 2</div>
  <div class="auth-title" style="margin-bottom:6px">Create admin account</div>
  <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:22px">Only admins can create further user accounts.</p>
  <form method="POST" action="<?=$b?>/setup.php?step=1">
    <div class="form-group"><label>Username</label><input type="text" name="username" required minlength="3" maxlength="32" autofocus autocomplete="username"></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <div class="form-group"><label>Confirm password</label><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></div>
    <button type="submit" class="btn btn-primary">Create account &amp; continue</button>
  </form>

  <?php elseif($step===2):?>
  <div class="setup-tag"><?=icon('link')?> Step 2 of 2</div>
  <div class="auth-title" style="margin-bottom:6px">Wolfiku Connect</div>
  <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:22px">Link with your Wolfiku account for remote access.</p>
  <form method="POST" action="<?=$b?>/setup.php?step=2" id="sf">
    <div class="w-card" id="cc" onclick="sel('connect')">
      <input type="radio" name="wolfiku" value="connect" id="oc" style="display:none">
      <div class="w-card-icon" id="ic"><?=icon('link')?></div>
      <div><div style="font-weight:700;font-size:15px">Connect with Wolfiku</div><div style="font-size:13px;color:var(--text-muted);margin-top:2px">Redirects to connect.wolfiku.de to complete setup.</div></div>
    </div>
    <div class="w-card sel" id="cs" onclick="sel('skip')">
      <input type="radio" name="wolfiku" value="skip" id="os" checked style="display:none">
      <div class="w-card-icon sel" id="is"><?=icon('note')?></div>
      <div><div style="font-weight:700;font-size:15px">Skip for now</div><div style="font-size:13px;color:var(--text-muted);margin-top:2px">Use Cumu locally. You can link it later.</div></div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:8px">Finish setup</button>
  </form>
  <script>
    function sel(v){
      document.getElementById('oc').checked=v==='connect';
      document.getElementById('os').checked=v==='skip';
      document.getElementById('cc').classList.toggle('sel',v==='connect');
      document.getElementById('cs').classList.toggle('sel',v==='skip');
      document.getElementById('ic').classList.toggle('sel',v==='connect');
      document.getElementById('is').classList.toggle('sel',v==='skip');
    }
  </script>
  <?php endif;?>
</div></body></html>
