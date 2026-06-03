<?php declare(strict_types=1);

/**
 * Auth Controller
 * Handles authentication for mobile app (user & member roles only)
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends Controller
{
    private User $userModel;
    private \PDOStatement $referralCheckStmt;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        // Pre-prepare frequently used statement
        $this->referralCheckStmt = $this->db->prepare('SELECT id, role, referral_code FROM users WHERE referral_code = ? LIMIT 1');
    }

    /**
     * Generate unique referral code (cryptographically secure)
     */
    private function generateReferralCode(): string
    {
        do {
            // Generate 8 character alphanumeric code using secure random bytes
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // Check if code already exists (reuse prepared statement)
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE referral_code = ? LIMIT 1');
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn();
        } while ($exists);

        return $code;
    }

    /**
     * Login
     * POST /api/auth/login
     */
    public function login(): void
    {
        $data = $this->getRequestBody();

        // Validate
        $errors = $this->validate($data, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Find user
        $user = $this->userModel->findByEmail($data['email']);

        if (!$user) {
            Response::error('Email atau password salah', 401);
        }

        // Verify password
        if (!$this->userModel->verifyPassword($data['password'], $user['password'])) {
            Response::error('Email atau password salah', 401);
        }

        // Check if user is active
        if (!$user['is_active']) {
            Response::error('Akun Anda tidak aktif. Hubungi admin.', 403);
        }

        // Check role - only user and member allowed for mobile app (strict comparison)
        if (!in_array($user['role'], ALLOWED_MOBILE_ROLES, true)) {
            Response::error('Akun Anda tidak memiliki akses ke aplikasi ini', 403);
        }

        // Check maintenance mode - only admin can bypass (with error handling)
        try {
            require_once __DIR__ . '/../models/Config.php';
            $configModel = new Config();
            $maintenanceMode = $configModel->getValue('maintenance_mode', '0');

            if ($maintenanceMode === '1' && $user['role'] !== 'admin') {
                Response::error('Aplikasi sedang dalam mode maintenance. Silakan coba lagi nanti.', 503);
            }
        } catch (Exception $e) {
            // If configs table doesn't exist or error, skip maintenance check
            // Log error but continue with login
            error_log('Config check error: ' . $e->getMessage());
        }

        // Generate JWT token (optimized array structure)
        $token = JWT::encode([
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // Remove password from response
        unset($user['password']);

        Response::success([
            'token' => $token,
            'user' => $user
        ], 'Login berhasil');
    }

    /**
     * Register
     * POST /api/auth/register
     */
    public function register(): void
    {
        $data = $this->getRequestBody();

        // Validate
        $errors = $this->validate($data, [
            'name' => 'required|min:3|max:100',
            'email' => 'required|email',
            'phone' => 'required|min:8|max:15',
            'password' => 'required|min:6'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Check if email already exists
        $existingUser = $this->userModel->findByEmail($data['email']);
        if ($existingUser) {
            Response::error('Email sudah terdaftar', 422, [
                'email' => ['Email sudah terdaftar']
            ]);
        }

        // Check referral code if provided (reuse prepared statement)
        $referrerId = null;
        if (!empty($data['referral_code'])) {
            $this->referralCheckStmt->execute([$data['referral_code']]);
            $referrer = $this->referralCheckStmt->fetch();

            if (!$referrer) {
                Response::error('Kode referral tidak valid', 422, [
                    'referral_code' => ['Kode referral tidak ditemukan']
                ]);
            }

            // Only members can refer new users
            if ($referrer['role'] !== 'member') {
                Response::error('Kode referral tidak valid. Hanya kode dari Member yang bisa digunakan.', 422, [
                    'referral_code' => ['Kode referral harus dari akun Member']
                ]);
            }

            $referrerId = (int) $referrer['id'];
        }

        // Generate unique referral code for new user
        $newReferralCode = $this->generateReferralCode();

        // Create user with default role 'user'
        try {
            $this->db->beginTransaction();

            $userId = $this->userModel->createUser([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'user',  // Default role for new registrations
                'referral_code' => $newReferralCode,
                'referred_by' => $referrerId
            ]);

            // NOTE: Referral bonus is given when user UPGRADES to member, not on registration
            // See MembershipRequest::approve() for bonus logic

            $this->db->commit();

            $user = $this->userModel->getProfile($userId);

            // Generate JWT token
            $token = JWT::encode([
                'user_id' => (int) $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            Response::success([
                'token' => $token,
                'user' => $user
            ], 'Registrasi berhasil', 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Registrasi gagal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get current user profile
     * GET /api/auth/me
     */
    public function me(): void
    {
        $authUser = $this->requireAuth();

        $user = $this->userModel->getProfile($authUser['user_id']);

        if (!$user) {
            Response::notFound('User tidak ditemukan');
        }

        Response::success($user);
    }

    /**
     * Logout (revoke current token server-side)
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        // Extract the bearer token from the request
        $headers = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['Authorization']
            ?? '';

        if (preg_match('/Bearer\s+(\S+)/', $headers, $matches)) {
            $token = $matches[1];
            // Revoke token in DB blacklist so it can't be reused
            JWT::revoke($token);
        }

        Response::success(null, 'Logout berhasil');
    }

    /**
     * Refresh token (revokes old token, issues new one)
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        $authUser = $this->requireAuth();

        $user = $this->userModel->getProfile($authUser['user_id']);

        if (!$user) {
            Response::notFound('User tidak ditemukan');
        }

        // Revoke old token
        $headers = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['Authorization']
            ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $headers, $matches)) {
            JWT::revoke($matches[1]);
        }

        // Generate new token
        $token = JWT::encode([
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        Response::success([
            'token' => $token,
            'user' => $user
        ], 'Token refreshed');
    }

    /**
     * Update referral code (for users who didn't input during registration)
     * POST /api/auth/update-referral
     */
    public function updateReferral(): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        // Get current user
        $user = $this->userModel->find($authUser['user_id']);

        if (!$user) {
            Response::notFound('User tidak ditemukan');
        }

        // Check if already has referrer
        if (!empty($user['referred_by'])) {
            Response::error('Anda sudah terdaftar dengan kode referral', 400);
        }

        // Validate referral code
        if (empty($data['referral_code'])) {
            Response::error('Kode referral harus diisi', 422, [
                'referral_code' => ['Kode referral harus diisi']
            ]);
        }

        // Reuse prepared statement
        $this->referralCheckStmt->execute([$data['referral_code']]);
        $referrer = $this->referralCheckStmt->fetch();

        if (!$referrer) {
            Response::error('Kode referral tidak valid', 422, [
                'referral_code' => ['Kode referral tidak ditemukan']
            ]);
        }

        // Only members can refer
        if ($referrer['role'] !== 'member') {
            Response::error('Kode referral tidak valid. Hanya kode dari Member yang bisa digunakan.', 422, [
                'referral_code' => ['Kode referral harus dari akun Member']
            ]);
        }

        // Can't use own referral code (strict comparison)
        if ((int) $referrer['id'] === $authUser['user_id']) {
            Response::error('Tidak bisa menggunakan kode referral sendiri', 422, [
                'referral_code' => ['Tidak bisa menggunakan kode referral sendiri']
            ]);
        }

        try {
            $this->db->beginTransaction();

            // Update referral info (no auto-upgrade, just tracking)
            $this->userModel->update($authUser['user_id'], [
                'referred_by' => (int) $referrer['id']
            ]);

            $this->db->commit();

            // Get updated user profile
            $updatedUser = $this->userModel->getProfile($authUser['user_id']);

            Response::success([
                'user' => $updatedUser
            ], 'Kode referral berhasil ditambahkan');
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Gagal menambahkan kode referral: ' . $e->getMessage(), 500);
        }
    }
}
