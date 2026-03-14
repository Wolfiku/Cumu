<?php
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/layout.php';
requireLogin();
$db = getDB(); $b = BASE_URL;

$artists = $db->query('SELECT a.id,a.name,a.image,COUNT(DISTINCT al.id) AS album_count,COUNT(s.id) AS song_count
    FROM artists a
    LEFT JOIN albums al ON al.artist_id=a.id
    LEFT JOIN songs  s  ON s.artist_id=a.id
    GROUP BY a.id ORDER BY a.name')->fetchAll();

layoutHead('Artists');
?>
<div class="app-wrap">
<?php layoutSidebar('artists');?>
<div class="main-content">
  <header class="page-header">
    <h1 class="page-title">Artists</h1>
    <div class="search-wrap"><?=icon('search')?><input type="search" class="search-input" id="search-input" placeholder="Search artists..."></div>
  </header>
  <main class="page-body">
    <?php if(empty($artists)):?>
      <div class="empty-state"><?=icon('mic')?><div class="empty-state-title">No artists yet</div></div>
    <?php else:?>
      <div class="artists-grid">
        <?php foreach($artists as $a):?>
          <a href="<?=$b?>/pages/artist.php?id=<?=(int)$a['id']?>" class="artist-card searchable" data-name="<?=h($a['name'])?>">
            <div class="artist-avatar"><?=strtoupper(substr(h($a['name']),0,1))?></div>
            <div class="artist-name"><?=h($a['name'])?></div>
            <div class="artist-meta"><?=(int)$a['album_count']?> album<?=$a['album_count']!=1?'s':''?> · <?=(int)$a['song_count']?> song<?=$a['song_count']!=1?'s':''?></div>
          </a>
        <?php endforeach;?>
      </div>
    <?php endif;?>
  </main>
</div>
<?php layoutPlayerBar();?>
</div>
<script>
document.getElementById('search-input')?.addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('.searchable').forEach(el=>el.style.display=(!q||el.dataset.name.toLowerCase().includes(q))?'':'none');
});
</script>
</body></html>
