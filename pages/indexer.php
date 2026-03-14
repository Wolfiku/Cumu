<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$b=BASE_URL;
$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['run'])){
    $result=runIndexer();
}
layoutHead('Re-Index Library');
?>
<div class="app-wrap">
<?php layoutSidebar('indexer');?>
<div class="main-content">
  <header class="page-header"><h1 class="page-title">Re-Index Library</h1></header>
  <main class="page-body" style="max-width:640px">
    <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px">
      <div style="font-weight:700;font-size:15px;margin-bottom:8px">What this does</div>
      <p style="font-size:14px;color:var(--text-muted);line-height:1.7">
        Scans the <code>music/</code> folder and adds any new files to the database.<br>
        Files that are already indexed are skipped.<br>
        Folder structure: <code>music/Artist/Album/track.mp3</code>
      </p>
    </div>
    <form method="POST">
      <input type="hidden" name="run" value="1">
      <button type="submit" class="btn btn-primary" style="width:auto;padding:11px 24px"><?=icon('refresh')?> Run Indexer</button>
    </form>
    <?php if($result!==null):?>
      <div style="margin-top:28px">
        <div style="display:flex;gap:16px;margin-bottom:16px">
          <div class="stat-card" style="flex:1"><div class="stat-value" style="color:#166534"><?=$result['added']?></div><div class="stat-label">Added</div></div>
          <div class="stat-card" style="flex:1"><div class="stat-value" style="color:var(--text-muted)"><?=$result['skipped']?></div><div class="stat-label">Skipped</div></div>
          <div class="stat-card" style="flex:1"><div class="stat-value" style="color:#991B1B"><?=$result['errors']?></div><div class="stat-label">Errors</div></div>
        </div>
        <?php if(!empty($result['log'])):?>
          <div style="background:#111;border-radius:var(--radius-lg);padding:16px;max-height:320px;overflow-y:auto">
            <?php foreach($result['log'] as $line):
              $c=str_starts_with($line,'[OK]')?'#4ade80':(str_starts_with($line,'[ERR]')?'#f87171':'#999');
            ?>
              <div style="font-family:var(--mono);font-size:12px;color:<?=$c?>;padding:2px 0"><?=h($line)?></div>
            <?php endforeach;?>
          </div>
        <?php endif;?>
        <?php if($result['added']>0):?>
          <div style="margin-top:16px"><a href="<?=$b?>/pages/library.php" class="btn btn-primary" style="width:auto;padding:10px 20px">View Library</a></div>
        <?php endif;?>
      </div>
    <?php endif;?>
  </main>
</div>
<?php layoutPlayerBar();?>
</div></body></html>
