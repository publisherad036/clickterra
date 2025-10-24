<?php
/**
 * Tracking Pixel & Event Logger
 * GET /track.php?zone_id=123&event=impression&impression_id=xxx
 */

header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// Serve 1x1 transparent GIF immediately
$pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
echo $pixel;
flush();

// Log impression async (non-blocking)
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Logger.php';

$zone_id = (int)($_GET['zone_id'] ?? 0);
$campaign_id = (int)($_GET['campaign_id'] ?? 0);
$impression_id = $_GET['impression_id'] ?? '';
$event_type = $_GET['event'] ?? 'impression';
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($zone_id && $impression_id) {
    Logger::impression($zone_id, $event_type, [
        'impression_id' => $impression_id,
        'campaign_id' => $campaign_id,
        'user_ip' => $user_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? ''
    ]);
}

?>
