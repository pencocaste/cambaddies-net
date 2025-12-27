<?php
/**
 * API functions for Chaturbate
 */

require_once __DIR__ . '/config.php';

/**
 * Fetch rooms from Chaturbate API
 *
 * @param array $params Parameters for the API call
 * @return array|null API response or null on error
 */
function fetchRooms($params = []) {
    // Build query parameters
    $queryParams = [
        'wm' => WM,
        'format' => 'json',
        'limit' => $params['limit'] ?? DEFAULT_LIMIT,
        'offset' => $params['offset'] ?? 0,
    ];

    // Add client IP - this is the key benefit of SSR!
    // We can now send the real user's IP for proper geo-filtering
    $clientIp = getClientIp();
    if ($clientIp) {
        $queryParams['client_ip'] = $clientIp;
    }

    // Add gender filter
    if (!empty($params['gender'])) {
        $queryParams['gender'] = $params['gender'];
    }

    // Add region filter
    if (!empty($params['region'])) {
        $queryParams['region'] = $params['region'];
    }

    // Add tags (max 5)
    if (!empty($params['tags']) && is_array($params['tags'])) {
        $tags = array_slice($params['tags'], 0, 5);
        foreach ($tags as $tag) {
            $queryParams['tag'][] = $tag;
        }
    }

    // Add HD filter
    if (isset($params['hd']) && $params['hd']) {
        $queryParams['hd'] = 'true';
    }

    // Build URL
    $url = API_URL . '?' . http_build_query($queryParams);

    // Make API request
    $response = makeApiRequest($url);

    if ($response === null) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Make HTTP request to API
 *
 * @param string $url URL to fetch
 * @return string|null Response body or null on error
 */
function makeApiRequest($url) {
    // Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'CamBaddies/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            error_log("API Error: $error, HTTP Code: $httpCode");
            return null;
        }

        return $response;
    }

    // Fallback to file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'CamBaddies/1.0',
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("API Error: Failed to fetch $url");
        return null;
    }

    return $response;
}

/**
 * Get client IP address
 *
 * @return string|null Client IP or null
 */
function getClientIp() {
    // Check for proxy headers first
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Standard proxy
        'HTTP_X_REAL_IP',            // Nginx proxy
        'HTTP_CLIENT_IP',            // Some proxies
        'REMOTE_ADDR',               // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];

            // Handle comma-separated IPs (X-Forwarded-For can have multiple)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    // Return REMOTE_ADDR even if it's a private IP (for local testing)
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

/**
 * Format seconds to human readable time
 *
 * @param int $seconds Seconds online
 * @return string Formatted time
 */
function formatOnlineTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($hours > 0) {
        return "{$hours}h {$minutes}m";
    }
    return "{$minutes}m";
}

/**
 * Get gender text from code
 *
 * @param string $gender Gender code (f, m, c, t)
 * @return string Gender text
 */
function getGenderText($gender) {
    $map = [
        'f' => 'Female',
        'm' => 'Male',
        'c' => 'Couple',
        't' => 'Trans',
    ];
    return $map[$gender] ?? $gender;
}
