/**
 * CUMU – shell_player.js
 * Runs in app.php (the persistent shell).
 * Audio element never destroyed. Navigation = iframe src change only.
 */
window.ShellPlayer = (function(){

  var queue      = [];
  var cur        = -1;
  var sheetSong  = null;
  var favPlId    = null;

  var B = function(){ return window.CUMU_BASE || ''; };

  /* ── DOM ────────────────────────────────────────── */
  function $id(id){ return document.getElementById(id); }

  var audio  = function(){ return $id('c-audio'); };

  function fmt(s){
    if(isNaN(s)||s<0) return '0:00';
    return Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0');
  }

  /* ── SVGs ───────────────────────────────────────── */
  var PLAY_LG  = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>';
  var PAUSE_LG = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>';
  var PLAY_SM  = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>';
  var PAUSE_SM = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>';
  var HEART_OFF= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
  var HEART_ON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="var(--accent)" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

  function setIcons(playing){
    document.querySelectorAll('.fp-play').forEach(function(b){ b.innerHTML = playing ? PAUSE_LG : PLAY_LG; });
    document.querySelectorAll('.mini-play').forEach(function(b){ b.innerHTML = playing ? PAUSE_SM : PLAY_SM; });
  }

  /* ── Update UI ──────────────────────────────────── */
  function updateUI(song){
    if(!song) return;
    var fpT=$id('fp-title'), fpA=$id('fp-artist'), fpArt=$id('fp-art'), fpBg=$id('fp-bg');
    var mT=$id('mini-title'), mA=$id('mini-artist'), mC=$id('mini-cover');
    if(fpT) fpT.textContent=song.title||'Unknown';
    if(fpA){ fpA.textContent=song.artist||'–'; fpA.dataset.artistId=song.artistId||''; }
    var artHtml = song.cover
      ? '<img src="'+song.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit">'
      : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:52px;height:52px;color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    if(fpArt) fpArt.innerHTML=artHtml;
    if(fpBg)  fpBg.style.backgroundImage=song.cover?'url("'+song.cover+'")':'';
    if(mT) mT.textContent=song.title||'Unknown';
    if(mA) mA.textContent=song.artist||'–';
    if(mC) mC.innerHTML=song.cover
      ? '<img src="'+song.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    var mp=$id('mini-player');
    if(mp) mp.style.display='flex';
    updateHeart(song.id);
    // Tell iframe about current song so it can highlight row
    var f=document.getElementById('main-frame');
    if(f&&f.contentWindow){
      try{ f.contentWindow.postMessage({type:'nowplaying',songId:song.id},'*'); }catch(e){}
    }
  }

  /* ── Progress ───────────────────────────────────── */
  function tick(){
    var el=audio(); if(!el) return;
    var c=el.currentTime, d=el.duration||0, p=d>0?c/d*100:0;
    var ff=$id('fp-fill');    if(ff) ff.style.width=p+'%';
    var mp=$id('mini-prog');  if(mp) mp.style.width=p+'%';
    var tc=$id('fp-cur');     if(tc) tc.textContent=fmt(c);
    var td=$id('fp-dur');     if(td) td.textContent=fmt(d);
  }

  /* ── Playback ───────────────────────────────────── */
  function playAt(idx){
    if(idx<0||idx>=queue.length) return;
    cur=idx;
    var song=queue[idx], el=audio(); if(!el) return;
    el.src=song.stream; el.load();
    el.play().catch(function(e){ console.warn('[Cumu]',e); });
    setIcons(true); updateUI(song);
    document.title=song.title+' – Cumu';
    var art=$id('fp-art');
    if(art){ art.classList.remove('playing'); requestAnimationFrame(function(){ art.classList.add('playing'); }); }
  }

  function togglePlay(){
    var el=audio(); if(!el) return;
    if(cur===-1&&queue.length){ playAt(0); return; }
    if(el.paused){ el.play(); setIcons(true); }
    else         { el.pause(); setIcons(false); }
  }

  /* ── Called from iframe pages via postMessage ───── */
  function receiveQueue(newQueue, startIdx){
    queue=newQueue;
    cur=startIdx!=null?startIdx:0;
    if(typeof startIdx==='number') playAt(startIdx);
  }

  /* ── Fullscreen ─────────────────────────────────── */
  var _touchStartY=0, _touchMoved=false;

  function openFP(){  var f=$id('fp'); if(f){ f.classList.add('open');  document.body.style.overflow='hidden'; } }
  function closeFP(){ var f=$id('fp'); if(f){ f.classList.remove('open'); document.body.style.overflow=''; } }

  /* ── Sheets ─────────────────────────────────────── */
  function openSongSheet(data){
    sheetSong=data;
    var sc=$id('ss-cover');
    if(sc) sc.innerHTML=data.cover?'<img src="'+data.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">':'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    var st=$id('ss-title'),  sa=$id('ss-artist');
    if(st) st.textContent=data.title||'';
    if(sa) sa.textContent=data.artist||'';
    var sal=$id('ss-artist-link');
    if(sal){ sal.style.display=data.artistId&&data.artistId!=='0'?'flex':'none'; }
    var sed=$id('ss-edit-link');
    if(sed){ sed.style.display=window.CUMU_ADMIN?'flex':'none'; }
    var o=$id('song-sheet-overlay'); if(o) o.classList.add('open');
  }
  function closeSongSheet(){ var o=$id('song-sheet-overlay'); if(o) o.classList.remove('open'); }

  function openPlSheet(songId){
    var o=$id('pl-sheet'), list=$id('pl-sheet-list');
    if(!o||!list) return;
    list.innerHTML='<div style="padding:16px;text-align:center;color:var(--tf)">Loading…</div>';
    o.classList.add('open');
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'list_user'})})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok&&d.data.length){
        list.innerHTML=d.data.map(function(pl){
          return '<div class="sheet-item" data-pid="'+pl.id+'"><div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg></div><div class="sheet-item-name">'+pl.name+'</div></div>';
        }).join('');
        list.querySelectorAll('.sheet-item[data-pid]').forEach(function(item){
          item.onclick=function(){
            fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_song',playlist_id:parseInt(item.dataset.pid),song_id:parseInt(songId)})})
            .then(function(r){return r.json();}).then(function(){ closePlSheet(); });
          };
        });
      } else {
        list.innerHTML='<div style="padding:20px;text-align:center;color:var(--t2);font-size:14px">No playlists yet.</div>';
      }
    }).catch(function(){ list.innerHTML='<div style="padding:16px;text-align:center;color:var(--tf)">Error</div>'; });
  }
  function closePlSheet(){ var o=$id('pl-sheet'); if(o) o.classList.remove('open'); }

  /* ── Favorites ──────────────────────────────────── */
  function getFavPlId(cb){
    if(favPlId!==null){ cb(favPlId); return; }
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_or_create_favorites'})})
    .then(function(r){return r.json();})
    .then(function(d){ if(d.ok){ favPlId=d.data.id; cb(favPlId); } }).catch(function(){});
  }

  function updateHeart(songId){
    var btn=$id('fp-heart'); if(!btn||!songId) return;
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'is_favorite',song_id:parseInt(songId)})})
    .then(function(r){return r.json();})
    .then(function(d){ if(d.ok){ btn.innerHTML=d.data.is_fav?HEART_ON:HEART_OFF; btn.dataset.fav=d.data.is_fav?'1':'0'; } }).catch(function(){});
  }

  function toggleFavorite(){
    if(cur<0||cur>=queue.length) return;
    var songId=queue[cur].id, btn=$id('fp-heart'); if(!btn) return;
    var isFav=btn.dataset.fav==='1';
    getFavPlId(function(pid){
      fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:isFav?'remove_song':'add_song',playlist_id:pid,song_id:parseInt(songId)})})
      .then(function(r){return r.json();})
      .then(function(d){
        if(d.ok){ isFav=!isFav; btn.innerHTML=isFav?HEART_ON:HEART_OFF; btn.dataset.fav=isFav?'1':'0';
          btn.animate([{transform:'scale(1.35)'},{transform:'scale(1)'}],{duration:220,easing:'ease-out'}); }
      });
    });
  }

  /* ── Bind swipe on FP ───────────────────────────── */
  function bindSwipe(){
    var fp=$id('fp'); if(!fp) return;
    var startY=0, moved=0;
    fp.addEventListener('touchstart',function(e){ startY=e.touches[0].clientY; moved=0; },{passive:true});
    fp.addEventListener('touchmove', function(e){ moved=e.touches[0].clientY-startY; if(moved>0){fp.style.transform='translateY('+moved+'px)';fp.style.transition='none';} },{passive:true});
    fp.addEventListener('touchend',  function(){ fp.style.transition=''; if(moved>120){closeFP();fp.style.transform='';}else{fp.style.transform='';} });
  }

  /* ── Init ───────────────────────────────────────── */
  function init(){
    var el=audio();
    bindSwipe();

    if(el){
      el.addEventListener('timeupdate',tick);
      el.addEventListener('ended',function(){ setIcons(false); if(cur<queue.length-1) playAt(cur+1); });
      el.addEventListener('pause',function(){ setIcons(false); });
      el.addEventListener('play', function(){ setIcons(true);  });
    }

    // Mini player → FP (with scroll guard)
    var mp=$id('mini-player');
    if(mp){
      mp.addEventListener('touchstart',function(e){ _touchStartY=e.touches[0].clientY; _touchMoved=false; },{passive:true});
      mp.addEventListener('touchmove', function(e){ if(Math.abs(e.touches[0].clientY-_touchStartY)>8)_touchMoved=true; },{passive:true});
      mp.addEventListener('click',function(e){ if(_touchMoved)return; if(e.target.closest('.mini-btn,.mini-play'))return; openFP(); });
    }

    $id('fp-down')     && $id('fp-down').addEventListener('click', closeFP);
    $id('fp-play-btn') && $id('fp-play-btn').addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });
    $id('fp-prev')     && $id('fp-prev').addEventListener('click',function(){ var a=audio(); if(a&&a.currentTime>3){a.currentTime=0;return;} if(cur>0)playAt(cur-1); });
    $id('fp-next')     && $id('fp-next').addEventListener('click',function(){ if(cur<queue.length-1)playAt(cur+1); });
    $id('mini-play')   && $id('mini-play').addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });
    $id('mini-next')   && $id('mini-next').addEventListener('click',function(e){ e.stopPropagation(); if(cur<queue.length-1)playAt(cur+1); });

    var fpTrack=$id('fp-track');
    fpTrack && fpTrack.addEventListener('click',function(e){ var a=audio(); if(!a||!a.duration)return; var r=fpTrack.getBoundingClientRect(); a.currentTime=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width))*a.duration; });

    $id('fp-artist') && $id('fp-artist').addEventListener('click',function(){
      var aid=this.dataset.artistId;
      if(aid&&aid!=='0'){ closeFP(); window.navTo('pages/artist.php','id='+aid); }
    });

    $id('fp-more-btn') && $id('fp-more-btn').addEventListener('click',function(){
      if(cur>=0&&cur<queue.length){ var s=queue[cur]; openSongSheet({songId:s.id,title:s.title,artist:s.artist,cover:s.cover,artistId:s.artistId}); }
    });

    $id('fp-heart') && $id('fp-heart').addEventListener('click', toggleFavorite);

    $id('ss-add-pl') && $id('ss-add-pl').addEventListener('click',function(){
      if(sheetSong){ var sid=sheetSong.songId; closeSongSheet(); openPlSheet(sid); }
    });
    $id('ss-artist-link') && $id('ss-artist-link').addEventListener('click',function(){
      if(sheetSong&&sheetSong.artistId&&sheetSong.artistId!=='0'){ closeSongSheet(); window.navTo('pages/artist.php','id='+sheetSong.artistId); }
    });
    $id('ss-edit-link') && $id('ss-edit-link').addEventListener('click',function(){
      if(sheetSong){ closeSongSheet(); window.navTo('pages/song_edit.php','id='+sheetSong.songId); }
    });

    var sso=$id('song-sheet-overlay');
    sso && sso.addEventListener('click',function(e){ if(e.target===sso)closeSongSheet(); });
    var plo=$id('pl-sheet');
    plo && plo.addEventListener('click',function(e){ if(e.target===plo)closePlSheet(); });
    $id('pl-new-item') && $id('pl-new-item').addEventListener('click',function(){ closePlSheet(); window.navTo('pages/library.php'); });

    document.addEventListener('keydown',function(e){
      if(['INPUT','TEXTAREA','SELECT'].indexOf(e.target.tagName)>=0) return;
      if(e.code==='Space'){ e.preventDefault(); togglePlay(); }
      if(e.code==='ArrowRight'){ var a=audio();if(a)a.currentTime=Math.min((a.duration||0),a.currentTime+10); }
      if(e.code==='ArrowLeft') { var a=audio();if(a)a.currentTime=Math.max(0,a.currentTime-10); }
      if(e.code==='Escape'){ closeFP(); closeSongSheet(); closePlSheet(); }
    });
  }

  document.addEventListener('DOMContentLoaded', init);
  return { receiveQueue:receiveQueue, playAt:playAt, openSongSheetPublic:openSongSheet, queue:function(){return queue;} };
})();
