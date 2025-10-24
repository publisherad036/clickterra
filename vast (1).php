<?php
/**
 * VAST Tag Endpoint
 * /vast.php?zone_id=123&impression_id=xxx
 * Direct wrapper ke serve.php dengan VAST response
 */

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, private');
header('Pragma: no-cache');

require_once 'serve.php';

?>