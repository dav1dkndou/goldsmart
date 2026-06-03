<?php declare(strict_types=1);

/**
 * Admin Panel Entry Point - Optimized
 * Only accessible by admin users
 */

// Start output buffering for better performance
ob_start();

// Load configurations first (needed for ENVIRONMENT constant)
require_once __DIR__ . '/../config/app.php';

// Environment-aware error reporting
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Session configuration (security enhanced)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

session_start();

// Session timeout (30 minutes)
define('ADMIN_SESSION_TIMEOUT', 1800);

// Check and update session timeout
if (isset($_SESSION['admin_last_activity'])) {
    if (time() - $_SESSION['admin_last_activity'] > ADMIN_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
}
$_SESSION['admin_last_activity'] = time();

// Security headers (optimized array with CDN whitelist)
$securityHeaders = [
    'X-Frame-Options: DENY',
    'X-Content-Type-Options: nosniff',
    'X-XSS-Protection: 1; mode=block',
    'Referrer-Policy: strict-origin-when-cross-origin',
    "Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; img-src 'self' data: https:;"
];

foreach ($securityHeaders as $header) {
    header($header);
}

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Lazy load models (only load when needed)
spl_autoload_register(function ($class) {
    $modelFile = __DIR__ . '/../models/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return;
    }

    $coreFile = __DIR__ . '/../core/' . $class . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
    }
});

// Check if logged in (optimized with strict checks)
function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['admin_role']) &&
        $_SESSION['admin_role'] === 'admin' &&
        isset($_SESSION['admin_last_activity']);
}

// Get current admin (optimized with cache validation)
function getCurrentAdmin(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    // Check cache validity (refresh every 5 minutes)
    $cacheValid = isset($_SESSION['admin_data_cached_at']) &&
        (time() - $_SESSION['admin_data_cached_at']) < 300;

    if ($cacheValid && isset($_SESSION['admin_data']) && is_array($_SESSION['admin_data'])) {
        return $_SESSION['admin_data'];
    }

    // Fetch from database and update cache
    require_once __DIR__ . '/../core/Model.php';
    require_once __DIR__ . '/../models/User.php';

    $userModel = new User();
    $admin = $userModel->find($_SESSION['admin_id']);

    if ($admin && $admin['role'] === 'admin') {
        $_SESSION['admin_data'] = $admin;
        $_SESSION['admin_data_cached_at'] = time();
        return $admin;
    }

    // Invalid admin, logout
    session_unset();
    session_destroy();
    return null;
}

// Require login (optimized with early exit)
function requireLogin(): void
{
    if (isLoggedIn()) {
        return;
    }

    // Store intended page for redirect after login
    if (!isset($_GET['page']) || $_GET['page'] !== 'login') {
        $_SESSION['intended_page'] = $_SERVER['REQUEST_URI'];
    }

    header('Location: ' . BASE_URL . '/admin/?page=login', true, 302);
    exit;
}

// CSRF Token generation and validation
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token']) || (time() - ($_SESSION['csrf_token_time'] ?? 0)) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// Handle routing (optimized with early exits and validation)
$page = $_GET['page'] ?? null;
$action = $_GET['action'] ?? 'index';

// Sanitize page parameter (prevent directory traversal)
if ($page !== null) {
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);
}

// Default page based on login status
if ($page === null || $page === '') {
    $page = isLoggedIn() ? 'dashboard' : 'login';
}

// Public pages (no login required)
$publicPages = ['login', 'logout'];

// Check authentication for non-public pages
if (!in_array($page, $publicPages, true)) {
    requireLogin();

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['session_regenerated_at']) || (time() - $_SESSION['session_regenerated_at']) > 600) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated_at'] = time();
    }
}

// Build page file path
$pageFile = __DIR__ . '/pages/' . $page . '.php';

// Load the requested page (with error handling)
if (file_exists($pageFile)) {
    // Set current page for navigation
    $_SESSION['current_page'] = $page;

    try {
        require_once $pageFile;
    } catch (Throwable $e) {
        // Log error
        error_log('Admin page error: ' . $e->getMessage());

        if (ENVIRONMENT === 'development') {
            echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        } else {
            http_response_code(500);
            echo 'An error occurred. Please try again.';
        }
    }
} else {
    // 404 page
    http_response_code(404);

    if (file_exists(__DIR__ . '/pages/404.php')) {
        include __DIR__ . '/pages/404.php';
    } else {
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>';
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>The requested page does not exist.</p>';
        echo '<a href="' . BASE_URL . '/admin">Go to Dashboard</a>';
        echo '</body></html>';
    }
}

// Clean and flush output buffer
ob_end_flush();
