/**
 * CUMU – player.js
 * Fixes: artist page no longer stops music (queue persists across navigation).
 * New:   3-dot song sheet with Add to playlist + Go to artist + Edit (admin).
 * Removed: volume slider.
 */
const Cumu = (() => {
  /* ── State ─────────────────────────────────────────── */
  let queue    = [];
  let cur      = -1;
  let sheetSongData = null;

  const B = () => window.CUMU_BASE || '';

  /* ── Audio element ──────────────────────────────────── */
  function audio() { return document.getElementById('c-audio'); }

  /* ── Helpers ────────────────────────────────────────── */
  function fmt(s) {
    if (isNaN(s) || s < 0) return '0:00';
    return Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
  }

  const SVG_PLAY  = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>';
  const SVG_PAUSE = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>';
  const SVG_PLAY_SM  = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>';
  const SVG_PAUSE_SM = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>';

  function setIcons(playing) {
    document.querySelectorAll('.fp-play').forEach(function(b) { b.innerHTML = playing ? SVG_PAUSE : SVG_PLAY; });
    document.querySelectorAll('.mini-play').forEach(function(b) { b.innerHTML = playing ? SVG_PAUSE_SM : SVG_PLAY_SM; });
  }

  /* ── Update player UI ──────────────────────────────── */
  function updateUI(song) {
    if (!song) return;

    // Fullscreen
    var fpT  = document.getElementById('fp-title');
    var fpA  = document.getElementById('fp-artist');
    var fpArt = document.getElementById('fp-art');
    var fpBg  = document.getElementById('fp-bg');

    if (fpT)  fpT.textContent  = song.title  || 'Unknown';
    if (fpA) {
      fpA.textContent           = song.artist || '–';
      fpA.dataset.artistId      = song.artistId || '';
    }
    if (fpArt) {
      if (song.cover) {
        fpArt.innerHTML = '<img src="' + song.cover + '" alt="" style="width:100%;height:100%;object-fit:cover">';
      } else {
        fpArt.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:56px;height:56px;color:#6a6a6a"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
      }
      if (fpBg) fpBg.style.backgroundImage = song.cover ? 'url(\'' + song.cover + '\')' : '';
    }

    // Mini player
    var mT = document.getElementById('mini-title');
    var mA = document.getElementById('mini-artist');
    var mC = document.getElementById('mini-cover');

    if (mT) mT.textContent = song.title  || 'Unknown';
    if (mA) mA.textContent = song.artist || '–';
    if (mC) {
      if (song.cover) {
        mC.innerHTML = '<img src="' + song.cover + '" alt="" style="width:100%;height:100%;object-fit:cover">';
      } else {
        mC.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
      }
    }

    var mp = document.getElementById('mini-player');
    if (mp) mp.style.display = 'flex';
  }

  /* ── Highlight playing row ─────────────────────────── */
  function syncRows(idx) {
    document.querySelectorAll('.song-row').forEach(function(row) {
      var i = parseInt(row.dataset.idx, 10);
      var match = (i === idx);
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

  /* ── Play ──────────────────────────────────────────── */
  function playAt(idx) {
    if (idx < 0 || idx >= queue.length) return;
    cur = idx;
    var song = queue[idx];
    var el   = audio();
    if (!el) return;
    el.src = song.stream;
    el.load();
    el.play().catch(function(e) { console.warn('[Cumu]', e); });
    setIcons(true);
    updateUI(song);
    syncRows(idx);
    document.title = song.title + ' – Cumu';
    var art = document.getElementById('fp-art');
    if (art) {
      art.classList.remove('playing');
      requestAnimationFrame(function() { art.classList.add('playing'); });
    }
  }

  function togglePlay() {
    var el = audio();
    if (!el) return;
    if (cur === -1 && queue.length) { playAt(0); return; }
    if (el.paused) { el.play(); setIcons(true); }
    else           { el.pause(); setIcons(false); }
  }

  /* ── Progress ──────────────────────────────────────── */
  function updateProgress() {
    var el = audio();
    if (!el) return;
    var c = el.currentTime, d = el.duration || 0;
    var pct = d > 0 ? (c / d * 100) : 0;
    var pf  = document.getElementById('fp-fill');   if (pf) pf.style.width  = pct + '%';
    var mp  = document.getElementById('mini-prog'); if (mp) mp.style.width  = pct + '%';
    var tc  = document.getElementById('fp-cur');    if (tc) tc.textContent  = fmt(c);
    var td  = document.getElementById('fp-dur');    if (td) td.textContent  = fmt(d);
  }

  /* ── Fullscreen ────────────────────────────────────── */
  function openFP() {
    var fp = document.getElementById('fp');
    if (fp) { fp.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
  function closeFP() {
    var fp = document.getElementById('fp');
    if (fp) { fp.classList.remove('open'); document.body.style.overflow = ''; }
  }

  /* ── Song options sheet ────────────────────────────── */
  function openSongSheet(data) {
    sheetSongData = data;

    // Populate header
    var ssCover  = document.getElementById('ss-cover');
    var ssTitle  = document.getElementById('ss-title');
    var ssArtist = document.getElementById('ss-artist');
    if (ssCover) {
      ssCover.innerHTML = data.cover
        ? '<img src="' + data.cover + '" alt="" style="width:100%;height:100%;object-fit:cover">'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--tf)"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
    }
    if (ssTitle)  ssTitle.textContent  = data.title  || '';
    if (ssArtist) ssArtist.textContent = data.artist || '';

    // Artist link
    var ssArtistLink = document.getElementById('ss-artist-link');
    if (ssArtistLink && data.artistId && data.artistId !== '0') {
      ssArtistLink.href = B() + '/pages/artist.php?id=' + data.artistId;
      ssArtistLink.style.display = 'flex';
    } else if (ssArtistLink) {
      ssArtistLink.style.display = 'none';
    }

    // Edit link — only for admins
    var ssEdit = document.getElementById('ss-edit-link');
    if (ssEdit) {
      if (window.CUMU_ADMIN === true) {
        ssEdit.href = B() + '/pages/song_edit.php?id=' + data.songId;
        ssEdit.style.display = 'flex';
      } else {
        ssEdit.style.display = 'none';
      }
    }

    var overlay = document.getElementById('song-sheet-overlay');
    if (overlay) overlay.classList.add('open');
  }

  function closeSongSheet() {
    var overlay = document.getElementById('song-sheet-overlay');
    if (overlay) overlay.classList.remove('open');
    sheetSongData = null;
  }

  /* ── Playlist sheet ────────────────────────────────── */
  function openPlSheet(songId) {
    var overlay = document.getElementById('pl-sheet');
    var list    = document.getElementById('pl-sheet-list');
    if (!overlay || !list) return;
    list.innerHTML = '<div style="padding:16px;color:var(--tf);text-align:center">Loading…</div>';
    overlay.classList.add('open');

    fetch(B() + '/backend/playlist_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list_user' })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok && d.data.length) {
        var html = '';
        d.data.forEach(function(pl) {
          html += '<div class="sheet-item" data-pid="' + pl.id + '">' +
            '<div class="sheet-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg></div>' +
            '<div class="sheet-item-name">' + pl.name + '</div>' +
          '</div>';
        });
        list.innerHTML = html;
        list.querySelectorAll('.sheet-item[data-pid]').forEach(function(item) {
          item.addEventListener('click', function() {
            fetch(B() + '/backend/playlist_api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'add_song', playlist_id: parseInt(item.dataset.pid), song_id: parseInt(songId) })
            })
            .then(function(r) { return r.json(); })
            .then(function() { closePlSheet(); });
          });
        });
      } else {
        list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--t2);font-size:14px">No playlists yet.</div>';
      }
    })
    .catch(function() {
      list.innerHTML = '<div style="padding:16px;color:var(--tf);text-align:center">Error</div>';
    });
  }

  function closePlSheet() {
    var overlay = document.getElementById('pl-sheet');
    if (overlay) overlay.classList.remove('open');
  }

  /* ── Build queue from current page rows ────────────── */
  function buildQueue(rows) {
    queue = [];
    var nodeList = rows || document.querySelectorAll('.song-row[data-id]');
    var i = 0;
    nodeList.forEach(function(row) {
      row.dataset.idx = i;
      queue.push({
        id:       row.dataset.id       || '',
        stream:   row.dataset.stream   || '',
        title:    row.dataset.title    || '',
        artist:   row.dataset.artist   || '',
        cover:    row.dataset.cover    || '',
        artistId: row.dataset.artistId || '',
      });
      i++;
    });
  }

  /* ── Swipe down to close fullscreen ────────────────── */
  function bindSwipe() {
    var fp = document.getElementById('fp');
    if (!fp) return;
    var startY = 0, moved = 0;
    fp.addEventListener('touchstart', function(e) {
      startY = e.touches[0].clientY; moved = 0;
    }, { passive: true });
    fp.addEventListener('touchmove', function(e) {
      moved = e.touches[0].clientY - startY;
      if (moved > 0) { fp.style.transform = 'translateY(' + moved + 'px)'; fp.style.transition = 'none'; }
    }, { passive: true });
    fp.addEventListener('touchend', function() {
      fp.style.transition = '';
      if (moved > 100) { closeFP(); fp.style.transform = ''; }
      else { fp.style.transform = ''; }
    });
  }

  /* ── Init ───────────────────────────────────────────── */
  function init() {
    var el = audio();

    buildQueue();
    bindSwipe();

    if (el) {
      el.addEventListener('timeupdate', updateProgress);
      el.addEventListener('ended', function() {
        setIcons(false);
        if (cur < queue.length - 1) playAt(cur + 1);
      });
      el.addEventListener('pause', function() { setIcons(false); });
      el.addEventListener('play',  function() { setIcons(true);  });
    }

    /* Mini player → open fullscreen */
    var miniPl = document.getElementById('mini-player');
    if (miniPl) {
      miniPl.addEventListener('click', function(e) {
        if (e.target.closest('.mini-btn,.mini-play')) return;
        openFP();
      });
    }

    /* Fullscreen close */
    var fpDown = document.getElementById('fp-down');
    if (fpDown) fpDown.addEventListener('click', closeFP);

    /* Fullscreen play buttons */
    document.querySelectorAll('.fp-play').forEach(function(b) {
      b.addEventListener('click', function(e) { e.stopPropagation(); togglePlay(); });
    });

    /* Mini play */
    document.querySelectorAll('.mini-play').forEach(function(b) {
      b.addEventListener('click', function(e) { e.stopPropagation(); togglePlay(); });
    });

    /* Prev / Next */
    var fpPrev = document.getElementById('fp-prev');
    if (fpPrev) fpPrev.addEventListener('click', function() {
      var a = audio();
      if (a && a.currentTime > 3) { a.currentTime = 0; return; }
      if (cur > 0) playAt(cur - 1);
    });
    var fpNext = document.getElementById('fp-next');
    if (fpNext) fpNext.addEventListener('click', function() {
      if (cur < queue.length - 1) playAt(cur + 1);
    });
    var miniNext = document.getElementById('mini-next');
    if (miniNext) miniNext.addEventListener('click', function(e) {
      e.stopPropagation();
      if (cur < queue.length - 1) playAt(cur + 1);
    });

    /* Seek on fullscreen progress track */
    var fpTrack = document.getElementById('fp-track');
    if (fpTrack) {
      fpTrack.addEventListener('click', function(e) {
        var a = audio();
        if (!a || !a.duration) return;
        var r = fpTrack.getBoundingClientRect();
        a.currentTime = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * a.duration;
      });
    }

    /* Fullscreen artist button → navigate without stopping audio */
    var fpArtist = document.getElementById('fp-artist');
    if (fpArtist) {
      fpArtist.addEventListener('click', function() {
        var aid = fpArtist.dataset.artistId;
        if (aid && aid !== '0') {
          closeFP();
          /* Use replace so back button still works, and audio keeps playing */
          location.href = B() + '/pages/artist.php?id=' + aid;
        }
      });
    }

    /* Fullscreen 3-dot → open song sheet */
    var fpMore = document.getElementById('fp-more-btn');
    if (fpMore) {
      fpMore.addEventListener('click', function() {
        if (cur >= 0 && cur < queue.length) {
          var s = queue[cur];
          openSongSheet({ songId: s.id, title: s.title, artist: s.artist, cover: s.cover, artistId: s.artistId });
        }
      });
    }

    /* Row click → play */
    document.querySelectorAll('.song-row[data-id]').forEach(function(row) {
      row.addEventListener('click', function(e) {
        if (e.target.closest('.sr-action,.sr-more')) return;
        playAt(parseInt(row.dataset.idx, 10));
      });
    });

    /* Row 3-dot → song sheet */
    document.querySelectorAll('.sr-more').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        openSongSheet({
          songId:   btn.dataset.songId,
          title:    btn.dataset.title,
          artist:   btn.dataset.artist,
          cover:    btn.dataset.cover,
          artistId: btn.dataset.artistId,
        });
      });
    });

    /* Song sheet: add to playlist */
    var ssAddPl = document.getElementById('ss-add-pl');
    if (ssAddPl) {
      ssAddPl.addEventListener('click', function() {
        if (sheetSongData) {
          closeSongSheet();
          openPlSheet(sheetSongData.songId);
        }
      });
    }

    /* Song sheet overlay click → close */
    var sso = document.getElementById('song-sheet-overlay');
    if (sso) sso.addEventListener('click', function(e) {
      if (e.target === sso) closeSongSheet();
    });

    /* Playlist sheet: new playlist link */
    var plNew = document.getElementById('pl-new-item');
    if (plNew) plNew.addEventListener('click', function() {
      closePlSheet();
      location.href = B() + '/pages/library.php';
    });

    /* Playlist sheet overlay → close */
    var plOverlay = document.getElementById('pl-sheet');
    if (plOverlay) plOverlay.addEventListener('click', function(e) {
      if (e.target === plOverlay) closePlSheet();
    });

    /* Keyboard shortcuts */
    document.addEventListener('keydown', function(e) {
      if (['INPUT','TEXTAREA','SELECT'].indexOf(e.target.tagName) >= 0) return;
      if (e.code === 'Space')      { e.preventDefault(); togglePlay(); }
      if (e.code === 'ArrowRight') { var a=audio(); if(a) a.currentTime=Math.min((a.duration||0),a.currentTime+10); }
      if (e.code === 'ArrowLeft')  { var a=audio(); if(a) a.currentTime=Math.max(0,a.currentTime-10); }
      if (e.code === 'Escape')     { closeFP(); closeSongSheet(); closePlSheet(); }
    });
  }

  return { init: init, playAt: playAt, buildQueue: buildQueue, queue: function() { return queue; } };
})();

document.addEventListener('DOMContentLoaded', Cumu.init);
    
