<?php declare(strict_types=1);

/**
 * Config Controller
 * Handles app configuration
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Config.php';

class ConfigController extends Controller
{
    private Config $configModel;

    // Static API documentation (pre-computed for performance)
    private const API_DOCUMENTATION = [
        'success' => true,
        'message' => 'GoldSmart API v1.0',
        'endpoints' => [
            'auth' => [
                'POST /auth/login' => 'Login user/member',
                'POST /auth/register' => 'Register new user',
                'GET /auth/me' => 'Get current user (requires token)',
                'POST /auth/logout' => 'Logout (requires token)',
            ],
            'users' => [
                'GET /users/profile' => 'Get user profile (requires token)',
                'PUT /users/profile' => 'Update profile (requires token)',
                'GET /users/balance' => 'Get GC balance (requires token)',
            ],
            'products' => [
                'GET /products' => 'List all products',
                'GET /products/featured' => 'Get featured products',
                'GET /products/{id}' => 'Get product details',
                'GET /categories' => 'List all categories',
            ],
            'transactions' => [
                'GET /transactions' => 'Get my transactions (requires token)',
                'POST /transactions' => 'Create transaction (requires token)',
                'GET /transactions/{id}' => 'Get transaction details (requires token)',
            ],
            'withdrawals' => [
                'GET /withdrawals' => 'Get my withdrawals (requires token)',
                'POST /withdrawals' => 'Request withdrawal (requires token)',
                'GET /withdrawals/{id}' => 'Get withdrawal details (requires token)',
            ],
            'videos' => [
                'GET /videos' => 'List all videos',
                'POST /videos' => 'Upload video (member only, requires token)',
                'GET /videos/{id}' => 'Get video details',
                'POST /videos/{id}/like' => 'Like video (requires token)',
            ],
            'config' => [
                'GET /config' => 'Get app configuration',
            ],
        ],
        'authentication' => [
            'type' => 'Bearer Token',
            'header' => 'Authorization: Bearer {token}',
            'note' => 'Token obtained from /auth/login or /auth/register',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->configModel = new Config();
    }

    /**
     * Get app configuration
     * GET /api/config
     */
    public function index(): void
    {
        $config = $this->configModel->getAppConfig();
        Response::success($config);
    }

    /**
     * Update app configuration
     * PUT /api/config or POST /api/config/update
     */
    public function update(): void
    {
        // Require admin authentication
        $authUser = $this->requireAuth();

        // Only admin can update config
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Hanya admin yang dapat mengubah konfigurasi');
            return;
        }

        $data = $this->getRequestBody();

        // Update each config value (optimized with early validation)
        $updated = [];
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if ($this->configModel->setValue($key, (string) $value)) {
                    $updated[] = $key;
                }
            }
        }

        // Return updated config
        $config = $this->configModel->getAppConfig();
        Response::success($config, 'Konfigurasi berhasil diupdate');
    }

    /**
     * API Documentation
     * GET /api/ or GET /api
     */
    public function apiDocs(): void
    {
        // Use pre-computed constant and add dynamic base_url
        $docs = self::API_DOCUMENTATION;
        $docs['base_url'] = BASE_URL . '/api';

        Response::success($docs);
    }
}
