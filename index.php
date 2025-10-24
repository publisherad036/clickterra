<?php
/**
 * AdServer Main Entry Point
 */

require_once 'config/config.php';
require_once 'includes/Auth.php';

if (Auth::isLoggedIn()) {
    $user = Auth::user();
    
    switch ($user['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'advertiser':
            header('Location: advertiser/dashboard.php');
            break;
        case 'publisher':
            header('Location: publisher/dashboard.php');
            break;
        default:
            Auth::logout();
            header('Location: admin/login.php');
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdServer - Video Ad Management Platform</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 1200px;
            width: 100%;
            padding: 20px;
        }
        .hero {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .panel {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            transition: all 0.3s;
        }
        .panel:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
        }
        .panel h2 {
            margin-bottom: 15px;
            color: #333;
            font-size: 28px;
        }
        .panel p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .panel:nth-child(2) {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
            border: 2px solid #f5576c;
        }
        .panel:nth-child(2) .btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .panel:nth-child(3) {
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
            border: 2px solid #00f2fe;
        }
        .panel:nth-child(3) .btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>🎬 AdServer</h1>
            <p>Professional Video Ad Management Platform with VAST & RTB Support</p>
        </div>

        <div class="panels">
            <div class="panel">
                <h2>👨‍💼 Admin</h2>
                <p>Manage users, campaigns, zones, and monitor system performance</p>
                <a href="admin/login.php" class="btn">Admin Panel</a>
            </div>

            <div class="panel">
                <h2>📢 Advertiser</h2>
                <p>Create and manage campaigns with internal VAST and external endpoints</p>
                <a href="advertiser/login.php" class="btn">Advertiser Panel</a>
            </div>

            <div class="panel">
                <h2>📡 Publisher</h2>
                <p>Create zones, generate VAST tags, and access SSP integration</p>
                <a href="publisher/login.php" class="btn">Publisher Panel</a>
            </div>
        </div>
    </div>
</body>
</html>
