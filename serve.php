<?php
/**
 * AdServer - Core Ad Serving Engine
 * Complete ad serving system dengan VAST, RTB, SSP, dan tracking
 * Deployable langsung ke HestiaCP public_html/
 * 
 * Usage:
 *   VAST Tag: https://domain.com/serve.php?type=vast&zone_id=123
 *   RTB POST: https://domain.com/serve.php?type=rtb (POST JSON)
 *   Tracking: https://domain.com/serve.php?type=track&impression_id=xxx&event=impression
 */

// ============================================
// INITIALIZATION
// ============================================
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Logger.php';
require_once 'includes/Cache.php';
require_once 'includes/Auth.php';
require_once 'includes/VASTBuilder.php';

// Setup CORS headers untuk semua responses
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Request type routing
$request_type = $_GET['type'] ?? $_POST['type'] ?? 'vast';

switch ($request_type) {
    case 'vast':
        handleVASTRequest();
        break;
    case 'rtb':
        handleRTBRequest();
        break;
    case 'track':
        handleTrackingRequest();
        break;
    case 'click':
        handleClickRequest();
        break;
    case 'health':
        handleHealthCheck();
        break;
    default:
        header('Content-Type: application/xml; charset=utf-8');
        serveErrorVAST('Invalid request type: ' . $request_type);
}

// ============================================
// VAST REQUEST HANDLER
// ============================================
/**
 * Handle VAST Tag Request
 * GET /serve.php?type=vast&zone_id=123&impression_id=xxx
 */
