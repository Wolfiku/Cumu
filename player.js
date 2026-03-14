/**
 * CUMU – player.js
 * Queue-based audio player + add-to-playlist UI.
 */
const Player = (() => {
  let queue   = [];
  let current = -1;

  const $ = id => document.getElementById(id);
  const audio = () => $('cumu-audio');
  const fmt = s => isNaN(s)||s<0 ? '0:00' : `${Math.floor(s/60)}:${String(Math.floor(s%60)).padStart(2,'0')}`;

  function setIcon(playing) {
    const btn = $('btn-pp'); if (!btn) return;
    btn.innerHTML = playing
      ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>`
      : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>`;
  }

  function updateMeta(song) {
    if (!song) return;
    const t=$('player-title'),a=$('player-artist'),img=$('player-thumb-img');
    if (t) t.textContent  = song.title  || 'Unknown';
    if (a) a.textContent  = song.artist || '–';
    if (img) { if(song.cover){img.src=song.cover;img.style.display='block';}else{img.style.display='none';} }
  }

  function syncRows(idx) {
    document.querySelectorAll('.song-row').forEach(row => {
      const i = parseInt(row.dataset.index, 10);
      const match = i === idx;
      row.classList.toggle('playing', match);
      const num = row.querySelector('.song-num');
      const ind = row.querySelector('.playing-indicator');
      if (match) {
        if (num) num.style.display = 'none';
        if (!ind) { const el=document.createElement('span');el.className='playing-indicator';el.innerHTML='<span></span><span></span><span></span><span></span>';row.querySelector('td:first-child')?.appendChild(el); }
      } else {
        if (num) num.style.display = '';
        if (ind) ind.remove();
      }
    });
  }

  function playAt(idx) {
    if (idx < 0 || idx >= queue.length) return;
    current = idx;
    const song = queue[idx];
    const el = audio(); if (!el) return;
    el.src = song.stream; el.load();
    el.play().catch(e => console.warn('[Cumu]', e));
    setIcon(true); updateMeta(song); syncRows(idx);
    document.title = `${song.title} – Cumu`;
  }

  function togglePlay() {
    const el = audio(); if (!el) return;
    if (current === -1 && queue.length > 0) { playAt(0); return; }
    if (el.paused) { el.play(); setIcon(true); }
    else           { el.pause(); setIcon(false); }
  }

  function buildQueue() {
    queue = [];
    document.querySelectorAll('.song-row').forEach((row, i) => {
      row.dataset.index = i;
      queue.push({ stream:row.dataset.stream||'', title:row.dataset.title||'', artist:row.dataset.artist||'', album:row.dataset.album||'', cover:row.dataset.cover||'', id:row.dataset.id||'' });
    });
  }

  // ── Add-to-playlist UI ────────────────────────────
  let openPicker = null;

  async function fetchPlaylists() {
    try {
      const r = await fetch(window.CUMU_BASE + '/backend/playlist_api.php?action=list_user');
      if (!r.ok) return [];
      const d = await r.json();
      return d.ok ? d.data : [];
    } catch { return []; }
  }

  function closePicker() {
    if (openPicker) { openPicker.remove(); openPicker = null; }
  }

  function showPicker(btn, songId) {
    closePicker();
    const picker = document.createElement('div');
    picker.className = 'pl-picker';
    picker.innerHTML = '<div class="pl-picker-empty">Loading...</div>';
    btn.parentElement.style.position = 'relative';
    btn.parentElement.appendChild(picker);
    openPicker = picker;

    fetch(window.CUMU_BASE + '/backend/playlist_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list_user' })
    }).then(r => r.json()).then(d => {
      if (!d.ok || !d.data.length) {
        picker.innerHTML = '<div class="pl-picker-empty">No playlists. <a href="' + window.CUMU_BASE + '/pages/playlists.php">Create one</a></div>';
        return;
      }
      picker.innerHTML = d.data.map(pl =>
        `<div class="pl-picker-item" data-pid="${pl.id}">${pl.name}</div>`
      ).join('');
      picker.querySelectorAll('.pl-picker-item').forEach(item => {
        item.onclick = async () => {
          const r2 = await fetch(window.CUMU_BASE + '/backend/playlist_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_song', playlist_id: parseInt(item.dataset.pid), song_id: parseInt(songId) })
          });
          const d2 = await r2.json();
          closePicker();
          if (!d2.ok) alert(d2.error);
        };
      });
    }).catch(() => { picker.innerHTML = '<div class="pl-picker-empty">Error loading playlists.</div>'; });

    setTimeout(() => document.addEventListener('click', closePicker, { once: true }), 50);
  }

  // ── Search filter ─────────────────────────────────
  function bindSearch() {
    const input = document.getElementById('search-input');
    if (!input) return;
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      document.querySelectorAll('.song-row').forEach(row => {
        const m = !q || (row.dataset.title||'').toLowerCase().includes(q) || (row.dataset.artist||'').toLowerCase().includes(q) || (row.dataset.album||'').toLowerCase().includes(q);
        row.style.display = m ? '' : 'none';
      });
    });
  }

  function init() {
    const el = audio(); if (!el) return;
    buildQueue(); bindSearch();

    // Row play buttons + double click
    document.querySelectorAll('.song-play-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        playAt(parseInt(btn.closest('.song-row')?.dataset.index, 10));
      });
    });
    document.querySelectorAll('.song-row').forEach(row => {
      row.addEventListener('dblclick', () => playAt(parseInt(row.dataset.index, 10)));
    });

    // Add-to-playlist buttons
    document.querySelectorAll('.add-pl-btn').forEach(btn => {
      btn.addEventListener('click', e => { e.stopPropagation(); showPicker(btn, btn.dataset.songId); });
    });

    // Audio events
    el.addEventListener('timeupdate', () => {
      const c=el.currentTime, d=el.duration||0, pct=d>0?c/d*100:0;
      const pf=$('progress-fill'); if(pf) pf.style.width=pct+'%';
      const tc=$('time-cur'); if(tc) tc.textContent=fmt(c);
      const td=$('time-dur'); if(td) td.textContent=fmt(d);
    });
    el.addEventListener('ended', () => { setIcon(false); if(current<queue.length-1) playAt(current+1); });
    el.addEventListener('pause', () => setIcon(false));
    el.addEventListener('play',  () => setIcon(true));

    // Controls
    $('btn-pp')?.addEventListener('click', togglePlay);
    $('btn-prev')?.addEventListener('click', () => { const e=audio(); if(e&&e.currentTime>3){e.currentTime=0;return;} if(current>0)playAt(current-1); });
    $('btn-next')?.addEventListener('click', () => { if(current<queue.length-1)playAt(current+1); });
    $('progress-track')?.addEventListener('click', e => {
      const el2=audio(),pt=$('progress-track'); if(!el2||!el2.duration||!pt)return;
      const r=pt.getBoundingClientRect(); el2.currentTime=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width))*el2.duration;
    });
    const vs=$('vol'); if(vs){el.volume=vs.value/100;vs.addEventListener('input',()=>{const e=audio();if(e)e.volume=vs.value/100;});}

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
      if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
      if (e.code === 'Space') { e.preventDefault(); togglePlay(); }
      if (e.code === 'ArrowRight') { const a=audio();if(a)a.currentTime=Math.min((a.duration||0),a.currentTime+10); }
      if (e.code === 'ArrowLeft')  { const a=audio();if(a)a.currentTime=Math.max(0,a.currentTime-10); }
    });
  }

  return { init, playAt, queue: () => queue };
})();

document.addEventListener('DOMContentLoaded', Player.init);
