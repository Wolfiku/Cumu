<?php
/**
 * CUMU – backend/layout.php
 *
 * Shared HTML partials used by all app pages.
 *
 * Functions:
 *   layoutHead($title)    – <head> tag with CSS link
 *   layoutSidebar($page)  – left navigation sidebar
 *   layoutPlayerBar()     – fixed bottom audio player
 *   layoutFoot()          – closing tags + scripts
 */

// Inline SVG icons used across the layout
function icon(string $name): string
{
    $icons = [

        'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                     <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                     <polyline points="9 22 9 12 15 12 15 22"/>
                   </svg>',

        'music' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 18V5l12-2v13"/>
                      <circle cx="6" cy="18" r="3"/>
                      <circle cx="18" cy="16" r="3"/>
                    </svg>',

        'list' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                     <line x1="8" y1="6"  x2="21" y2="6"/>
                     <line x1="8" y1="12" x2="21" y2="12"/>
                     <line x1="8" y1="18" x2="21" y2="18"/>
                     <line x1="3" y1="6"  x2="3.01" y2="6"/>
                     <line x1="3" y1="12" x2="3.01" y2="12"/>
                     <line x1="3" y1="18" x2="3.01" y2="18"/>
                   </svg>',

        'logout' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                       <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                       <polyline points="16 17 21 12 16 7"/>
                       <line x1="21" y1="12" x2="9" y2="12"/>
                     </svg>',

        'search' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                       <circle cx="11" cy="11" r="8"/>
                       <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                     </svg>',

        'play'  => '<svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
                    </svg>',

        'pause' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <rect x="6" y="4" width="4" height="16" rx="1"/>
                      <rect x="14" y="4" width="4" height="16" rx="1"/>
                    </svg>',

        'skip-prev' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <polygon points="19 20 9 12 19 4 19 20"/>
                          <line x1="5" y1="19" x2="5" y2="5"/>
                        </svg>',

        'skip-next' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <polygon points="5 4 15 12 5 20 5 4"/>
                          <line x1="19" y1="5" x2="19" y2="19"/>
                        </svg>',

        'volume' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                       <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                       <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                       <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                     </svg>',

        'note' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                     <path d="M9 18V5l12-2v13"/>
                     <circle cx="6" cy="18" r="3"/>
                     <circle cx="18" cy="16" r="3"/>
                   </svg>',
    ];

    return $icons[$name] ?? '';
}

/**
 * Outputs the HTML <head> section.
 *
 * @param string $title  Page title
 * @param string $root   Relative path back to project root (default: '..')
 */
function layoutHead(string $title, string $root = '..'): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> – Cumu</title>
      <link rel="stylesheet" href="<?= $root ?>/style.css">
    </head>
    <body>
    <?php
}

/**
 * Outputs the left sidebar navigation.
 *
 * @param string $activePage  One of: 'dashboard', 'library', 'playlists'
 * @param string $root        Relative path back to project root
 */
function layoutSidebar(string $activePage = 'dashboard', string $root = '..'): void
{
    $username  = currentUsername() ?? 'User';
    $initial   = strtoupper(substr($username, 0, 1));
    ?>
    <aside class="sidebar">

      <div class="sidebar-head">
        <div class="sidebar-logo">Cu<span>mu</span></div>
      </div>

      <nav class="sidebar-nav" aria-label="Main navigation">

        <span class="nav-section-label">Library</span>

        <a href="<?= $root ?>/dashboard.php"
           class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
          <?= icon('home') ?>
          <span>Dashboard</span>
        </a>

        <a href="<?= $root ?>/library.php"
           class="nav-link <?= $activePage === 'library' ? 'active' : '' ?>">
          <?= icon('music') ?>
          <span>Music Library</span>
        </a>

        <a href="<?= $root ?>/playlists.php"
           class="nav-link <?= $activePage === 'playlists' ? 'active' : '' ?>">
          <?= icon('list') ?>
          <span>Playlists</span>
        </a>

      </nav>

      <div class="sidebar-foot">
        <div class="user-badge">
          <div class="user-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
          <span class="user-name"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="<?= $root ?>/backend/logout.php"
           class="nav-link"
           title="Sign out"
           style="margin-top:4px">
          <?= icon('logout') ?>
          <span>Sign out</span>
        </a>
      </div>

    </aside>
    <?php
}

/**
 * Outputs the fixed bottom music player bar.
 */
function layoutPlayerBar(): void
{
    ?>
    <!-- Hidden HTML5 audio element -->
    <audio id="audio-player" preload="metadata"></audio>

    <div class="player-bar" role="region" aria-label="Music player">

      <!-- Track info -->
      <div class="player-track">
        <div class="player-cover-placeholder" id="player-cover-wrap">
          <?= icon('note') ?>
          <img id="player-cover-img" class="player-cover" style="display:none" alt="Album cover">
        </div>
        <div class="player-meta">
          <div class="player-song-title"  id="player-song-title">No track selected</div>
          <div class="player-song-artist" id="player-song-artist">–</div>
        </div>
      </div>

      <!-- Playback controls -->
      <div class="player-controls">
        <div class="player-btn-row">

          <button class="ctrl-btn" id="btn-prev" title="Previous" aria-label="Previous track">
            <?= icon('skip-prev') ?>
          </button>

          <button class="ctrl-btn play-pause" id="btn-play-pause" title="Play/Pause" aria-label="Play or pause">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M8 5.14v13.72a1 1 0 0 0 1.5.87l11-6.86a1 1 0 0 0 0-1.74l-11-6.86A1 1 0 0 0 8 5.14z"/>
            </svg>
          </button>

          <button class="ctrl-btn" id="btn-next" title="Next" aria-label="Next track">
            <?= icon('skip-next') ?>
          </button>

        </div>

        <!-- Progress bar -->
        <div class="player-progress">
          <span class="time-label" id="time-current">0:00</span>
          <div class="progress-track" id="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-fill" id="progress-fill"></div>
          </div>
          <span class="time-label right" id="time-total">0:00</span>
        </div>
      </div>

      <!-- Volume control -->
      <div class="player-volume">
        <?= icon('volume') ?>
        <input
          type="range"
          class="volume-slider"
          id="volume-slider"
          min="0"
          max="100"
          value="80"
          aria-label="Volume"
        >
      </div>

    </div>
    <?php
}

/**
 * Outputs the closing </div> (app-wrap), script tags, and </body></html>.
 *
 * @param string $root  Relative path back to project root
 */
function layoutFoot(string $root = '..'): void
{
    ?>
      </div><!-- /.main-content -->
    </div><!-- /.app-wrap -->

    <script src="<?= $root ?>/player.js"></script>
    </body>
    </html>
    <?php
}
