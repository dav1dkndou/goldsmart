<?php declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/config/app.php';

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = trim($path, '/');

// Remove subdirectory if running on localhost
if (strpos($path, 'goldsmart.online') === 0) {
    $path = substr($path, mb_strlen('goldsmart.online'));
    $path = trim($path, '/');
}

// Route: /api/* → API Handler
if (strpos($path, 'api') === 0) {
    require_once __DIR__ . '/api/index.php';
    exit;
}

// Route: /admin/* → Admin Panel
if (strpos($path, 'admin') === 0) {
    require_once __DIR__ . '/admin/index.php';
    exit;
}

// Route: Root access → Show simple landing page
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoldSmart Backend</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/admin/assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/admin/assets/images/favicon-16.png">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
            background: linear-gradient(135deg, #1a1a2e 0%, #0f0f1e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .logo-container {
            margin-bottom: 30px;
        }
        .logo {
            width: 150px;
            height: 150px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(255, 215, 0, 0.3);
        }
        h1 { 
            color: #FFD700;
            font-size: 2.5em;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        p {
            font-size: 1.2em;
            margin: 20px 0 40px;
            color: #ccc;
        }
        .links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        a { 
            color: #FFD700; 
            text-decoration: none; 
            padding: 15px 30px; 
            border: 2px solid #FFD700; 
            border-radius: 8px; 
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        a:hover { 
            background: #FFD700; 
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="<?= BASE_URL ?>/admin/assets/images/logo-goldsmart.png" alt="GoldSmart Logo" class="logo">
    </div>
    <h1>GoldSmart</h1>
    <div class="links">
        <a href="<?= BASE_URL ?>/admin/">Admin Panel</a>
    </div>
</body>
</html><?php
exit;
