/**
 * CUMU – player.js
 * Handles audio playback, progress, and UI sync.
 */

const Player = (() => {
  /* ── Internal State ──────────────────────────────────── */
  let state = {
    currentId:    null,
    currentIndex: -1,
    queue:        [],   // array of song objects { id, title, artist, cover }
    playing:      false,
  };

  /* ── DOM References ──────────────────────────────────── */
  const audio         = document.getElementById('audio-player');
  const btnPlayPause  = document.getElementById('btn-play-pause');
  const btnPrev       = document.getElementById('btn-prev');
  const btnNext       = document.getElementById('btn-next');
  const progressTrack = document.getElementById('progress-track');
  const progressFill  = document.getElementById('progress-fill');
  const timeEl        = document.getElementById('time-current');
  const timeTotalEl   = document.getElementById('time-total');
  const volumeSlider  = document.getElementById('volume-slider');

  const playerTitle   = document.getElementById('player-song-title');
  const playerArtist  = document.getElementById('player-song-artist');
  const playerCover   = document.getElementById('player-cover-img');

  /* ── Utilities ───────────────────────────────────────── */
  function formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  }

  function setPlayIcon(playing) {
    if (!btnPlayPause) return;
    btnPlayPause.innerHTML = playing
      ? /* pause icon */
        `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="currentColor">
           <rect x="6" y="4" width="4" height="16" rx="1"/>
           <rect x="14" y="4" width="4" height="16" rx="1"/>
         </svg>`
      : /* play icon */
        `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="currentColor">
           <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
         </svg>`;
  }

  /* ── Update Player UI ────────────────────────────────── */
  function updatePlayerUI(song) {
    if (!song) return;
    if (playerTitle)  playerTitle.textContent  = song.title  || 'Unknown Title';
    if (playerArtist) playerArtist.textContent = song.artist || 'Unknown Artist';

    // Cover image
    if (playerCover) {
      if (song.cover) {
        playerCover.src   = song.cover;
        playerCover.style.display = 'block';
      } else {
        playerCover.style.display = 'none';
      }
    }
  }

  /* ── Highlight active row in song table ──────────────── */
  function syncTableHighlight(id) {
    document.querySelectorAll('.song-table tbody tr').forEach(row => {
      const rid = row.dataset.songId;
      row.classList.toggle('playing', String(rid) === String(id));

      const numCell = row.querySelector('.song-num');
      const ind     = row.querySelector('.playing-indicator');

      if (String(rid) === String(id)) {
        if (numCell) numCell.style.display = 'none';
        if (!ind) {
          const indicator = document.createElement('span');
          indicator.className = 'playing-indicator';
          indicator.innerHTML = '<span></span><span></span><span></span><span></span>';
          row.querySelector('td:first-child').appendChild(indicator);
        }
      } else {
        if (numCell) numCell.style.display = '';
        if (ind) ind.remove();
      }
    });
  }

  /* ── Load and play a song by ID ──────────────────────── */
  function playSong(songId, songData) {
    state.currentId = songId;

    // Update queue index
    const idx = state.queue.findIndex(s => String(s.id) === String(songId));
    if (idx !== -1) state.currentIndex = idx;

    // Construct stream URL
    const streamUrl = `backend/stream.php?id=${encodeURIComponent(songId)}`;
    audio.src = streamUrl;
    audio.load();

    const playPromise = audio.play();
    if (playPromise !== undefined) {
      playPromise.catch(err => {
        console.warn('Playback blocked:', err);
      });
    }

    state.playing = true;
    setPlayIcon(true);

    if (songData) updatePlayerUI(songData);
    syncTableHighlight(songId);
  }

  /* ── Toggle play / pause ─────────────────────────────── */
  function togglePlayPause() {
    if (!state.currentId) {
      // Play first song in queue if available
      if (state.queue.length > 0) {
        playSong(state.queue[0].id, state.queue[0]);
      }
      return;
    }

    if (audio.paused) {
      audio.play();
      state.playing = true;
      setPlayIcon(true);
    } else {
      audio.pause();
      state.playing = false;
      setPlayIcon(false);
    }
  }

  /* ── Skip prev / next ────────────────────────────────── */
  function skipPrev() {
    if (audio.currentTime > 3) {
      audio.currentTime = 0;
      return;
    }
    if (state.currentIndex > 0) {
      const song = state.queue[state.currentIndex - 1];
      playSong(song.id, song);
    }
  }

  function skipNext() {
    if (state.currentIndex < state.queue.length - 1) {
      const song = state.queue[state.currentIndex + 1];
      playSong(song.id, song);
    }
  }

  /* ── Progress update ─────────────────────────────────── */
  function onTimeUpdate() {
    const cur  = audio.currentTime;
    const dur  = audio.duration || 0;
    const pct  = dur > 0 ? (cur / dur) * 100 : 0;

    if (progressFill) progressFill.style.width = `${pct}%`;
    if (timeEl)       timeEl.textContent = formatTime(cur);
    if (timeTotalEl)  timeTotalEl.textContent = formatTime(dur);
  }

  /* ── Seek on click ───────────────────────────────────── */
  function onProgressClick(e) {
    if (!audio.duration) return;
    const rect = progressTrack.getBoundingClientRect();
    const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    audio.currentTime = pct * audio.duration;
  }

  /* ── Volume ──────────────────────────────────────────── */
  function onVolumeChange() {
    audio.volume = volumeSlider.value / 100;
  }

  /* ── Auto-advance ────────────────────────────────────── */
  function onEnded() {
    state.playing = false;
    setPlayIcon(false);
    skipNext();
  }

  /* ── Build queue from current table ─────────────────── */
  function buildQueueFromTable() {
    state.queue = [];
    document.querySelectorAll('.song-table tbody tr').forEach(row => {
      const id     = row.dataset.songId;
      const title  = row.dataset.songTitle  || '';
      const artist = row.dataset.songArtist || '';
      const cover  = row.dataset.songCover  || '';
      if (id) state.queue.push({ id, title, artist, cover });
    });
  }

  /* ── Search filter ───────────────────────────────────── */
  function bindSearch() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('.song-table tbody tr').forEach(row => {
        const title  = (row.dataset.songTitle  || '').toLowerCase();
        const artist = (row.dataset.songArtist || '').toLowerCase();
        const album  = (row.dataset.songAlbum  || '').toLowerCase();
        const match  = !q || title.includes(q) || artist.includes(q) || album.includes(q);
        row.style.display = match ? '' : 'none';
      });
    });
  }

  /* ── Bind row play buttons ───────────────────────────── */
  function bindRowButtons() {
    document.querySelectorAll('.song-play-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const row    = btn.closest('tr');
        const id     = row.dataset.songId;
        const song   = state.queue.find(s => String(s.id) === String(id));
        playSong(id, song || {
          id,
          title:  row.dataset.songTitle,
          artist: row.dataset.songArtist,
          cover:  row.dataset.songCover,
        });
      });
    });

    // Double-click row to play
    document.querySelectorAll('.song-table tbody tr').forEach(row => {
      row.addEventListener('dblclick', () => {
        const id   = row.dataset.songId;
        const song = state.queue.find(s => String(s.id) === String(id));
        playSong(id, song || {
          id,
          title:  row.dataset.songTitle,
          artist: row.dataset.songArtist,
          cover:  row.dataset.songCover,
        });
      });
    });
  }

  /* ── Init ────────────────────────────────────────────── */
  function init() {
    if (!audio) return;

    buildQueueFromTable();
    bindRowButtons();
    bindSearch();

    // Audio events
    audio.addEventListener('timeupdate', onTimeUpdate);
    audio.addEventListener('ended',      onEnded);

    // Controls
    if (btnPlayPause)  btnPlayPause.addEventListener('click',  togglePlayPause);
    if (btnPrev)       btnPrev.addEventListener('click',       skipPrev);
    if (btnNext)       btnNext.addEventListener('click',       skipNext);
    if (progressTrack) progressTrack.addEventListener('click', onProgressClick);
    if (volumeSlider) {
      volumeSlider.addEventListener('input', onVolumeChange);
      audio.volume = volumeSlider.value / 100;
    }
  }

  return { init, playSong, state };
})();

document.addEventListener('DOMContentLoaded', Player.init);
