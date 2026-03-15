/**
 * CUMU – player.js  (runs inside iframe pages)
 *
 * Does NOT control audio directly.
 * Sends postMessage to parent shell (app.php) to play songs.
 * Also receives 'nowplaying' messages to highlight the active row.
 */
(function(){

  var shell = window.parent !== window ? window.parent : null;

  function send(msg){
    if(shell) shell.postMessage(msg, '*');
  }

  /* ── Navigate within the shell ─────────────────── */
  window.navigate = function(page, query){
    send({ type:'navigate', page:page, query:query||'' });
  };

  /* ── Build queue from song rows ─────────────────── */
  function buildQueue(rows){
    var queue=[], i=0;
    (rows||document.querySelectorAll('.song-row[data-id]')).forEach(function(row){
      row.dataset.idx=i;
      queue.push({
        id:       row.dataset.id||'',
        stream:   row.dataset.stream||'',
        title:    row.dataset.title||'',
        artist:   row.dataset.artist||'',
        cover:    row.dataset.cover||'',
        artistId: row.dataset.artistId||''
      });
      i++;
    });
    return queue;
  }

  /* ── Highlight currently playing row ─────────────*/
  function highlightRow(songId){
    document.querySelectorAll('.song-row').forEach(function(row){
      var match = (row.dataset.id && String(row.dataset.id)===String(songId));
      row.classList.toggle('playing', match);
      var num=row.querySelector('.sr-num,.song-num');
      var ind=row.querySelector('.play-ind');
      if(match){
        if(num) num.style.visibility='hidden';
        if(!ind){
          var el=document.createElement('span');
          el.className='play-ind';
          el.innerHTML='<span></span><span></span><span></span><span></span>';
          row.appendChild(el);
        }
      } else {
        if(num) num.style.visibility='';
        if(ind) ind.remove();
      }
    });
  }

  /* ── Receive messages from shell ─────────────────*/
  window.addEventListener('message',function(e){
    if(!e.data||typeof e.data!=='object') return;
    if(e.data.type==='nowplaying') highlightRow(e.data.songId);
  });

  /* ── Intercept all nav-link clicks in iframe ─────*/
  // So that clicking nav links navigates the shell, not the iframe
  document.addEventListener('click', function(e){
    var a = e.target.closest('a[href]');
    if(!a) return;
    var href = a.getAttribute('href');
    if(!href || href==='#' || href.startsWith('http')||href.startsWith('//')) return;
    // External link to backend (logout etc) — let it happen
    if(href.indexOf('/backend/')>=0) return;
    // Admin pages — let them open normally
    if(href.indexOf('/pages/admin')>=0||href.indexOf('/pages/upload')>=0||href.indexOf('/pages/indexer')>=0) return;
    // Check if it's a same-origin page link
    var base = (window.CUMU_BASE||'');
    if(href.startsWith(base+'/pages/') || href.startsWith('pages/')){
      e.preventDefault();
      var rel = href.replace(base+'/','').replace(/^\//,'');
      var parts = rel.split('?');
      send({type:'navigate', page:parts[0], query:parts[1]||''});
    }
  });

  /* ── Wire up song rows on DOMContentLoaded ───────*/
  document.addEventListener('DOMContentLoaded',function(){

    // Row click → play via shell
    document.querySelectorAll('.song-row[data-id]').forEach(function(row){
      row.addEventListener('click', function(e){
        if(e.target.closest('.sr-action,.sr-more')) return;
        var idx = parseInt(row.dataset.idx,10);
        var q   = buildQueue();
        send({type:'play', queue:q, idx:idx});
      });
    });

    // 3-dot → open options sheet in shell
    document.querySelectorAll('.sr-more').forEach(function(btn){
      btn.addEventListener('click',function(e){
        e.stopPropagation();
        send({type:'openSongSheet', data:{
          songId:   btn.dataset.songId,
          title:    btn.dataset.title,
          artist:   btn.dataset.artist,
          cover:    btn.dataset.cover,
          artistId: btn.dataset.artistId
        }});
      });
    });

    // Play-all buttons
    document.querySelectorAll('#play-all-btn,.play-all').forEach(function(btn){
      btn.addEventListener('click',function(){
        var rows=document.querySelectorAll('.song-row[data-id]');
        if(rows.length){ send({type:'play', queue:buildQueue(rows), idx:0}); }
      });
    });

    // Search input (stays in iframe, no shell needed)

    // Tell shell which tab is active
    var path = window.location.pathname;
    var page = path.split('/').pop()||'home.php';
    send({type:'setTab', page:'pages/'+page});
  });

})();
