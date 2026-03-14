/**
 * CUMU – player.js
 * Queue-based audio player: mini bar, fullscreen overlay,
 * add-to-playlist sheet, keyboard shortcuts, swipe to close.
 */
const Cumu = (() => {
  let queue = [], cur = -1;
  const B = () => window.CUMU_BASE || '';
  const fmt = s => isNaN(s)||s<0 ? '0:00' : `${Math.floor(s/60)}:${String(Math.floor(s%60)).padStart(2,'0')}`;

  const SVG_PLAY  = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>`;
  const SVG_PAUSE = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>`;
  const SVG_PLAY_SM  = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>`;
  const SVG_PAUSE_SM = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>`;

  function setIcons(p) {
    document.querySelectorAll('.fp-play').forEach(b => b.innerHTML = p ? SVG_PAUSE : SVG_PLAY);
    document.querySelectorAll('.mini-play').forEach(b => b.innerHTML = p ? SVG_PAUSE_SM : SVG_PLAY_SM);
  }

  function updateUI(song) {
    if (!song) return;
    // Fullscreen
    const fpT = document.getElementById('fp-title');
    const fpA = document.getElementById('fp-artist');
    const fpArt = document.getElementById('fp-art');
    const fpBg  = document.getElementById('fp-bg');
    if (fpT)   fpT.textContent  = song.title  || 'Unknown';
    if (fpA) { fpA.textContent  = song.artist || '–'; fpA.dataset.artistId = song.artistId || ''; }
    if (fpArt) {
      fpArt.innerHTML = song.cover
        ? `<img src="${song.cover}" alt="">`
        : `<svg class="fp-art-ph" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:#6a6a6a"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`;
      if (fpBg) fpBg.style.backgroundImage = song.cover ? `url('${song.cover}')` : '';
    }
    // Mini
    const mT = document.getElementById('mini-title');
    const mA = document.getElementById('mini-artist');
    const mC = document.getElementById('mini-cover');
    if (mT) mT.textContent = song.title  || 'Unknown';
    if (mA) mA.textContent = song.artist || '–';
    if (mC) mC.innerHTML = song.cover
      ? `<img src="${song.cover}" alt="" style="width:100%;height:100%;object-fit:cover">`
      : `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`;
    const mp = document.getElementById('mini-player');
    if (mp) mp.style.display = 'flex';
  }

  function syncRows(idx) {
    document.querySelectorAll('.song-row').forEach(row => {
      const i = parseInt(row.dataset.idx, 10), match = i === idx;
      row.classList.toggle('playing', match);
      const num = row.querySelector('.sr-num,.song-num');
      const ind = row.querySelector('.play-ind');
      if (match) {
        if (num) num.style.visibility = 'hidden';
        if (!ind) {
          const el = document.createElement('span');
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

  function audio() { return document.getElementById('c-audio'); }

  function playAt(idx) {
    if (idx < 0 || idx >= queue.length) return;
    cur = idx;
    const song = queue[idx], el = audio(); if (!el) return;
    el.src = song.stream; el.load();
    el.play().catch(e => console.warn('[Cumu]', e));
    setIcons(true); updateUI(song); syncRows(idx);
    document.title = `${song.title} – Cumu`;
    const art = document.getElementById('fp-art');
    if (art) { art.classList.remove('playing'); requestAnimationFrame(() => art.classList.add('playing')); }
  }

  function togglePlay() {
    const el = audio(); if (!el) return;
    if (cur === -1 && queue.length) { playAt(0); return; }
    if (el.paused) { el.play(); setIcons(true); } else { el.pause(); setIcons(false); }
  }

  function updateProgress() {
    const el = audio(); if (!el) return;
    const c = el.currentTime, d = el.duration || 0, pct = d > 0 ? c/d*100 : 0;
    const pf  = document.getElementById('fp-fill');  if (pf)  pf.style.width  = pct + '%';
    const mp  = document.getElementById('mini-prog'); if (mp)  mp.style.width  = pct + '%';
    const tc  = document.getElementById('fp-cur');   if (tc)  tc.textContent  = fmt(c);
    const td  = document.getElementById('fp-dur');   if (td)  td.textContent  = fmt(d);
  }

  function openFP()  { const fp=document.getElementById('fp'); if(fp){fp.classList.add('open');document.body.style.overflow='hidden';} }
  function closeFP() { const fp=document.getElementById('fp'); if(fp){fp.classList.remove('open');document.body.style.overflow='';} }

  let sheetSongId = null;

  async function openSheet(songId) {
    sheetSongId = songId;
    const overlay = document.getElementById('pl-sheet');
    const list    = document.getElementById('pl-sheet-list');
    if (!overlay||!list) return;
    list.innerHTML = '<div style="padding:16px;color:var(--tf);text-align:center">Loading…</div>';
    overlay.classList.add('open');
    try {
      const r = await fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'list_user'})});
      const d = await r.json();
      if (d.ok && d.data.length) {
        list.innerHTML = d.data.map(pl=>`<div class="sheet-item" data-pid="${pl.id}"><div class="sheet-item-icon">${pl.cover?`<img src="${B()+'/'+pl.cover}" alt="">`: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg>`}</div><div class="sheet-item-name">${pl.name}</div></div>`).join('');
        list.querySelectorAll('.sheet-item').forEach(item => {
          item.onclick = async () => {
            await fetch(B()+'/backend/playlist_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_song',playlist_id:parseInt(item.dataset.pid),song_id:parseInt(sheetSongId)})});
            closeSheet();
          };
        });
      } else {
        list.innerHTML = `<div style="padding:20px;text-align:center;color:var(--t2);font-size:14px">No playlists yet.</div>`;
      }
    } catch { list.innerHTML = '<div style="padding:16px;color:var(--tf);text-align:center">Error</div>'; }
  }

  function closeSheet() { document.getElementById('pl-sheet')?.classList.remove('open'); }

  function buildQueue(rows) {
    queue = [];
    (rows || document.querySelectorAll('.song-row[data-id]')).forEach((row, i) => {
      row.dataset.idx = i;
      queue.push({ id:row.dataset.id||'', stream:row.dataset.stream||'', title:row.dataset.title||'', artist:row.dataset.artist||'', cover:row.dataset.cover||'', artistId:row.dataset.artistId||'' });
    });
  }

  function bindSwipe() {
    const fp = document.getElementById('fp'); if (!fp) return;
    let startY=0, moved=0;
    fp.addEventListener('touchstart', e=>{startY=e.touches[0].clientY;moved=0;},{passive:true});
    fp.addEventListener('touchmove',  e=>{moved=e.touches[0].clientY-startY;if(moved>0){fp.style.transform=`translateY(${moved}px)`;fp.style.transition='none';}},{passive:true});
    fp.addEventListener('touchend',   ()=>{fp.style.transition='';if(moved>100){closeFP();fp.style.transform='';}else{fp.style.transform='';}});
  }

  function init() {
    const el = audio(); if (!el) return;
    buildQueue(); bindSwipe();

    el.addEventListener('timeupdate', updateProgress);
    el.addEventListener('ended',  ()=>{setIcons(false);if(cur<queue.length-1)playAt(cur+1);});
    el.addEventListener('pause',  ()=>setIcons(false));
    el.addEventListener('play',   ()=>setIcons(true));

    document.getElementById('mini-player')?.addEventListener('click', e=>{
      if(e.target.closest('.mini-btn,.mini-play'))return; openFP();
    });
    document.getElementById('fp-down')?.addEventListener('click', closeFP);
    document.querySelectorAll('.fp-play').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();togglePlay();}));
    document.querySelectorAll('.mini-play').forEach(b=>b.addEventListener('click',e=>{e.stopPropagation();togglePlay();}));
    document.getElementById('fp-prev')?.addEventListener('click',()=>{const a=audio();if(a&&a.currentTime>3){a.currentTime=0;return;}if(cur>0)playAt(cur-1);});
    document.getElementById('fp-next')?.addEventListener('click',()=>{if(cur<queue.length-1)playAt(cur+1);});
    document.getElementById('mini-next')?.addEventListener('click',e=>{e.stopPropagation();if(cur<queue.length-1)playAt(cur+1);});

    const fpTrack = document.getElementById('fp-track');
    fpTrack?.addEventListener('click', e=>{
      const a=audio();if(!a||!a.duration)return;
      const r=fpTrack.getBoundingClientRect();a.currentTime=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width))*a.duration;
    });

    const vs = document.getElementById('fp-vol');
    if (vs) { el.volume=vs.value/100; vs.addEventListener('input',()=>{const a=audio();if(a)a.volume=vs.value/100;}); }

    document.getElementById('fp-artist')?.addEventListener('click',()=>{
      const aid=document.getElementById('fp-artist')?.dataset.artistId;
      if(aid&&aid!=='0'){closeFP();location.href=B()+'/pages/artist.php?id='+aid;}
    });

    document.querySelectorAll('.song-row[data-id]').forEach(row=>{
      row.addEventListener('click',e=>{if(e.target.closest('.sr-action'))return;playAt(parseInt(row.dataset.idx,10));});
    });

    document.querySelectorAll('.sr-action[data-song-id]').forEach(btn=>{
      btn.addEventListener('click',e=>{e.stopPropagation();openSheet(btn.dataset.songId);});
    });

    document.getElementById('pl-sheet')?.addEventListener('click',e=>{
      if(e.target===document.getElementById('pl-sheet'))closeSheet();
    });

    document.addEventListener('keydown',e=>{
      if(['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName))return;
      if(e.code==='Space'){e.preventDefault();togglePlay();}
      if(e.code==='ArrowRight'){const a=audio();if(a)a.currentTime=Math.min((a.duration||0),a.currentTime+10);}
      if(e.code==='ArrowLeft'){const a=audio();if(a)a.currentTime=Math.max(0,a.currentTime-10);}
      if(e.code==='Escape'){closeFP();closeSheet();}
    });
  }

  return { init, playAt, buildQueue, queue:()=>queue };
})();

document.addEventListener('DOMContentLoaded', Cumu.init);
  
