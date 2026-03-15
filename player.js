/**
 * CUMU – player.js  v3
 *
 * Fixes:
 *  1. Music persists across page navigation (sessionStorage queue)
 *  2. Song sheet not visible when fullscreen player is open (z-index stack)
 *  3. Accidental fullscreen open on fast scroll (touch threshold)
 *  4. Upload tab buttons broken (moved to upload.php inline JS)
 *  5. Favorites playlist support
 *  6. Improved fullscreen UI wiring
 */
var Cumu = (function () {

  /* ─────────────────────────────────────────────────
     STATE  (persisted in sessionStorage)
  ───────────────────────────────────────────────── */
  var queue    = [];   // [{id,stream,title,artist,cover,artistId}]
  var cur      = -1;
  var playing  = false;
  var sheetSong = null; // song data for the options sheet

  var SS_KEY = 'cumu_player';

  function saveState() {
    try {
      sessionStorage.setItem(SS_KEY, JSON.stringify({
        queue: queue, cur: cur,
        time:  audio() ? audio().currentTime : 0,
        src:   audio() ? audio().src : ''
      }));
    } catch(e) {}
  }

  function loadState() {
    try {
      var raw = sessionStorage.getItem(SS_KEY);
      if (!raw) return;
      var s = JSON.parse(raw);
      if (s.queue && s.queue.length) {
        queue = s.queue;
        cur   = typeof s.cur === 'number' ? s.cur : -1;
        if (cur >= 0 && cur < queue.length) {
          var el = audio();
          if (el && s.src) {
            el.src = s.src;
            el.load();
            // Restore time but don't autoplay — user must tap play
            el.addEventListener('loadedmetadata', function onMeta() {
              el.removeEventListener('loadedmetadata', onMeta);
              el.currentTime = s.time || 0;
            });
          }
          // Rebuild idx on current page rows (may differ)
          buildQueue(null, true);
          updateUI(queue[cur]);
          setIcons(false); // not autoplaying
          updateProgress();
          // Show mini player
          var mp = document.getElementById('mini-player');
          if (mp) mp.style.display = 'flex';
        }
      }
    } catch(e) {}
  }

  /* ─────────────────────────────────────────────────
     DOM helpers
  ───────────────────────────────────────────────── */
  function audio() { return document.getElementById('c-audio'); }
  function $(id)   { return document.getElementById(id); }

  function fmt(s) {
    if (isNaN(s) || s < 0) return '0:00';
    return Math.floor(s/60) + ':' + String(Math.floor(s%60)).padStart(2,'0');
  }

  function B() { return window.CUMU_BASE || ''; }

  /* ─────────────────────────────────────────────────
     ICONS
  ───────────────────────────────────────────────── */
  var IC = {
    PLAY_LG:  '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
    PAUSE_LG: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>',
    PLAY_SM:  '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
    PAUSE_SM: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>',
    HEART_OFF:'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    HEART_ON: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'
  };

  function setIcons(p) {
    playing = p;
    document.querySelectorAll('.fp-play').forEach(function(b) {
      b.innerHTML = p ? IC.PAUSE_LG : IC.PLAY_LG;
    });
    document.querySelectorAll('.mini-play').forEach(function(b) {
      b.innerHTML = p ? IC.PAUSE_SM : IC.PLAY_SM;
    });
  }

  /* ─────────────────────────────────────────────────
     UPDATE UI
  ───────────────────────────────────────────────── */
  function updateUI(song) {
    if (!song) return;
    var fpT  = $('fp-title'),  fpA = $('fp-artist');
    var fpArt= $('fp-art'),    fpBg= $('fp-bg');
    var mT   = $('mini-title'), mA = $('mini-artist'), mC = $('mini-cover');

    if (fpT)  fpT.textContent = song.title  || 'Unknown';
    if (fpA) { fpA.textContent = song.artist || '–'; fpA.dataset.artistId = song.artistId || ''; }
    if (fpArt) {
      fpArt.innerHTML = song.cover
        ? '<img src="' + song.cover + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:52px;height:52px;color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    }
    if (fpBg)  fpBg.style.backgroundImage = song.cover ? 'url("' + song.cover + '")' : '';
    if (mT)    mT.textContent  = song.title  || 'Unknown';
    if (mA)    mA.textContent  = song.artist || '–';
    if (mC)    mC.innerHTML    = song.cover
      ? '<img src="' + song.cover + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';

    var mp = $('mini-player');
    if (mp) mp.style.display = 'flex';

    // heart button
    updateHeart(song.id);
  }

  function syncRows(idx) {
    document.querySelectorAll('.song-row').forEach(function(row) {
      var i = parseInt(row.dataset.idx, 10), match = (i === idx);
      row.classList.toggle('playing', match);
      var num = row.querySelector('.sr-num,.song-num');
      var ind = row.querySelector('.play-ind');
      if (match) {
        if (num) num.style.visibility = 'hidden';
        if (!ind) {
          var el = document.createElement('span');
          el.className = 'play-ind';
          el.innerHTML = '<span></span><span></span><span></span><span></span>';
          row.appendChild(el);
        }
      } else {
        if (num) num.style.visibility = '';
        if (ind) ind.remove();
      }
    });
  }

  /* ─────────────────────────────────────────────────
     PLAYBACK
  ───────────────────────────────────────────────── */
  function playAt(idx) {
    if (idx < 0 || idx >= queue.length) return;
    cur = idx;
    var song = queue[idx], el = audio();
    if (!el) return;
    el.src = song.stream; el.load();
    el.play().catch(function(e){ console.warn('[Cumu]', e); });
    setIcons(true);
    updateUI(song);
    syncRows(idx);
    document.title = song.title + ' – Cumu';
    var art = $('fp-art');
    if (art) { art.classList.remove('playing'); requestAnimationFrame(function(){ art.classList.add('playing'); }); }
    saveState();
  }

  function togglePlay() {
    var el = audio(); if (!el) return;
    if (cur === -1 && queue.length) { playAt(0); return; }
    if (el.paused) { el.play(); setIcons(true); }
    else           { el.pause(); setIcons(false); }
    saveState();
  }

  /* ─────────────────────────────────────────────────
     PROGRESS
  ───────────────────────────────────────────────── */
  function updateProgress() {
    var el = audio(); if (!el) return;
    var c  = el.currentTime, d = el.duration || 0, p = d > 0 ? c/d*100 : 0;
    var ff = $('fp-fill');    if (ff) ff.style.width = p + '%';
    var mp = $('mini-prog');  if (mp) mp.style.width = p + '%';
    var tc = $('fp-cur');     if (tc) tc.textContent = fmt(c);
    var td = $('fp-dur');     if (td) td.textContent = fmt(d);
  }

  /* ─────────────────────────────────────────────────
     FULLSCREEN PLAYER
  ───────────────────────────────────────────────── */
  // Track touch start to avoid opening FP on scroll
  var _touchStartY = 0, _touchMoved = false;

  function openFP() {
    var fp = $('fp');
    if (fp) { fp.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
  function closeFP() {
    var fp = $('fp');
    if (fp) { fp.classList.remove('open'); document.body.style.overflow = ''; }
  }

  /* ─────────────────────────────────────────────────
     SHEETS  (song options + playlist picker)
     z-index: song-sheet = 300, pl-sheet = 350
     both above fp (200) so they're visible from FP
  ───────────────────────────────────────────────── */
  function openSongSheet(data) {
    sheetSong = data;
    var cover   = data.cover || '';
    var title   = data.title  || '';
    var artist  = data.artist || '';
    var songId  = data.songId || '';
    var artistId= data.artistId || '';

    // Cover
    var ssCover = $('ss-cover');
    if (ssCover) ssCover.innerHTML = cover
      ? '<img src="' + cover + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';

    var ssT = $('ss-title');   if (ssT) ssT.textContent  = title;
    var ssA = $('ss-artist');  if (ssA) ssA.textContent  = artist;

    // Artist link
    var ssAl = $('ss-artist-link');
    if (ssAl) { ssAl.href = artistId && artistId !== '0' ? B()+'/pages/artist.php?id='+artistId : '#'; ssAl.style.display = artistId && artistId!=='0' ? 'flex':'none'; }

    // Edit link (admin only)
    var ssEd = $('ss-edit-link');
    if (ssEd) { ssEd.style.display = window.CUMU_ADMIN ? 'flex' : 'none'; if (window.CUMU_ADMIN) ssEd.href = B()+'/pages/song_edit.php?id='+songId; }

    var o = $('song-sheet-overlay');
    if (o) { o.style.zIndex = 310; o.classList.add('open'); }
  }

  function closeSongSheet() {
    var o = $('song-sheet-overlay');
    if (o) o.classList.remove('open');
    sheetSong = null;
  }

  function openPlSheet(songId) {
    var overlay = $('pl-sheet'), list = $('pl-sheet-list');
    if (!overlay||!list) return;
    overlay.style.zIndex = 320;
    list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--tf)">Loading…</div>';
    overlay.classList.add('open');

    fetch(B()+'/backend/playlist_api.php',{ method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'list_user'}) })
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.ok && d.data.length) {
        list.innerHTML = d.data.map(function(pl){
          return '<div class="sheet-item" data-pid="'+pl.id+'"><div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg></div><div class="sheet-item-name">'+pl.name+'</div></div>';
        }).join('');
        list.querySelectorAll('.sheet-item[data-pid]').forEach(function(item){
          item.onclick = function(){
            fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_song',playlist_id:parseInt(item.dataset.pid),song_id:parseInt(songId)})})
            .then(function(r){return r.json();})
            .then(function(d){ closePlSheet(); if(d.error&&d.error!=='Song already in playlist.') alert(d.error); });
          };
        });
      } else {
        list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--t2);font-size:14px">No playlists yet.</div>';
      }
    })
    .catch(function(){ list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--tf)">Error</div>'; });
  }

  function closePlSheet() {
    var o = $('pl-sheet'); if (o) o.classList.remove('open');
  }

  /* ─────────────────────────────────────────────────
     FAVORITES
  ───────────────────────────────────────────────── */
  var favPlId = null; // cached favorites playlist ID

  function getFavPlId(cb) {
    if (favPlId !== null) { cb(favPlId); return; }
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_or_create_favorites'})})
    .then(function(r){return r.json();})
    .then(function(d){ if(d.ok){favPlId=d.data.id; cb(favPlId);} })
    .catch(function(){});
  }

  function updateHeart(songId) {
    var btn = $('fp-heart'); if (!btn || !songId) return;
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'is_favorite',song_id:parseInt(songId)})})
    .then(function(r){return r.json();})
    .then(function(d){ if(d.ok) btn.innerHTML = d.data.is_fav ? IC.HEART_ON : IC.HEART_OFF; btn.dataset.fav=d.data.is_fav?'1':'0'; })
    .catch(function(){});
  }

  function toggleFavorite() {
    if (cur<0||cur>=queue.length) return;
    var songId = queue[cur].id;
    var btn    = $('fp-heart'); if (!btn) return;
    var isFav  = btn.dataset.fav === '1';
    getFavPlId(function(pid){
      var action = isFav ? 'remove_song' : 'add_song';
      fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:action,playlist_id:pid,song_id:parseInt(songId)})})
      .then(function(r){return r.json();})
      .then(function(d){
        if(d.ok){
          isFav = !isFav;
          btn.innerHTML  = isFav ? IC.HEART_ON : IC.HEART_OFF;
          btn.dataset.fav= isFav ? '1' : '0';
          btn.style.color= isFav ? 'var(--accent)' : '';
          // Pulse animation
          btn.animate([{transform:'scale(1.3)'},{transform:'scale(1)'}],{duration:250,easing:'ease-out'});
        }
      });
    });
  }

  /* ─────────────────────────────────────────────────
     QUEUE
  ───────────────────────────────────────────────── */
  // mergeOnly = true: just set dataset.idx without replacing queue
  function buildQueue(rows, mergeOnly) {
    var nodeList = rows || document.querySelectorAll('.song-row[data-id]');
    if (!mergeOnly) queue = [];
    var i = 0;
    nodeList.forEach(function(row){
      row.dataset.idx = i;
      if (!mergeOnly) {
        queue.push({ id:row.dataset.id||'', stream:row.dataset.stream||'', title:row.dataset.title||'', artist:row.dataset.artist||'', cover:row.dataset.cover||'', artistId:row.dataset.artistId||'' });
      }
      i++;
    });
  }

  /* ─────────────────────────────────────────────────
     SWIPE TO CLOSE FP
  ───────────────────────────────────────────────── */
  function bindSwipe() {
    var fp = $('fp'); if (!fp) return;
    var startY=0, moved=0;
    fp.addEventListener('touchstart',function(e){ startY=e.touches[0].clientY; moved=0; },{passive:true});
    fp.addEventListener('touchmove', function(e){
      moved=e.touches[0].clientY-startY;
      if (moved>0){ fp.style.transform='translateY('+moved+'px)'; fp.style.transition='none'; }
    },{passive:true});
    fp.addEventListener('touchend',function(){
      fp.style.transition='';
      if (moved>120){ closeFP(); fp.style.transform=''; }
      else { fp.style.transform=''; }
    });
  }

  /* ─────────────────────────────────────────────────
     INIT
  ───────────────────────────────────────────────── */
  function init() {
    var el = audio();
    buildQueue();
    loadState();   // ← restore queue/position from previous page
    bindSwipe();
    if (el) {
      el.addEventListener('timeupdate', updateProgress);
      el.addEventListener('ended', function(){
        setIcons(false);
        if (cur < queue.length-1) playAt(cur+1);
        saveState();
      });
      el.addEventListener('pause', function(){ setIcons(false); saveState(); });
      el.addEventListener('play',  function(){ setIcons(true);  saveState(); });
    }

    // Save state before leaving page
    window.addEventListener('beforeunload', saveState);
    window.addEventListener('pagehide',     saveState);

    /* Mini player – only open FP if not a scroll gesture */
    var miniPl = $('mini-player');
    if (miniPl) {
      miniPl.addEventListener('touchstart', function(e){ _touchStartY=e.touches[0].clientY; _touchMoved=false; },{passive:true});
      miniPl.addEventListener('touchmove',  function(e){ if(Math.abs(e.touches[0].clientY-_touchStartY)>8) _touchMoved=true; },{passive:true});
      miniPl.addEventListener('click', function(e){
        if (_touchMoved) return;
        if (e.target.closest('.mini-btn,.mini-play')) return;
        openFP();
      });
    }

    /* FP close */
    var fpDown = $('fp-down');
    if (fpDown) fpDown.addEventListener('click', closeFP);

    /* FP play */
    document.querySelectorAll('.fp-play').forEach(function(b){
      b.addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });
    });

    /* Mini play */
    document.querySelectorAll('.mini-play').forEach(function(b){
      b.addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });
    });

    /* Prev / Next */
    var fpPrev=$('fp-prev'),fpNext=$('fp-next'),miniNext=$('mini-next');
    if (fpPrev) fpPrev.addEventListener('click',function(){ var a=audio(); if(a&&a.currentTime>3){a.currentTime=0;return;} if(cur>0)playAt(cur-1); });
    if (fpNext) fpNext.addEventListener('click',function(){ if(cur<queue.length-1)playAt(cur+1); });
    if (miniNext) miniNext.addEventListener('click',function(e){ e.stopPropagation(); if(cur<queue.length-1)playAt(cur+1); });

    /* FP seek */
    var fpTrack=$('fp-track');
    if (fpTrack) fpTrack.addEventListener('click',function(e){
      var a=audio(); if(!a||!a.duration) return;
      var r=fpTrack.getBoundingClientRect();
      a.currentTime=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width))*a.duration;
    });

    /* FP artist btn */
    var fpArtist=$('fp-artist');
    if (fpArtist) fpArtist.addEventListener('click',function(){
      var aid=fpArtist.dataset.artistId;
      if(aid&&aid!=='0'){ closeFP(); location.href=B()+'/pages/artist.php?id='+aid; }
    });

    /* FP 3-dot */
    var fpMore=$('fp-more-btn');
    if (fpMore) fpMore.addEventListener('click',function(){
      if (cur>=0&&cur<queue.length) {
        var s=queue[cur];
        openSongSheet({songId:s.id,title:s.title,artist:s.artist,cover:s.cover,artistId:s.artistId});
      }
    });

    /* FP heart */
    var fpHeart=$('fp-heart');
    if (fpHeart) fpHeart.addEventListener('click', toggleFavorite);

    /* Song rows */
    document.querySelectorAll('.song-row[data-id]').forEach(function(row){
      row.addEventListener('click',function(e){
        if(e.target.closest('.sr-action,.sr-more')) return;
        playAt(parseInt(row.dataset.idx,10));
      });
    });

    /* Row 3-dot */
    document.querySelectorAll('.sr-more').forEach(function(btn){
      btn.addEventListener('click',function(e){
        e.stopPropagation();
        openSongSheet({songId:btn.dataset.songId,title:btn.dataset.title,artist:btn.dataset.artist,cover:btn.dataset.cover,artistId:btn.dataset.artistId});
      });
    });

    /* Song sheet: add to playlist */
    var ssAddPl=$('ss-add-pl');
    if (ssAddPl) ssAddPl.addEventListener('click',function(){
      if(sheetSong){ var sid=sheetSong.songId; closeSongSheet(); openPlSheet(sid); }
    });

    /* Song sheet overlay click */
    var sso=$('song-sheet-overlay');
    if (sso) sso.addEventListener('click',function(e){ if(e.target===sso||e.target.closest('.sheet-overlay')===sso&&!e.target.closest('.sheet')) closeSongSheet(); });

    /* PL sheet new */
    var plNew=$('pl-new-item');
    if (plNew) plNew.addEventListener('click',function(){ closePlSheet(); location.href=B()+'/pages/library.php'; });

    var plO=$('pl-sheet');
    if (plO) plO.addEventListener('click',function(e){ if(e.target===plO) closePlSheet(); });

    /* Keyboard */
    document.addEventListener('keydown',function(e){
      if(['INPUT','TEXTAREA','SELECT'].indexOf(e.target.tagName)>=0) return;
      if(e.code==='Space')     { e.preventDefault(); togglePlay(); }
      if(e.code==='ArrowRight'){ var a=audio();if(a)a.currentTime=Math.min((a.duration||0),a.currentTime+10); }
      if(e.code==='ArrowLeft') { var a=audio();if(a)a.currentTime=Math.max(0,a.currentTime-10); }
      if(e.code==='Escape')    { closeFP(); closeSongSheet(); closePlSheet(); }
    });

    // Restore highlight on current page
    if (cur>=0) syncRows(cur);
  }

  return { init:init, playAt:playAt, buildQueue:buildQueue, queue:function(){return queue;} };

})();

document.addEventListener('DOMContentLoaded', Cumu.init);
