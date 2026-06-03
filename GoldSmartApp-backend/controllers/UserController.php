<?php declare(strict_types=1);

/**
 * User Controller
 * Handles user profile operations
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Config.php';
require_once __DIR__ . '/../models/ReferralHistory.php';

class UserController extends Controller
{
    private User $userModel;
    private Config $configModel;
    private ReferralHistory $referralHistoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->configModel = new Config();
        $this->referralHistoryModel = new ReferralHistory();
    }

    /**
     * Get user profile
     * GET /api/users/profile
     */
    public function getProfile(): void
    {
        $authUser = $this->requireAuth();

        $user = $this->userModel->getProfile($authUser['user_id']);

        if (!$user) {
            Response::notFound('User tidak ditemukan');
        }

        Response::success($user);
    }

    /**
     * Update user profile
     * PUT /api/users/profile
     */
    public function updateProfile(): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        // Validate with optimized checks
        $errors = [];
        if (isset($data['name']) && mb_strlen($data['name']) < 3) {
            $errors['name'][] = 'Nama minimal 3 karakter';
        }
        if (isset($data['phone']) && mb_strlen($data['phone']) < 10) {
            $errors['phone'][] = 'Nomor HP minimal 10 digit';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $result = $this->userModel->updateProfile($authUser['user_id'], $data);

        if (!$result) {
            Response::error('Gagal mengupdate profil');
        }

        $user = $this->userModel->getProfile($authUser['user_id']);
        Response::success($user, 'Profil berhasil diupdate');
    }

    /**
     * Change password
     * POST /api/users/change-password
     */
    public function changePassword(): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        // Validate
        $errors = $this->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:6'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Verify current password
        $user = $this->userModel->findById($authUser['user_id']);
        if (!$this->userModel->verifyPassword($data['current_password'], $user['password'])) {
            Response::error('Password saat ini salah', 400);
        }

        // Update password
        $result = $this->userModel->changePassword($authUser['user_id'], $data['new_password']);

        if (!$result) {
            Response::error('Gagal mengubah password');
        }

        Response::success(null, 'Password berhasil diubah');
    }

    /**
     * Get GC balance
     * GET /api/users/balance
     */
    public function getBalance(): void
    {
        $authUser = $this->requireAuth();

        $user = $this->userModel->getProfile($authUser['user_id']);

        if (!$user) {
            Response::notFound('User tidak ditemukan');
        }

        Response::success([
            'gc_balance' => (float) $user['gc_balance'],
            'total_gc_earned' => (float) $user['total_gc_earned'],
            'total_gc_withdrawn' => (float) $user['total_gc_withdrawn']
        ]);
    }

    /**
     * Get user's referrals list with complete history
     * GET /api/users/referrals
     */
    public function getReferrals(): void
    {
        $authUser = $this->requireAuth();

        try {
            // Get all users referred by current user with single optimized query
            $stmt = $this->db->prepare('
                SELECT 
                    id,
                    name,
                    email,
                    role,
                    created_at,
                    (role = "member") as is_member
                FROM users 
                WHERE referred_by = ?
                ORDER BY created_at DESC
            ');
            $stmt->execute([$authUser['user_id']]);
            $referrals = $stmt->fetchAll();

            // Get config values
            $referralBonus = (float) $this->configModel->getValue('referral_bonus', '5');
            $commissionBonus = (float) $this->configModel->getValue('commission_bonus', '0.5');

            // Get referral history summary
            $historySummary = $this->referralHistoryModel->getSummaryByReferrer($authUser['user_id']);

            // Get detailed history
            $referralHistory = $this->referralHistoryModel->getByReferrer($authUser['user_id']);

            // Format referrals with earnings info
            $formattedReferrals = [];
            foreach ($referrals as $referral) {
                $isMember = $referral['role'] === 'member';

                // Count commissions for this referred user
                $commissionCount = 0;
                $commissionTotal = 0.0;
                foreach ($referralHistory as $history) {
                    if ((int) $history['referred_id'] === (int) $referral['id'] && $history['type'] === 'commission') {
                        $commissionCount++;
                        $commissionTotal += (float) $history['gc_amount'];
                    }
                }

                $formattedReferrals[] = [
                    'id' => (int) $referral['id'],
                    'name' => $referral['name'],
                    'email' => $referral['email'],
                    'role' => $referral['role'],
                    'account_type' => $isMember ? 'member' : 'user',
                    'signup_bonus' => $isMember ? $referralBonus : 0.0,
                    'commission_count' => $commissionCount,
                    'commission_total' => $commissionTotal,
                    'total_earned' => ($isMember ? $referralBonus : 0.0) + $commissionTotal,
                    'created_at' => $referral['created_at']
                ];
            }

            // Format history for display
            $formattedHistory = [];
            foreach ($referralHistory as $history) {
                $formattedHistory[] = [
                    'id' => (int) $history['id'],
                    'type' => $history['type'],
                    'type_label' => $history['type'] === 'signup_bonus' ? 'Bonus Upgrade Member' : 'Komisi Transaksi',
                    'gc_amount' => (float) $history['gc_amount'],
                    'referred_name' => $history['referred_name'],
                    'referred_email' => $history['referred_email'],
                    'order_number' => $history['order_number'],
                    'description' => $history['description'],
                    'created_at' => $history['created_at']
                ];
            }

            Response::success([
                'referrals' => $formattedReferrals,
                'history' => $formattedHistory,
                'summary' => [
                    'total_referrals' => count($referrals),
                    'total_members' => $historySummary['signup_bonus']['count'],
                    'signup_bonus_earned' => $historySummary['signup_bonus']['total_gc'],
                    'commission_count' => $historySummary['commission']['count'],
                    'commission_earned' => $historySummary['commission']['total_gc'],
                    'total_gc_earned' => $historySummary['total_gc']
                ],
                'config' => [
                    'referral_bonus' => $referralBonus,
                    'commission_bonus' => $commissionBonus
                ]
            ]);
        } catch (Exception $e) {
            error_log('Get referrals error: ' . $e->getMessage());
            Response::error('Gagal mengambil data referral: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload avatar
     * POST /api/users/avatar
     */
    public function uploadAvatar(): void
    {
        $authUser = $this->requireAuth();
        $userId = $authUser['user_id'];

        // Validate file upload
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Tidak ada file yang diupload', 400);
        }

        $file = $_FILES['avatar'];

        // Validate file type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Use strict comparison
        if (!in_array($fileType, ALLOWED_IMAGE_TYPES, true)) {
            Response::error('Format file tidak didukung. Gunakan JPG, PNG, atau WebP', 400);
        }

        // Validate file size using constant
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            Response::error('Ukuran file maksimal 5MB', 400);
        }

        // Use constant for upload directory
        $uploadDir = UPLOAD_PATH . 'avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Delete old avatar if exists (optimized single query)
        $user = $this->userModel->findById($userId);
        if ($user && !empty($user['avatar'])) {
            $oldFile = UPLOAD_PATH . 'avatars/' . basename($user['avatar']);
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        // Move uploaded file with error check
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::error('Gagal mengupload file', 500);
        }

        // Update database
        $avatarPath = 'uploads/avatars/' . $filename;
        $result = $this->userModel->updateProfile($userId, ['avatar' => $avatarPath]);

        if (!$result) {
            // Clean up uploaded file if DB update fails
            @unlink($filepath);
            Response::error('Gagal menyimpan data avatar', 500);
        }

        // Get updated user profile
        $updatedUser = $this->userModel->getProfile($userId);

        // Add full avatar URL using BASE_URL constant
        if (!empty($updatedUser['avatar'])) {
            $updatedUser['avatar_url'] = BASE_URL . '/' . $updatedUser['avatar'];
        }

        Response::success($updatedUser, 'Avatar berhasil diupload');
    }
}
