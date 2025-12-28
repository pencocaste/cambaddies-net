<?php
/**
 * Template functions for HTML generation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

/**
 * Number of images to load with high priority (above the fold)
 * These will NOT have lazy loading and will have fetchpriority="high"
 */
const LCP_IMAGE_COUNT = 6;

/**
 * Render a single room card
 *
 * @param array $room Room data from API
 * @param int $index Position index of the card (0-based)
 * @return string HTML for room card
 */
function renderRoomCard($room, $index = 0) {
    $username = htmlspecialchars($room['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $imageUrl = htmlspecialchars($room['image_url_360x270'] ?? '', ENT_QUOTES, 'UTF-8');
    $age = intval($room['age'] ?? 0);
    $numUsers = intval($room['num_users'] ?? 0);
    $secondsOnline = intval($room['seconds_online'] ?? 0);
    $isHd = !empty($room['is_hd']);
    $isNew = !empty($room['is_new']);
    $tags = array_slice($room['tags'] ?? [], 0, 3);
    $spokenLanguages = htmlspecialchars($room['spoken_languages'] ?? '', ENT_QUOTES, 'UTF-8');

    $onlineTime = formatOnlineTime($secondsOnline);

    // Build badges HTML
    $badges = '';
    if ($isHd) {
        $badges .= '<span class="badge badge-hd">HD</span>';
    }
    if ($isNew) {
        $badges .= '<span class="badge badge-new">NEW</span>';
    }

    // Build tags HTML
    $tagsHtml = '';
    foreach ($tags as $tag) {
        $tagsHtml .= '<span class="room-tag">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    // Build language HTML
    $languageHtml = '';
    if ($spokenLanguages) {
        $languageHtml = '
            <div class="room-language">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                ' . $spokenLanguages . '
            </div>';
    }

    // Room data as JSON for JavaScript (sanitized to remove external URLs)
    $sanitizedRoom = sanitizeRoomData($room);
    $roomDataJson = htmlspecialchars(json_encode($sanitizedRoom), ENT_QUOTES, 'UTF-8');

    // LCP optimization: First N images should not be lazy loaded and should have high priority
    $isLcpImage = $index < LCP_IMAGE_COUNT;
    $imgAttributes = $isLcpImage
        ? 'fetchpriority="high"'
        : 'loading="lazy"';

    return '
        <div class="room-card fade-in" data-username="' . $username . '" data-room=\'' . $roomDataJson . '\'>
            <div class="room-thumbnail">
                <img src="' . $imageUrl . '" alt="' . $username . ' preview" ' . $imgAttributes . '>
                <div class="room-badges">
                    ' . $badges . '
                </div>
                <div class="room-viewers">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>' . number_format($numUsers) . '</span>
                </div>
            </div>
            <div class="room-details">
                <div class="room-title">' . $username . '</div>
                <div class="room-meta">
                    <span>' . ($age ? $age . ' years' : '') . '</span>
                    <span>' . $onlineTime . ' online</span>
                </div>
                ' . $languageHtml . '
                <div class="room-tags">
                    ' . $tagsHtml . '
                </div>
            </div>
        </div>';
}

/**
 * Render multiple room cards
 *
 * @param array $rooms Array of room data
 * @return string HTML for all room cards
 */
function renderRoomCards($rooms) {
    $html = '';
    foreach ($rooms as $index => $room) {
        $html .= renderRoomCard($room, $index);
    }
    return $html;
}

/**
 * Get page configuration based on path
 *
 * @param string $path URL path
 * @return array Page configuration
 */
function getPageConfig($path) {
    global $GENDER_CONFIG;

    $pathMap = [
        '/' => 'all',
        '/girls' => 'f',
        '/men' => 'm',
        '/couples' => 'c',
        '/trans' => 't',
    ];

    $gender = $pathMap[$path] ?? 'all';
    return [
        'gender' => $gender === 'all' ? '' : $gender,
        'config' => $GENDER_CONFIG[$gender] ?? $GENDER_CONFIG['all'],
    ];
}

/**
 * Render the dropdown filters
 *
 * @param string $gender Current gender filter
 * @return string HTML for filters
 */
function renderFilters($gender = '') {
    global $REGIONS, $POPULAR_TAGS;

    // Region dropdown
    $regionOptions = '';
    foreach ($REGIONS as $value => $label) {
        $selected = $value === '' ? 'selected' : '';
        $regionOptions .= '<div class="dropdown-item ' . $selected . '" data-value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Tags dropdown - based on gender
    $tags = [];
    if ($gender && isset($POPULAR_TAGS[$gender])) {
        $tags = $POPULAR_TAGS[$gender];
    } else {
        // Combine all tags
        $allTags = array_merge(
            $POPULAR_TAGS['f'] ?? [],
            $POPULAR_TAGS['m'] ?? [],
            $POPULAR_TAGS['c'] ?? [],
            $POPULAR_TAGS['t'] ?? []
        );
        $tags = array_slice(array_unique($allTags), 0, 10);
    }

    $tagOptions = '<div class="dropdown-item selected" data-value="">All Tags</div>';
    foreach ($tags as $tag) {
        $tagOptions .= '<div class="dropdown-item" data-value="' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    return '
        <div class="dropdown-filters">
            <div class="dropdown-filter">
                <button class="dropdown-filter-btn" id="region-filter-btn">
                    <span>Region</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" id="region-dropdown">
                    ' . $regionOptions . '
                </div>
            </div>

            <div class="dropdown-filter">
                <button class="dropdown-filter-btn" id="tags-filter-btn">
                    <span>Popular Tags</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" id="tags-dropdown">
                    ' . $tagOptions . '
                </div>
            </div>
        </div>';
}

/**
 * Render the page header (navigation)
 *
 * @param string $currentPath Current URL path
 * @return string HTML for header
 */
function renderHeader($currentPath) {
    $navItems = [
        '/girls/' => 'Girls',
        '/couples/' => 'Couples',
        '/men/' => 'Men',
        '/trans/' => 'Trans',
    ];

    $desktopLinks = '';
    $mobileButtons = '';

    // Normalize currentPath for comparison
    $normalizedPath = rtrim($currentPath, '/');

    foreach ($navItems as $path => $label) {
        $pathWithoutSlash = rtrim($path, '/');
        $activeClass = $normalizedPath === $pathWithoutSlash ? 'active' : '';
        $gender = str_replace('/', '', $pathWithoutSlash);
        $desktopLinks .= '<li><a href="' . $path . '" class="nav-link ' . $activeClass . '" data-gender="' . $gender . '">' . $label . '</a></li>';
        $mobileButtons .= '<a href="' . $path . '" class="mobile-gender-btn ' . $activeClass . '" data-gender="' . $gender . '">' . $label . '</a>';
    }

    return '
    <header>
        <nav>
            <a href="https://cambaddies.net/" class="logo-link">
                <img src="/assets/images/cambaddies-logotype.webp" alt="cambaddies logo" width="198" height="33" class="logo-img">
            </a>

            <!-- Desktop Menu -->
            <ul class="nav-links desktop-menu">
                ' . $desktopLinks . '
            </ul>

            <button class="hamburger">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </nav>

        <!-- Mobile Gender Buttons -->
        <div class="mobile-gender-buttons">
            ' . $mobileButtons . '
        </div>
    </header>';
}

/**
 * Render the footer
 *
 * @return string HTML for footer
 */
function renderFooter() {
    return '
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h2 class="footer-title">Live Cams</h2>
                <p>The best platform to discover and enjoy live cams from around the world. Explore thousands of real-time broadcasts.</p>
            </div>
            <div class="footer-section">
                <span class="footer-title">Legal</span>
                <ul class="footer-links">
                    <li><a href="https://www.cambaddies.net/terms/" rel="nofollow noopener noreferrer" target="_blank">Terms</a></li>
                    <li><a href="https://www.cambaddies.net/2257/" rel="nofollow noopener noreferrer" target="_blank">2257</a></li>
                    <li><a href="https://www.cambaddies.net/law_enforcement/" rel="nofollow noopener noreferrer" target="_blank">Law Enforcement</a></li>
                    <li><a href="https://www.cambaddies.net/privacy/" rel="nofollow noopener noreferrer" target="_blank">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; ' . date('Y') . ' Live Cams. All rights reserved.</p>
        </div>
    </footer>';
}

/**
 * Render the modal
 *
 * @return string HTML for modal
 */
function renderModal() {
    return '
    <div class="modal" id="room-modal">
        <div class="modal-content">
            <button class="close-modal" id="close-modal">&times;</button>
            <div class="modal-header">
                <div class="modal-title" id="modal-title">Room Name</div>
            </div>
            <div class="modal-body">
                <div class="embed-container" id="modal-embed">
                    <!-- Iframe will be inserted here -->
                </div>
                <a href="#" class="start-chat-btn" id="start-chat-btn" rel="nofollow noopener noreferrer" target="_blank">Start Chat with Model</a>
                <div class="room-info" id="modal-info">
                    <!-- Room info will be inserted here -->
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render the main content section
 *
 * @param array $pageConfig Page configuration
 * @param array $rooms Initial rooms data
 * @param int $totalRooms Total number of rooms available
 * @return string HTML for main content
 */
function renderMainContent($pageConfig, $rooms, $totalRooms) {
    $title = htmlspecialchars($pageConfig['config']['title'], ENT_QUOTES, 'UTF-8');
    $gender = $pageConfig['gender'];
    $roomsHtml = renderRoomCards($rooms);
    $filtersHtml = renderFilters($gender);

    $hasMoreRooms = count($rooms) < $totalRooms;
    $loadMoreClass = $hasMoreRooms ? '' : 'hidden';
    $noRoomsClass = count($rooms) === 0 ? '' : 'hidden';

    return '
    <main>
        <section class="page-header">
            <h1 id="page-title">' . $title . '</h1>
        </section>

        <section class="featured">
            <div class="section-header">
                ' . $filtersHtml . '
            </div>
            <div class="loader-container hidden" id="rooms-loader">
                <div class="loader"></div>
            </div>
            <div class="rooms-container" id="rooms-container">
                ' . $roomsHtml . '
            </div>
            <div class="no-rooms-message ' . $noRoomsClass . '" id="no-rooms-message">
                <div class="no-rooms-title">No rooms found</div>
                <p>Try different filters or check back later.</p>
            </div>
        </section>

        <button class="load-more ' . $loadMoreClass . '" id="load-more-btn">Load More</button>

        <div class="scroll-loader hidden" id="scroll-loader">
            <div class="loader"></div>
        </div>
    </main>';
}

/**
 * Render the complete HTML page
 *
 * @param string $path Current URL path
 * @param array $rooms Initial rooms data
 * @param int $totalRooms Total number of rooms available
 * @return string Complete HTML page
 */
function renderPage($path, $rooms, $totalRooms) {
    $pageConfig = getPageConfig($path);
    $config = $pageConfig['config'];

    $metaTitle = htmlspecialchars($config['meta_title'], ENT_QUOTES, 'UTF-8');
    $metaDescription = htmlspecialchars($config['meta_description'], ENT_QUOTES, 'UTF-8');
    $canonicalPath = $path === '/' ? '/' : $path . '/';
    $canonicalUrl = SITE_URL . $canonicalPath;

    // Initial state for JavaScript
    $initialState = json_encode([
        'rooms' => $rooms,
        'totalRooms' => $totalRooms,
        'offset' => DEFAULT_LIMIT,
        'gender' => $pageConfig['gender'],
        'path' => $path,
    ]);

    $headerHtml = renderHeader($path);
    $mainHtml = renderMainContent($pageConfig, $rooms, $totalRooms);
    $footerHtml = renderFooter();
    $modalHtml = renderModal();

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . $metaDescription . '">
    <meta name="keywords" content="live cams, sex cams, webcams, live video chat, adult webcams">
    <title>' . $metaTitle . '</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="250x250" href="/assets/images/cambaddies-favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/cambaddies-favicon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="' . $canonicalUrl . '" id="canonical-url">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="' . $metaTitle . '">
    <meta property="og:description" content="' . $metaDescription . '">
    <meta property="og:type" content="website">
    <meta property="og:url" content="' . $canonicalUrl . '">
    <meta property="og:image" content="/api/placeholder/1200/630">

    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="' . $metaTitle . '">
    <meta name="twitter:description" content="' . $metaDescription . '">
    <meta name="twitter:image" content="/api/placeholder/1200/630">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "' . SITE_NAME . '",
      "url": "' . SITE_URL . '/",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "' . SITE_URL . '/search?q={search_term_string}",
        "query-input": "required name=search_term_string"
      },
      "description": "' . $metaDescription . '"
    }
    </script>

    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    ' . $headerHtml . '
    ' . $mainHtml . '
    ' . $modalHtml . '
    ' . $footerHtml . '

    <script>
        window.__INITIAL_STATE__ = ' . $initialState . ';
    </script>
    <script src="/assets/scripts.js"></script>
</body>
</html>';
}
