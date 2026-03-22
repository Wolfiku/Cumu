/**
 * CUMU – shell_player.js  v4
 * Persistent audio shell. Handles two modes:
 *   'music'    – normal player (prev/next, heart, artist link)
 *   'audiobook' – ±15s skip, no heart, no prev/next, no artist link
 */
window.ShellPlayer = (function(){

  /* ── State ──────────────────────────────────────── */
  var queue     = [];   // music queue
  var cur       = -1;
  var mode      = 'music'; // 'music' | 'audiobook' | 'mixtape'
  var curMixtape = null; // {id, name, count}
  var curAb     = null;    // current audiobook {id,stream,title,series,cover}
  var sheetSong = null;
  var favPlId   = null;

  var B = function(){ return window.CUMU_BASE || ''; };

  /* ── DOM ─────────────────────────────────────────── */
  function $id(id){ return document.getElementById(id); }
  function audio(){ return $id('c-audio'); }
  function fmt(s){ if(isNaN(s)||s<0)return'0:00'; return Math.floor(s/60)+':'+String(Math.floor(s%60)).padStart(2,'0'); }

  /* ── SVGs ────────────────────────────────────────── */
  var IC = {
    PLAY_LG : '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
    PAUSE_LG: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>',
    PLAY_SM : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>',
    PAUSE_SM: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1.5"/><rect x="14" y="4" width="4" height="16" rx="1.5"/></svg>',
    HEART_OFF:'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    HEART_ON :'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="var(--accent)" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    BACK15  :'<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/><text x="8" y="16" font-size="5" fill="currentColor" stroke="none" font-family="Arial" font-weight="700">15</text></svg>',
    FWD15   :'<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.49-3.5"/><text x="8" y="16" font-size="5" fill="currentColor" stroke="none" font-family="Arial" font-weight="700">15</text></svg>',
  };

  /* ── Icon helpers ────────────────────────────────── */
  function setPlayIcons(playing){
    $id('fp-play-btn') && ($id('fp-play-btn').innerHTML = playing ? IC.PAUSE_LG : IC.PLAY_LG);
    document.querySelectorAll('.mini-play').forEach(function(b){ b.innerHTML = playing ? IC.PAUSE_SM : IC.PLAY_SM; });
  }

  /* ════════════════════════════════════════════════
     MODE SWITCHING
     The fullscreen player has two layouts:
     - Music:     prev | play | next   + heart + artist
     - Audiobook: -15s | play | +15s   (no heart, no artist)
  ════════════════════════════════════════════════ */
  function setMode(m){
    mode = m;
    var prevBtn   = $id('fp-prev');
    var nextBtn   = $id('fp-next');
    var heartBtn  = $id('fp-heart');
    var artistBtn = $id('fp-artist');
    var labelEl   = $id('fp-label');
    var abLabel   = $id('fp-ab-label');  // series label, only audiobook mode

    if (m === 'mixtape') {
      // same as music but different label + cassette art
      if (prevBtn) { prevBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>'; delete prevBtn.dataset.abMode; }
      if (nextBtn) { nextBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>'; delete nextBtn.dataset.abMode; }
      if (heartBtn)  heartBtn.style.display  = '';
      if (artistBtn) artistBtn.style.display = 'none';
      if (labelEl)   labelEl.textContent     = 'Mixtape';
      if (abLabel)   abLabel.style.display   = 'none';
    } else if (m === 'audiobook') {
      // Prev → –15s
      if (prevBtn) {
        prevBtn.innerHTML = IC.BACK15;
        prevBtn.title = '−15 seconds';
        prevBtn.dataset.abMode = '1';
      }
      // Next → +15s
      if (nextBtn) {
        nextBtn.innerHTML = IC.FWD15;
        nextBtn.title = '+15 seconds';
        nextBtn.dataset.abMode = '1';
      }
      if (heartBtn)  heartBtn.style.display  = 'none';
      if (artistBtn) artistBtn.style.display = 'none';
      if (labelEl)   labelEl.textContent     = 'Hörbuch';
      if (abLabel)   abLabel.style.display   = 'block';
    } else {
      // Music mode
      if (prevBtn) {
        prevBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>';
        prevBtn.title = 'Previous';
        delete prevBtn.dataset.abMode;
      }
      if (nextBtn) {
        nextBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>';
        nextBtn.title = 'Next';
        delete nextBtn.dataset.abMode;
      }
      if (heartBtn)  heartBtn.style.display  = '';
      if (artistBtn) artistBtn.style.display = '';
      if (labelEl)   labelEl.textContent     = 'Now Playing';
      if (abLabel)   abLabel.style.display   = 'none';
    }
  }

  /* ════════════════════════════════════════════════
     UPDATE UI
  ════════════════════════════════════════════════ */
  function updateUI(item){
    if (!item) return;
    var fpT  = $id('fp-title');
    var fpA  = $id('fp-artist');
    var fpArt= $id('fp-art');
    var fpBg = $id('fp-bg');
    var mT   = $id('mini-title');
    var mA   = $id('mini-artist');
    var mC   = $id('mini-cover');

    var artHtml;
    if (mode === 'mixtape' && curMixtape) {
      artHtml = buildCassetteHTML(curMixtape.name, curMixtape.count);
    } else {
      artHtml = item.cover
        ? '<img src="'+item.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit">'
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:52px;height:52px;color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    }
    var miniArtHtml = item.cover
      ? '<img src="'+item.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#52525b"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';

    if (fpT)  fpT.textContent = item.title  || 'Unknown';
    if (fpA)  { fpA.textContent = item.artist || (item.series || '–'); fpA.dataset.artistId = item.artistId || ''; }
    if (fpArt) fpArt.innerHTML = artHtml;
    if (fpBg)  fpBg.style.backgroundImage = item.cover ? 'url("'+item.cover+'")' : '';
    if (mT)    mT.textContent  = item.title  || 'Unknown';
    if (mA)    mA.textContent  = item.artist || (item.series || '–');
    if (mC)    mC.innerHTML    = miniArtHtml;

    var mp = $id('mini-player');
    if (mp) mp.style.display = 'flex';

    // Heart only for music
    if (mode === 'music') updateHeart(item.id);

    // Notify iframe
    var f = document.getElementById('main-frame');
    if (f && f.contentWindow) {
      try { f.contentWindow.postMessage({type:'nowplaying', songId:item.id}, '*'); } catch(e){}
    }
  }

  /* ── Progress ────────────────────────────────────── */
  function tick(){
    var el = audio(); if (!el) return;
    var c = el.currentTime, d = el.duration||0, p = d>0 ? c/d*100 : 0;
    var ff=$id('fp-fill');    if(ff) ff.style.width = p+'%';
    var mp=$id('mini-prog');  if(mp) mp.style.width = p+'%';
    var tc=$id('fp-cur');     if(tc) tc.textContent = fmt(c);
    var td=$id('fp-dur');     if(td) td.textContent = fmt(d);
  }

  /* ════════════════════════════════════════════════
     MUSIC PLAYBACK
  ════════════════════════════════════════════════ */
  function playMusic(newQueue, startIdx){
    queue = newQueue;
    cur   = (typeof startIdx === 'number') ? startIdx : 0;
    setMode('music');
    _playAt(cur);
  }

  function _playAt(idx){
    if (idx < 0 || idx >= queue.length) return;
    cur = idx;
    var song = queue[idx];
    var el   = audio(); if (!el) return;
    el.src = song.stream; el.load();
    el.play().catch(function(e){ console.warn('[Cumu]',e); });
    setPlayIcons(true);
    updateUI(song);
    document.title = song.title + ' – Cumu';
    var art = $id('fp-art');
    if (art){
      if (mode === 'mixtape') { setCassetteSpinning(true); }
      else { art.classList.remove('playing'); requestAnimationFrame(function(){ art.classList.add('playing'); }); }
    }
  }

  /* ════════════════════════════════════════════════
     AUDIOBOOK PLAYBACK
  ════════════════════════════════════════════════ */
  function playAudiobook(ab){
    curAb = ab;
    setMode('audiobook');
    var el = audio(); if (!el) return;
    el.src = ab.stream; el.load();
    el.play().catch(function(e){ console.warn('[Cumu]',e); });
    setPlayIcons(true);
    updateUI(ab);
    document.title = ab.title + ' – Cumu';
    var art = $id('fp-art');
    if (art){ art.classList.remove('playing'); requestAnimationFrame(function(){ art.classList.add('playing'); }); }
  }

  /* ════════════════════════════════════════════════
     MIXTAPE PLAYBACK
  ════════════════════════════════════════════════ */
  function playMixtape(newQueue, startIdx, mixtapeInfo){
    queue      = newQueue;
    cur        = typeof startIdx === 'number' ? startIdx : 0;
    curMixtape = mixtapeInfo || null;
    setMode('mixtape');
    _playAt(cur);
  }

    /* ── Toggle play/pause ──────────────────────────── */
  function togglePlay(){
    var el = audio(); if (!el) return;
    if (mode === 'music' && cur === -1 && queue.length){ _playAt(0); return; }
    if (el.paused){ el.play(); setPlayIcons(true); }
    else          { el.pause(); setPlayIcons(false); }
  }

  /* ── Prev / Next (music) or ±15s (audiobook) ─────── */
  function prevOrBack15(){
    if (mode === 'audiobook'){
      var el=audio(); if(el) el.currentTime = Math.max(0, el.currentTime-15);
    } else {
      var el2=audio();
      if (el2 && el2.currentTime > 3){ el2.currentTime=0; return; }
      if (cur > 0) _playAt(cur-1);
    }
  }
  function nextOrFwd15(){
    if (mode === 'audiobook'){
      var el=audio(); if(el && el.duration) el.currentTime = Math.min(el.duration, el.currentTime+15);
    } else {
      if (cur < queue.length-1) _playAt(cur+1);
    }
  }

  /* ════════════════════════════════════════════════
     FULLSCREEN
  ════════════════════════════════════════════════ */
  var _touchStartY=0, _touchMoved=false;

  function openFP(){  var f=$id('fp'); if(f){ f.classList.add('open');  document.body.style.overflow='hidden'; } }
  function closeFP(){ var f=$id('fp'); if(f){ f.classList.remove('open'); document.body.style.overflow=''; } }

  /* ════════════════════════════════════════════════
     SHEETS
  ════════════════════════════════════════════════ */
  function openSongSheet(data){
    sheetSong = data;
    var sc=$id('ss-cover');
    if(sc) sc.innerHTML = data.cover
      ? '<img src="'+data.cover+'" alt="" style="width:100%;height:100%;object-fit:cover;display:block">'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    var st=$id('ss-title'),sa=$id('ss-artist');
    if(st) st.textContent=data.title||'';
    if(sa) sa.textContent=data.artist||'';
    var sal=$id('ss-artist-link');
    if(sal) sal.style.display = (data.artistId && data.artistId!=='0') ? 'flex' : 'none';
    var sed=$id('ss-edit-link');
    if(sed) sed.style.display = window.CUMU_ADMIN ? 'flex' : 'none';
    var o=$id('song-sheet-overlay'); if(o) o.classList.add('open');
  }
  function closeSongSheet(){ var o=$id('song-sheet-overlay'); if(o) o.classList.remove('open'); sheetSong=null; }

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
          return '<div class="sheet-item" data-pid="'+pl.id+'">'
            +'<div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg></div>'
            +'<div class="sheet-item-name">'+pl.name+'</div></div>';
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

  /* ════════════════════════════════════════════════
     FAVORITES  (music only)
  ════════════════════════════════════════════════ */
  function getFavPlId(cb){
    if(favPlId!==null){ cb(favPlId); return; }
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_or_create_favorites'})})
    .then(function(r){return r.json();}).then(function(d){ if(d.ok){favPlId=d.data.id; cb(favPlId);} }).catch(function(){});
  }
  function updateHeart(songId){
    var btn=$id('fp-heart'); if(!btn||!songId) return;
    fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'is_favorite',song_id:parseInt(songId)})})
    .then(function(r){return r.json()}).then(function(d){ if(d.ok){ btn.innerHTML=d.data.is_fav?IC.HEART_ON:IC.HEART_OFF; btn.dataset.fav=d.data.is_fav?'1':'0'; } }).catch(function(){});
  }
  function toggleFavorite(){
    if(mode!=='music') return;
    if(cur<0||cur>=queue.length) return;
    var songId=queue[cur].id, btn=$id('fp-heart'); if(!btn) return;
    var isFav=btn.dataset.fav==='1';
    getFavPlId(function(pid){
      fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:isFav?'remove_song':'add_song',playlist_id:pid,song_id:parseInt(songId)})})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok){ isFav=!isFav; btn.innerHTML=isFav?IC.HEART_ON:IC.HEART_OFF; btn.dataset.fav=isFav?'1':'0';
          btn.animate([{transform:'scale(1.35)'},{transform:'scale(1)'}],{duration:220,easing:'ease-out'}); }
      });
    });
  }

  /* ════════════════════════════════════════════════
     CASSETTE ART (Mixtape mode)
  ════════════════════════════════════════════════ */
  function buildCassetteHTML(name, count){
    // Truncate for the orange bar label (max 28 chars like original "2x30min")
    var label = name.length > 28 ? name.substring(0,27)+'…' : name;
    // Exact HTML from Uiverse by Praashoo7
    // "2x30min" → mixtape name  |  "90" → song count
    return '<div class="cassette-outer">'
      +'<div style="transform:scale(0.72);transform-origin:top center;display:inline-block">'
      +'<div class="main">'
      +'<div class="card" id="fp-cassette">'
        +'<div class="ups"><div class="screw1">+</div><div class="screw2">+</div></div>'
        +'<div class="card1">'
          +'<div class="line1"></div>'
          +'<div class="line2"></div>'
          +'<div class="yl">'
            +'<div class="roll">'
              +'<div class="s_wheel"></div>'
              +'<div class="tape"></div>'
              +'<div class="e_wheel"></div>'
            +'</div>'
            +'<p class="num">'+count+'</p>'
          +'</div>'
          +'<div class="or"><p class="time">'+label+'</p></div>'
        +'</div>'
        +'<div class="card2_main"><div class="card2">'
          +'<div class="c1"></div>'
          +'<div class="t1"></div>'
          +'<div class="screw5">+</div>'
          +'<div class="t2"></div>'
          +'<div class="c2"></div>'
        +'</div></div>'
        +'<div class="downs"><div class="screw3">+</div><div class="screw4">+</div></div>'
      +'</div>'
      +'</div></div></div>';
  }

  function setCassetteSpinning(playing){
    var c = document.getElementById('fp-cassette');
    if (c) {
      // .cassette-paused sets animation-play-state:paused on wheels
      if (playing) c.classList.remove('cassette-paused');
      else         c.classList.add('cassette-paused');
    }
  }

    /* ════════════════════════════════════════════════
     MIXTAPE SHEET
  ════════════════════════════════════════════════ */
                        function openMxSheet(songId){
    var o=$id('mx-sheet'), list=$id('mx-sheet-list');
    if(!o||!list) return;
    list.innerHTML='<div style="padding:16px;text-align:center;color:var(--tf)">Loading…</div>';
    o.classList.add('open');
    fetch(B()+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'list'})})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok&&d.data.length){
        list.innerHTML=d.data.map(function(mx){
          return '<div class="sheet-item" data-mxid="'+mx.id+'">'
            +'<div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div>'
            +'<div class="sheet-item-name">'+mx.name+'<span style="margin-left:8px;font-size:12px;color:var(--tf)">'+mx.song_count+' songs</span></div>'
            +'</div>';
        }).join('');
        list.querySelectorAll('.sheet-item[data-mxid]').forEach(function(item){
          item.onclick=function(){
            fetch(B()+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_song',mixtape_id:parseInt(item.dataset.mxid),song_id:parseInt(songId)})})
            .then(function(r){return r.json();}).then(function(){ closeMxSheet(); });
          };
        });
      } else {
        list.innerHTML='<div style="padding:20px;text-align:center;color:var(--t2);font-size:14px">No Mixtapes yet.</div>';
      }
    }).catch(function(){ list.innerHTML='<div style="padding:16px;text-align:center;color:var(--tf)">Error</div>'; });
  }

  function closeMxSheet(){ var o=$id('mx-sheet'); if(o) o.classList.remove('open'); }

    /* ════════════════════════════════════════════════
     SWIPE
  ════════════════════════════════════════════════ */
  function bindSwipe(){
    var fp=$id('fp'); if(!fp) return;
    var startY=0, moved=0;
    fp.addEventListener('touchstart',function(e){ startY=e.touches[0].clientY; moved=0; },{passive:true});
    fp.addEventListener('touchmove', function(e){ moved=e.touches[0].clientY-startY; if(moved>0){fp.style.transform='translateY('+moved+'px)';fp.style.transition='none';} },{passive:true});
    fp.addEventListener('touchend',  function(){ fp.style.transition=''; if(moved>120){closeFP();fp.style.transform='';}else{fp.style.transform='';} });
  }

  /* ════════════════════════════════════════════════
     INIT
  ════════════════════════════════════════════════ */
  function init(){
    var el=audio();
    bindSwipe();

    if(el){
      el.addEventListener('timeupdate', tick);
      el.addEventListener('ended', function(){
        setPlayIcons(false);
        if(mode==='music' && cur<queue.length-1) _playAt(cur+1);
      });
      el.addEventListener('pause', function(){ setPlayIcons(false); if(mode==='mixtape')setCassetteSpinning(false); });
      el.addEventListener('play',  function(){ setPlayIcons(true);  if(mode==='mixtape')setCassetteSpinning(true); });
    }

    /* Mini player → open FP (with scroll guard) */
    var mp=$id('mini-player');
    if(mp){
      mp.addEventListener('touchstart',function(e){ _touchStartY=e.touches[0].clientY; _touchMoved=false; },{passive:true});
      mp.addEventListener('touchmove', function(e){ if(Math.abs(e.touches[0].clientY-_touchStartY)>8) _touchMoved=true; },{passive:true});
      mp.addEventListener('click',function(e){ if(_touchMoved)return; if(e.target.closest('.mini-btn,.mini-play'))return; openFP(); });
    }

    /* FP close */
    $id('fp-down')&&$id('fp-down').addEventListener('click',closeFP);

    /* FP play */
    $id('fp-play-btn')&&$id('fp-play-btn').addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });

    /* Prev / Back15 */
    $id('fp-prev')&&$id('fp-prev').addEventListener('click', prevOrBack15);

    /* Next / Fwd15 */
    $id('fp-next')&&$id('fp-next').addEventListener('click', nextOrFwd15);

    /* Mini controls */
    document.querySelectorAll('.mini-play').forEach(function(b){
      b.addEventListener('click',function(e){ e.stopPropagation(); togglePlay(); });
    });
    $id('mini-next')&&$id('mini-next').addEventListener('click',function(e){
      e.stopPropagation();
      if(mode==='audiobook'){ nextOrFwd15(); } else { if(cur<queue.length-1)_playAt(cur+1); }
    });

    /* Seek */
    var fpTrack=$id('fp-track');
    fpTrack&&fpTrack.addEventListener('click',function(e){
      var a=audio(); if(!a||!a.duration) return;
      /* Audiobooks: no seek forward, only allow seek backward */
      var r=fpTrack.getBoundingClientRect();
      var pct=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));
      if(mode==='audiobook' && pct*a.duration > a.currentTime) return; // block fwd seek
      a.currentTime=pct*a.duration;
    });

    /* Artist button */
    $id('fp-artist')&&$id('fp-artist').addEventListener('click',function(){
      if(mode!=='music') return;
      var aid=this.dataset.artistId;
      if(aid&&aid!=='0'){ closeFP(); window.navTo&&window.navTo('pages/artist.php','id='+aid); }
    });

    /* Heart */
    $id('fp-heart')&&$id('fp-heart').addEventListener('click',toggleFavorite);

    /* 3-dot */
    $id('fp-more-btn')&&$id('fp-more-btn').addEventListener('click',function(){
      if(mode==='music' && cur>=0&&cur<queue.length){
        var s=queue[cur];
        openSongSheet({songId:s.id,title:s.title,artist:s.artist,cover:s.cover,artistId:s.artistId});
      }
    });

    /* Song sheet actions */
    // Only show "Add to Mixtape" for publishers/admins
    var ssMx=$id('ss-add-mx');
    if(ssMx){
      if(!window.CUMU_PUBLISHER){ ssMx.style.display='none'; }
      else{
        ssMx.addEventListener('click',function(){
          if(sheetSong){ var sid=sheetSong.songId; closeSongSheet(); openMxSheet(sid); }
        });
      }
    }
    var mxo=$id('mx-sheet');
    mxo&&mxo.addEventListener('click',function(e){ if(e.target===mxo) closeMxSheet(); });
    $id('mx-new-item')&&$id('mx-new-item').addEventListener('click',function(){
      closeMxSheet();
      // Prompt for new mixtape name then add
      var name=prompt('New Mixtape name:');
      if(!name||!name.trim()) return;
      fetch(B()+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create',name:name.trim()})})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok&&sheetSong){
          fetch(B()+'/backend/mixtape_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_song',mixtape_id:d.data.id,song_id:parseInt(sheetSong.songId)})});
        }
      });
    });
    $id('ss-add-pl')&&$id('ss-add-pl').addEventListener('click',function(){
      if(sheetSong){ var sid=sheetSong.songId; closeSongSheet(); openPlSheet(sid); }
    });
    $id('ss-artist-link')&&$id('ss-artist-link').addEventListener('click',function(){
      if(sheetSong&&sheetSong.artistId&&sheetSong.artistId!=='0'){ closeSongSheet(); window.navTo&&window.navTo('pages/artist.php','id='+sheetSong.artistId); }
    });
    $id('ss-edit-link')&&$id('ss-edit-link').addEventListener('click',function(){
      if(sheetSong){ closeSongSheet(); window.navTo&&window.navTo('pages/song_edit.php','id='+sheetSong.songId); }
    });
    var sso=$id('song-sheet-overlay');
    sso&&sso.addEventListener('click',function(e){ if(e.target===sso) closeSongSheet(); });
    var plo=$id('pl-sheet');
    plo&&plo.addEventListener('click',function(e){ if(e.target===plo) closePlSheet(); });
    $id('pl-new-item')&&$id('pl-new-item').addEventListener('click',function(){ closePlSheet(); window.navTo&&window.navTo('pages/library.php'); });

    /* Keyboard */
    document.addEventListener('keydown',function(e){
      if(['INPUT','TEXTAREA','SELECT'].indexOf(e.target.tagName)>=0) return;
      if(e.code==='Space')     { e.preventDefault(); togglePlay(); }
      if(e.code==='ArrowLeft') { prevOrBack15(); }
      if(e.code==='ArrowRight'){ nextOrFwd15();  }
      if(e.code==='Escape')    { closeFP(); closeSongSheet(); closePlSheet(); closeMxSheet(); }
    });

    /* Messages from iframe pages */
    window.addEventListener('message',function(e){
      if(!e.data||typeof e.data!=='object') return;
      var d=e.data;
      if(d.type==='play')           { playMusic(d.queue, d.idx); }
      if(d.type==='play_audiobook') { playAudiobook(d.ab); }
      if(d.type==='play_mixtape')   { playMixtape(d.queue, d.idx, d.mixtape); }
      if(d.type==='openSongSheet')  { openSongSheet(d.data); }
      if(d.type==='setTab')         { window.updateTabActive&&window.updateTabActive(d.page.replace(/\?.*$/,'')); }
      if(d.type==='navigate')       { window.navTo&&window.navTo(d.page, d.query||''); }
    });
  }

  document.addEventListener('DOMContentLoaded', init);

  return {
    receiveQueue:        playMusic,
    playMusic:           playMusic,
    playAudiobook:       playAudiobook,
    playMixtape:         playMixtape,
    openSongSheetPublic: openSongSheet,
    queue: function(){ return queue; }
  };
})();
