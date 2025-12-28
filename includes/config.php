<?php
/**
 * Configuration file for CamBaddies
 */

// API Configuration
define('API_URL', 'https://chaturbate.com/api/public/affiliates/onlinerooms/');
define('WM', 'lRUVu');
define('DEFAULT_LIMIT', 36);

// Site Configuration
define('SITE_NAME', 'CamBaddies');
define('SITE_URL', 'https://cambaddies.net');

// Affiliate Links
define('AFFILIATE_TOUR_EMBED', '9oGW');
define('AFFILIATE_TOUR_CHAT', 'LQps');
define('AFFILIATE_CAMPAIGN', 'lRUVu');

// Gender mappings
$GENDER_CONFIG = [
    'f' => [
        'path' => '/girls',
        'title' => 'Female Cams',
        'meta_title' => 'Live Female Cams | Hot Girls Webcams',
        'meta_description' => 'Watch live female cams and chat with hot girls. Free adult webcams featuring the hottest women broadcasting live.',
    ],
    'm' => [
        'path' => '/men',
        'title' => 'Male Cams',
        'meta_title' => 'Live Male Cams | Hot Men Webcams',
        'meta_description' => 'Watch live male cams and chat with hot men. Free adult webcams featuring attractive men broadcasting live.',
    ],
    'c' => [
        'path' => '/couples',
        'title' => 'Couples Cams',
        'meta_title' => 'Live Couples Cams | Couples Webcams',
        'meta_description' => 'Watch live couples cams and enjoy real couples broadcasting. Free adult webcams featuring hot couples.',
    ],
    't' => [
        'path' => '/trans',
        'title' => 'Trans Cams',
        'meta_title' => 'Live Trans Cams | Transgender Webcams',
        'meta_description' => 'Watch live trans cams and chat with transgender models. Free adult webcams featuring trans performers.',
    ],
    'all' => [
        'path' => '/',
        'title' => 'Live Sex Cams',
        'meta_title' => 'CamBadDies.net: Live Sex Cams | Watch Adult Webcams XXX',
        'meta_description' => 'Explore live sex cams and watch real amateur webcam models. Filter by gender, region, and popular tags.',
    ],
];

// Popular tags by gender
$POPULAR_TAGS = [
    'f' => ['latina', 'asian', 'milf', 'teen', 'bigboobs', 'hairy', 'squirt', 'anal', 'ebony', 'mature'],
    'm' => ['muscle', 'bigcock', 'bear', 'uncut', 'twink', 'daddy', 'latino', 'cum', 'bbc', 'fit'],
    'c' => ['anal', 'threesome', 'young', 'bbw', 'interracial', 'latina', 'bisexual', 'feet', 'smoke', 'lesbian'],
    't' => ['bigcock', 'asian', 'latina', 'cum', 'anal', 'bigass', 'slim', 'ebony', 'mistress', 'new'],
];

// Region mappings
$REGIONS = [
    '' => 'All Regions',
    'northamerica' => 'North America',
    'southamerica' => 'South America',
    'europe_russia' => 'Europe/Russia',
    'asia' => 'Asia',
    'other' => 'Other',
];
