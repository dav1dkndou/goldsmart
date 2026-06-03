<?php declare(strict_types=1);

/**
 * API Routes
 * GoldSmart Backend
 */

// ==================== DEBUG ROUTE ====================
$router->get('/debug', function () {
    Response::success([
        'api' => 'working',
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Debug endpoint');
});

$router->post('/debug', function () {
    $input = file_get_contents('php://input');
    Response::success([
        'api' => 'working',
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'raw_input' => $input,
        'parsed' => json_decode($input, true),
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Debug POST endpoint');
});

// ==================== ROOT / DOCUMENTATION ====================
$router->get('/', ['ConfigController', 'apiDocs']);
$router->get('', ['ConfigController', 'apiDocs']);

// ==================== AUTH ROUTES ====================
$router->post('/auth/login', ['AuthController', 'login']);
$router->post('/auth/register', ['AuthController', 'register']);
$router->get('/auth/me', ['AuthController', 'me']);
$router->post('/auth/logout', ['AuthController', 'logout']);
$router->post('/auth/refresh', ['AuthController', 'refresh']);
$router->post('/auth/update-referral', ['AuthController', 'updateReferral']);

// ==================== USER ROUTES ====================
$router->get('/users/profile', ['UserController', 'getProfile']);
$router->put('/users/profile', ['UserController', 'updateProfile']);
$router->post('/users/avatar', ['UserController', 'uploadAvatar']);
$router->post('/users/change-password', ['UserController', 'changePassword']);
$router->get('/users/balance', ['UserController', 'getBalance']);
$router->get('/users/referrals', ['UserController', 'getReferrals']);

// ==================== PRODUCT ROUTES ====================
$router->get('/products', ['ProductController', 'index']);
$router->get('/products/featured', ['ProductController', 'featured']);
$router->get('/products/{id}', ['ProductController', 'show']);
$router->get('/categories', ['ProductController', 'categories']);

// ==================== CART ROUTES ====================
$router->get('/cart', ['CartController', 'index']);
$router->get('/cart/summary', ['CartController', 'summary']);
$router->get('/cart/checkout-status', ['CartController', 'checkoutStatus']);
$router->post('/cart', ['CartController', 'add']);
$router->put('/cart/{id}', ['CartController', 'update']);
$router->delete('/cart/{id}', ['CartController', 'remove']);
$router->delete('/cart', ['CartController', 'clear']);
$router->post('/cart/checkout', ['CartController', 'checkout']);

// ==================== TRANSACTION ROUTES ====================
$router->get('/transactions', ['TransactionController', 'index']);
$router->post('/transactions', ['TransactionController', 'create']);
$router->get('/transactions/{id}', ['TransactionController', 'show']);
$router->post('/transactions/{id}/payment-proof', ['TransactionController', 'uploadPaymentProof']);

// ==================== WITHDRAWAL ROUTES ====================
$router->get('/withdrawals', ['WithdrawalController', 'index']);
$router->post('/withdrawals', ['WithdrawalController', 'create']);
$router->get('/withdrawals/{id}', ['WithdrawalController', 'show']);

// ==================== VIDEO ROUTES ====================
$router->get('/videos', ['VideoController', 'index']);
$router->get('/videos/categories', ['VideoController', 'categories']);
$router->post('/videos', ['VideoController', 'create']);
$router->get('/videos/{id}', ['VideoController', 'show']);
$router->post('/videos/{id}/view', ['VideoController', 'incrementView']);
$router->post('/videos/{id}/like', ['VideoController', 'like']);
$router->post('/videos/{id}/claim-reward', ['VideoController', 'claimReward']);
$router->get('/videos/{id}/comments', ['VideoController', 'getComments']);
$router->post('/videos/{id}/comments', ['VideoController', 'addComment']);
$router->delete('/videos/comments/{commentId}', ['VideoController', 'deleteComment']);

// ==================== CONFIG ROUTES ====================
$router->get('/config', ['ConfigController', 'index']);
$router->put('/config', ['ConfigController', 'update']);
$router->post('/config/update', ['ConfigController', 'update']);

// ==================== MEMBERSHIP ROUTES ====================
$router->post('/membership/request', ['MembershipController', 'requestUpgrade']);
$router->get('/membership/status', ['MembershipController', 'getMyStatus']);
$router->post('/membership/cancel', ['MembershipController', 'cancelRequest']);

// ==================== MINING ROUTES ====================
$router->get('/mining/stats', ['MiningController', 'stats']);
$router->get('/mining/plans', ['MiningController', 'plans']);
$router->get('/mining/daily-bonus', ['MiningController', 'dailyBonusStatus']);
$router->post('/mining/daily-bonus', ['MiningController', 'claimDailyBonus']);
$router->post('/mining/activate', ['MiningController', 'activate']);
$router->post('/mining/claim', ['MiningController', 'claim']);
$router->post('/mining/start', ['MiningController', 'start']);
$router->get('/mining/history', ['MiningController', 'history']);
$router->get('/mining/members', ['MiningController', 'activeMembers']);