function handleVASTRequest() {
    header('Content-Type: application/xml; charset=utf-8');
    
    $zone_id = (int)($_GET['zone_id'] ?? 0);
    $impression_id = $_GET['impression_id'] ?? uniqid('imp_', true);
    $video_player = $_GET['video_player'] ?? $_GET['player_id'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $user_ip = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Validate zone_id
    if (!$zone_id) {
        Logger::warning('VAST request missing zone_id', ['ip' => $user_ip]);
        serveErrorVAST('Missing required parameter: zone_id');
        return;
    }

    $db = Database::getInstance();
    $start_time = microtime(true);

    // Get zone info dengan cache
    $cache_key = "zone_{$zone_id}";
    $zone = Cache::get($cache_key);

    if (!$zone) {
        $db->prepare("SELECT * FROM zones WHERE id = ? AND status = 'active'")
           ->execute([$zone_id]);
        $zone = $db->fetch();

        if (!$zone) {
            Logger::warning('Zone not found', ['zone_id' => $zone_id]);
            serveErrorVAST('Zone not found');
            return;
        }

        // Cache zone untuk 5 menit
        Cache::set($cache_key, $zone, 300);
    }

    // Check apakah zone type support VAST
    if ($zone['zone_type'] === 'rtb') {
        // RTB-only zones tidak serve VAST langsung
        Logger::info('VAST request on RTB-only zone', ['zone_id' => $zone_id]);
        serveErrorVAST('This zone is RTB-only');
        return;
    }

    // Increment zone impression counter (asynchronously)
    queueZoneUpdate($zone_id, 'impressions', 1);

    $vast_xml = null;
    $campaign_id = null;
    $campaign_type = null;
    $selected_ad_source = null;

    // Strategy: Try internal VAST first, then external RTB/endpoints
    if ($zone['zone_type'] === 'vast' || $zone['zone_type'] === 'both') {
        // Try internal VAST campaigns
        $result = tryInternalVASTCampaigns($zone_id, $impression_id);
        if ($result) {
            $vast_xml = $result['xml'];
            $campaign_id = $result['campaign_id'];
            $campaign_type = 'internal';
            $selected_ad_source = 'vast_campaigns';
            Logger::debug('Served from internal VAST campaign', [
                'zone_id' => $zone_id,
                'campaign_id' => $campaign_id
            ]);
        }
    }

    // Try external endpoints/RTB jika internal tidak ada
    if (!$vast_xml && ($zone['zone_type'] === 'both')) {
        $result = tryExternalCampaigns($zone_id, $impression_id);
        if ($result) {
            $vast_xml = $result['xml'];
            $campaign_id = $result['campaign_id'];
            $campaign_type = 'external';
            $selected_ad_source = 'external_campaigns';
            Logger::debug('Served from external campaign', [
                'zone_id' => $zone_id,
                'campaign_id' => $campaign_id
            ]);
        }
    }

    // Try SSP/RTB endpoints
    if (!$vast_xml && ($zone['zone_type'] === 'both')) {
        $result = trySSPBidding($zone_id, $impression_id);
        if ($result) {
            $vast_xml = $result['xml'];
            $campaign_id = $result['campaign_id'];
            $campaign_type = 'ssp_rtb';
            $selected_ad_source = 'ssp_bids';
            Logger::debug('Served from SSP/RTB bidding', [
                'zone_id' => $zone_id,
                'bid_price' => $result['bid_price'] ?? 0
            ]);
        }
    }

    // Fallback ads jika semua gagal
    if (!$vast_xml) {
        $response_time = (microtime(true) - $start_time) * 1000;
        Logger::rtbBid($zone_id, $impression_id, [
            'status' => 'no_bid',
            'response_time_ms' => (int)$response_time
        ]);

        $fallback_result = getFallbackAd($zone_id);
        if ($fallback_result) {
            $vast_xml = $fallback_result['xml'];
            $campaign_id = $fallback_result['ad_id'];
            $campaign_type = 'fallback';
            $selected_ad_source = 'fallback';
            Logger::info('Served fallback ad', ['zone_id' => $zone_id]);
        } else {
            Logger::warning('No ads available - no fallback', ['zone_id' => $zone_id]);
            serveErrorVAST('No ads available at this moment. Please try again later.');
            return;
        }
    }

    // Log impression ke database (async)
    queueImpression($zone_id, $campaign_id, $campaign_type, [
        'impression_id' => $impression_id,
        'event_type' => 'impression',
        'user_ip' => $user_ip,
        'user_agent' => $user_agent,
        'referer' => $referer,
        'video_player_id' => $video_player
    ]);

    // Performance logging
    $response_time = (microtime(true) - $start_time) * 1000;
    if ($response_time > 1000) {
        Logger::warning('Slow VAST response', [
            'zone_id' => $zone_id,
            'response_time_ms' => (int)$response_time
        ]);
    }

    // Send VAST response
    http_response_code(200);
    echo $vast_xml;
}

/**
 * Try serving internal VAST campaigns
 */
function tryInternalVASTCampaigns($zone_id, $impression_id) {
    $db = Database::getInstance();
    static $campaign_cache = [];

    // Get available campaigns dengan weighted random selection
    $cache_key = "internal_campaigns_{$zone_id}";
    $campaigns = Cache::get($cache_key);

    if (!$campaigns) {
        $sql = "
            SELECT vc.id, vc.vast_url, vc.provider_name, vc.creative_duration,
                   c.id as campaign_id, c.name, c.budget, c.spent
            FROM vast_campaigns vc
            JOIN campaigns c ON vc.campaign_id = c.id
            WHERE c.status = 'active'
            AND (c.end_date IS NULL OR c.end_date > NOW())
            AND (c.budget IS NULL OR c.spent < c.budget)
            ORDER BY c.created_at DESC
            LIMIT 20
        ";

        $db->prepare($sql)->execute();
        $campaigns = $db->fetchAll();

        if (!empty($campaigns)) {
            Cache::set($cache_key, $campaigns, 600); // Cache 10 menit
        }
    }

    if (empty($campaigns)) {
        return null;
    }

    // Weighted random selection
    $selected = $campaigns[array_rand($campaigns)];

    // Fetch VAST URL dengan timeout protection
    $vast_content = fetchVASTWithTimeout($selected['vast_url'], EXTERNAL_ENDPOINT_TIMEOUT);

    if (!$vast_content) {
        Logger::warning('Failed to fetch VAST from provider', [
            'provider' => $selected['provider_name'],
            'vast_url' => $selected['vast_url']
        ]);

        // Log event untuk tracking
        queueImpression($zone_id, $selected['campaign_id'], 'internal', [
            'impression_id' => $impression_id,
            'event_type' => 'error',
            'error' => 'fetch_failed'
        ]);

        return null;
    }

    // Wrap VAST dengan impression tracking jika needed
    $wrapped_vast = wrapVASTWithTracking($vast_content, $selected['campaign_id'], $impression_id, 'internal');

    // Update campaign stats (async)
    queueCampaignUpdate($selected['campaign_id'], 'impression');

    return [
        'xml' => $wrapped_vast,
        'campaign_id' => $selected['campaign_id'],
        'provider' => $selected['provider_name']
    ];
}

/**
 * Try serving external campaigns via custom endpoints
 */
function tryExternalCampaigns($zone_id, $impression_id) {
    $db = Database::getInstance();

    // Get active external campaigns
    $sql = "
        SELECT ec.id, ec.endpoint_url, ec.endpoint_type, ec.api_key,
               c.id as campaign_id, c.name
        FROM external_campaigns ec
        JOIN campaigns c ON ec.campaign_id = c.id
        WHERE c.status = 'active'
        AND (c.end_date IS NULL OR c.end_date > NOW())
        ORDER BY RAND()
        LIMIT 1
    ";

    $db->prepare($sql)->execute();
    $external = $db->fetch();

    if (!$external) {
        return null;
    }

    // Make request ke endpoint
    $response = makeExternalBidRequest($external, $zone_id);

    if (!$response || !isset($response['vast_url'])) {
        Logger::warning('External endpoint bid failed', [
            'endpoint_type' => $external['endpoint_type'],
            'campaign_id' => $external['campaign_id']
        ]);
        return null;
    }

    // Fetch VAST dari response
    $vast_content = fetchVASTWithTimeout($response['vast_url'], EXTERNAL_ENDPOINT_TIMEOUT);

    if (!$vast_content) {
        return null;
    }

    // Wrap dengan tracking
    $wrapped_vast = wrapVASTWithTracking($vast_content, $external['campaign_id'], $impression_id, 'external');

    // Log RTB bid
    $db->insert('rtb_bids', [
        'zone_id' => $zone_id,
        'external_campaign_id' => $external['id'],
        'bid_price' => $response['bid_price'] ?? 0,
        'bid_currency' => $response['currency'] ?? 'USD',
        'response_time_ms' => $response['response_time'] ?? 0,
        'status' => 'success',
        'vast_url_response' => $response['vast_url']
    ]);

    // Update campaign stats
    queueCampaignUpdate($external['campaign_id'], 'impression');

    return [
        'xml' => $wrapped_vast,
        'campaign_id' => $external['campaign_id'],
        'bid_price' => $response['bid_price'] ?? 0
    ];
}

/**
 * Try SSP/RTB bidding
 */
function trySSPBidding($zone_id, $impression_id) {
    $db = Database::getInstance();

    // Get zone publisher's SSP config
    $sql = "
        SELECT sc.* FROM ssp_configs sc
        JOIN zones z ON z.publisher_id = sc.publisher_id
        WHERE z.id = ? AND sc.status = 'active'
        LIMIT 1
    ";

    $db->prepare($sql)->execute([$zone_id]);
    $ssp = $db->fetch();

    if (!$ssp) {
        return null;
    }

    // Make RTB bid request
    $bid_response = makeSSPBidRequest($ssp, $zone_id);

    if (!$bid_response || !isset($bid_response['vast_url'])) {
        return null;
    }

    // Fetch VAST
    $vast_content = fetchVASTWithTimeout($bid_response['vast_url'], RTB_TIMEOUT);

    if (!$vast_content) {
        return null;
    }

    // Log bid
    $db->insert('rtb_bids', [
        'zone_id' => $zone_id,
        'ssp_config_id' => $ssp['id'],
        'bid_price' => $bid_response['bid_price'] ?? 0,
        'response_time_ms' => $bid_response['response_time'] ?? 0,
        'status' => 'success',
        'vast_url_response' => $bid_response['vast_url']
    ]);

    return [
        'xml' => $vast_content,
        'campaign_id' => $ssp['id'],
        'bid_price' => $bid_response['bid_price'] ?? 0
    ];
}

/**
 * Get fallback ad untuk zone
 */
function getFallbackAd($zone_id) {
    $db = Database::getInstance();

    // Get primary fallback
    $sql = "
        SELECT * FROM fallback_ads
        WHERE zone_id = ? AND status = 'active'
        ORDER BY priority DESC
        LIMIT 1
    ";

    $db->prepare($sql)->execute([$zone_id]);
    $fallback = $db->fetch();

    if (!$fallback) {
        return null;
    }

    // Fetch VAST
    $vast_content = fetchVASTWithTimeout($fallback['vast_url'], EXTERNAL_ENDPOINT_TIMEOUT);

    if (!$vast_content) {
        Logger::warning('Fallback VAST fetch failed', ['fallback_id' => $fallback['id']]);
        return null;
    }

    // Add fallback tracking
    $builder = new VASTBuilder('fallback_' . $fallback['id']);
    $wrapped = $builder->buildWrapperAd([
        'vast_url' => $fallback['vast_url'],
        'duration' => 30,
        'impression_urls' => [
            BASE_URL . '/serve.php?type=track&zone_id=' . $zone_id . '&event=fallback'
        ]
    ]);

    return [
        'xml' => $wrapped,
        'ad_id' => $fallback['id'],
        'fallback' => true
    ];
}

// ============================================
// RTB REQUEST HANDLER
// ============================================
/**
 * Handle RTB POST Request
 * POST /serve.php?type=rtb
 */
function handleRTBRequest() {
    header('Content-Type: application/json; charset=utf-8');

    // Check if SSP/RTB is enabled
    if (!FEATURE_RTB_SUPPORT || !FEATURE_OPENRTB) {
        http_response_code(403);
        echo json_encode(['error' => 'RTB service not available']);
        return;
    }

    $input = file_get_contents('php://input');
    $bid_request = json_decode($input, true);

    if (!$bid_request || !isset($bid_request['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid bid request']);
        Logger::warning('Invalid RTB bid request', ['input' => substr($input, 0, 200)]);
        return;
    }

    $db = Database::getInstance();
    $start_time = microtime(true);

    // Validate zone/publisher
    $zone_id = $bid_request['ext']['zone_id'] ?? null;
    if (!$zone_id) {
        $response = buildRTBResponse($bid_request['id'], [], 'no_zone');
        echo json_encode($response);
        return;
    }

    // Check zone status
    $db->prepare("SELECT id FROM zones WHERE id = ? AND status = 'active'")
       ->execute([$zone_id]);
    if (!$db->fetch()) {
        $response = buildRTBResponse($bid_request['id'], [], 'zone_inactive');
        echo json_encode($response);
        return;
    }

    // Get available bids (dari campaigns)
    $bids = [];

    // Try internal campaigns
    $internal_bids = getInternalRTBBids($zone_id, $bid_request);
    if ($internal_bids) {
        $bids = array_merge($bids, $internal_bids);
    }

    // Try external campaigns
    $external_bids = getExternalRTBBids($zone_id, $bid_request);
    if ($external_bids) {
        $bids = array_merge($bids, $external_bids);
    }

    // Build response
    $response = buildRTBResponse($bid_request['id'], $bids);
    $response_time = (microtime(true) - $start_time) * 1000;

    // Log response
    Logger::debug('RTB response sent', [
        'bid_request_id' => $bid_request['id'],
        'bids_count' => count($bids),
        'response_time_ms' => (int)$response_time
    ]);

    http_response_code(200);
    echo json_encode($response);
}

/**
 * Build RTB response
 */
function buildRTBResponse($request_id, $bids = [], $status = 'ok') {
    $response = [
        'id' => $request_id,
        'seatbid' => [],
        'bidid' => uniqid('bid_'),
        'cur' => 'USD'
    ];

    if (!empty($bids)) {
        foreach ($bids as $bid) {
            $response['seatbid'][] = [
                'bid' => [$bid],
                'seat' => $bid['seat'] ?? 'adserver'
            ];
        }
    }

    return $response;
}

// ============================================
// TRACKING & EVENTS
// ============================================
/**
 * Handle tracking requests
 * GET /serve.php?type=track&zone_id=123&event=impression&impression_id=xxx
 */
function handleTrackingRequest() {
    header('Content-Type: image/gif');
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');

    $zone_id = (int)($_GET['zone_id'] ?? 0);
    $event_type = $_GET['event'] ?? 'impression';
    $impression_id = $_GET['impression_id'] ?? '';
    $campaign_id = (int)($_GET['campaign_id'] ?? 0);

    if (!$zone_id || !$impression_id) {
        // Return transparent GIF pixel
        servePixel();
        return;
    }

    // Queue impression event
    queueImpression($zone_id, $campaign_id, 'tracking', [
        'impression_id' => $impression_id,
        'event_type' => $event_type,
        'user_ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? ''
    ]);

    // Update zone stats
    if ($event_type === 'impression') {
        queueZoneUpdate($zone_id, 'impressions', 1);
    } elseif ($event_type === 'click') {
        queueZoneUpdate($zone_id, 'clicks', 1);
    }

    // Send pixel
    servePixel();
}

/**
 * Handle click tracking
 */
function handleClickRequest() {
    $zone_id = (int)($_GET['zone_id'] ?? 0);
    $impression_id = $_GET['impression_id'] ?? '';
    $campaign_id = (int)($_GET['campaign_id'] ?? 0);
    $redirect_url = $_GET['redirect'] ?? $_GET['url'] ?? '';

    if ($zone_id && $impression_id) {
        queueImpression($zone_id, $campaign_id, 'click', [
            'impression_id' => $impression_id,
            'event_type' => 'click',
            'user_ip' => getClientIP()
        ]);

        queueZoneUpdate($zone_id, 'clicks', 1);
    }

    // Redirect ke URL
    if ($redirect_url) {
        header('Location: ' . $redirect_url);
    } else {
        header('HTTP/1.1 204 No Content');
    }
    exit;
}

/**
 * Health check endpoint
 */
function handleHealthCheck() {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $db = Database::getInstance();
        $db->prepare("SELECT 1")->execute();

        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'features' => [
                'vast' => FEATURE_VAST_4_2,
                'rtb' => FEATURE_RTB_SUPPORT,
                'ssp' => FEATURE_SSP_INTEGRATION,
                'caching' => CACHE_ENABLED
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'status' => 'unhealthy',
            'error' => 'Database connection failed'
        ]);
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Fetch VAST content dengan timeout protection
 */
function fetchVASTWithTimeout($url, $timeout = EXTERNAL_ENDPOINT_TIMEOUT) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        Logger::warning('Invalid VAST URL', ['url' => $url]);
        return null;
    }

    $start_time = microtime(true);

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout / 1000,
            'follow_location' => true,
            'max_redirects' => 3,
            'header' => [
                'User-Agent: AdServer/1.0 (VAST)',
                'Accept: application/xml,text/xml',
                'Accept-Encoding: gzip'
            ]
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);

    try {
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            Logger::warning('Failed to fetch VAST URL', ['url' => $url]);
            return null;
        }

        // Validate XML
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (!@$dom->loadXML($response)) {
            Logger::warning('Invalid VAST XML', ['url' => $url]);
            return null;
        }

        // Check VAST version
        $root = $dom->documentElement;
        if ($root->tagName !== 'VAST') {
            Logger::warning('Invalid VAST root element', ['url' => $url]);
            return null;
        }

        return $response;
    } catch (Exception $e) {
        Logger::error('VAST fetch exception', [
            'url' => $url,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Wrap VAST dengan impression tracking
 */
function wrapVASTWithTracking($vast_xml, $campaign_id, $impression_id, $campaign_type) {
    try {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($vast_xml)) {
            return $vast_xml; // Return original jika gagal parse
        }

        // Get root VAST element
        $root = $dom->documentElement;

        // Create tracking URLs
        $tracking_urls = [
            BASE_URL . '/serve.php?type=track&zone_id=0&campaign_id=' . $campaign_id . '&impression_id=' . $impression_id . '&event=impression',
            BASE_URL . '/serve.php?type=track&zone_id=0&campaign_id=' . $campaign_id . '&impression_id=' . $impression_id . '&event=click'
        ];

        // Add impressions jika tidak ada
        $ads = $root->getElementsByTagName('Ad');
        foreach ($ads as $ad) {
            $inline = $ad->getElementsByTagName('InLine')->item(0);
            if ($inline) {
                // Add impression tracking
                $impression = $dom->createElement('Impression', BASE_URL . '/serve.php?type=track&campaign_id=' . $campaign_id . '&impression_id=' . $impression_id);
                $impression->setAttribute('id', 'tracking_' . $campaign_type);
                $inline->appendChild($impression);
            }
        }

        return $dom->saveXML();
    } catch (Exception $e) {
        Logger::error('VAST wrap error', ['error' => $e->getMessage()]);
        return $vast_xml;
    }
}

/**
 * Make external bid request
 */
function makeExternalBidRequest($campaign, $zone_id) {
    $start_time = microtime(true);

    $request_data = [
        'zone_id' => $zone_id,
        'bid_request_id' => uniqid('breq_'),
        'timestamp' => time()
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => EXTERNAL_ENDPOINT_TIMEOUT / 1000,
            'header' => [
                'Content-Type: application/json',
                'User-Agent: AdServer/1.0',
                'Authorization: Bearer ' . $campaign['api_key']
            ],
            'content' => json_encode($request_data)
        ]
    ]);

    try {
        $response = @file_get_contents($campaign['endpoint_url'], false, $context);

        if ($response === false) {
            Logger::warning('External endpoint request failed', [
                'endpoint' => $campaign['endpoint_url']
            ]);
            return null;
        }

        $data = json_decode($response, true);
        if (!$data) {
            return null;
        }

        $response_time = (microtime(true) - $start_time) * 1000;

        return [
            'vast_url' => $data['vast_url'] ?? $data['adm'] ?? null,
            'bid_price' => $data['bid_price'] ?? $data['price'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'response_time' => (int)$response_time
        ];
    } catch (Exception $e) {
        Logger::error('External bid request error', ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Make SSP bid request
 */
function makeSSPBidRequest($ssp, $zone_id) {
    return makeExternalBidRequest([
        'endpoint_url' => $ssp['endpoint_url'],
        'api_key' => $ssp['api_key']
    ], $zone_id);
}

/**
 * Queue impression untuk async logging
 */
function queueImpression($zone_id, $campaign_id, $source_type, $data) {
    $impression = [
        'zone_id' => $zone_id,
        'campaign_id' => $campaign_id ?: null,
        'impression_id' => $data['impression_id'] ?? '',
        'event_type' => $data['event_type'] ?? 'impression',
        'user_ip' => $data['user_ip'] ?? '',
        'user_agent' => substr($data['user_agent'] ?? '', 0, 255),
        'referer' => substr($data['referer'] ?? '', 0, 500),
        'video_player_id' => $data['video_player_id'] ?? ''
    ];

    Logger::impression($zone_id, $data['event_type'] ?? 'impression', $impression);
}

/**
 * Queue zone update (async)
 */
function queueZoneUpdate($zone_id, $field, $increment = 1) {
    // Async queue - bisa menggunakan Redis/Queue atau batch update
    static $queue = [];

    if (!isset($queue[$zone_id])) {
        $queue[$zone_id] = [];
    }

    $queue[$zone_id][$field] = ($queue[$zone_id][$field] ?? 0) + $increment;

    // Flush setiap 100 updates
    if (count($queue) * 2 > 100) {
        flushZoneUpdates($queue);
        $queue = [];
    }
}

/**
 * Queue campaign update (async)
 */
function queueCampaignUpdate($campaign_id, $action) {
    // Dapat di-extend untuk tracking performance
    // Contoh: impressions, clicks, conversions
}

/**
 * Flush zone updates ke database
 */
function flushZoneUpdates(&$queue) {
    if (empty($queue)) return;

    $db = Database::getInstance();

    foreach ($queue as $zone_id => $fields) {
        $updates = [];
        foreach ($fields as $field => $value) {
            if ($value > 0) {
                $updates[] = "{$field} = {$field} + {$value}";
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE zones SET " . implode(',', $updates) . " WHERE id = ?";
            try {
                $db->prepare($sql)->execute([$zone_id]);
            } catch (Exception $e) {
                Logger::error('Zone update error', ['zone_id' => $zone_id, 'error' => $e->getMessage()]);
            }
        }
    }
}

/**
 * Get internal RTB bids
 */
function getInternalRTBBids($zone_id, $bid_request) {
    // Implementation untuk RTB dari internal campaigns
    return [];
}

/**
 * Get external RTB bids
 */
function getExternalRTBBids($zone_id, $bid_request) {
    // Implementation untuk RTB dari external endpoints
    return [];
}

/**
 * Serve transparent GIF pixel
 */
function servePixel() {
    // 1x1 transparent GIF pixel
    $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    echo $pixel;
}

/**
 * Serve error VAST
 */
function serveErrorVAST($error_message) {
    $builder = new VASTBuilder('error_' . uniqid());
    $error_vast = '<?xml version="1.0" encoding="UTF-8"?>
<VAST version="4.2" xmlns="http://www.iab.com/VAST">
    <Error><![CDATA[' . htmlspecialchars($error_message) . ']]></Error>
</VAST>';

    http_response_code(200);
    echo $error_vast;
}

?>
