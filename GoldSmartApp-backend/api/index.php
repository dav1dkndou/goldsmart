<?php declare(strict_types=1);

/**
 * GoldSmart API - Entry Point
 * Optimized for performance and security
 */

// Start output buffering with Gzip compression if supported
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// Load configurations first (needed for ENVIRONMENT constant)
require_once __DIR__ . '/../config/app.php';

// Configure error reporting based on environment
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Enable OPcache (production optimization)
if (function_exists('opcache_get_status') && ENVIRONMENT === 'production') {
    ini_set('opcache.enable', '1');
    ini_set('opcache.memory_consumption', '128');
    ini_set('opcache.interned_strings_buffer', '8');
    ini_set('opcache.max_accelerated_files', '4000');
}

// CORS Headers (optimized - set once)
// Mobile app sends requests without Origin header, so we use wildcard
// but do NOT set Allow-Credentials (they conflict per spec)
$corsHeaders = [
    'Access-Control-Allow-Origin: *',
    'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With',
    'Access-Control-Max-Age: 86400',
];

foreach ($corsHeaders as $header) {
    header($header);
}

// Handle preflight (early exit)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// Error handling (production-safe)
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => 'Internal Server Error'
    ];

    // Only show details in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $response['error'] = $e->getMessage();
        $response['file'] = $e->getFile();
        $response['line'] = $e->getLine();
        $response['trace'] = explode("\n", $e->getTraceAsString());
    }

    echo json_encode($response);
    exit;
});

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Load core classes (required for all requests)
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JWT.php';

// Lazy load controllers - only load when needed by router
spl_autoload_register(function ($class) {
    $controllerFile = __DIR__ . '/../controllers/' . $class . '.php';
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
    }
});

// Initialize router
$router = new Router();

// Load routes
require_once __DIR__ . '/../routes/api.php';

// Handle 404
$router->notFound(function () {
    Response::notFound('Endpoint tidak ditemukan');
});

// Dispatch router
$router->dispatch();

// Clean output buffer (router already handles response)
ob_end_clean();
