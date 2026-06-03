<?php declare(strict_types=1);

/**
 * Membership Request Controller
 * Handles membership upgrade/downgrade requests from mobile app
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/MembershipRequest.php';
require_once __DIR__ . '/../models/User.php';

class MembershipController extends Controller
{
    private MembershipRequest $membershipModel;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->membershipModel = new MembershipRequest();
        $this->userModel = new User();
    }

    /**
     * Submit membership upgrade request (Mobile App)
     * POST /api/membership/request
     */
    public function requestUpgrade(): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            Response::unauthorized('Silakan login terlebih dahulu');
        }

        $data = $this->getRequestBody();

        // Validate reason - make it more flexible
        $reason = trim($data['reason'] ?? '');

        // Fallback: if reason is empty or too short, use default
        if (strlen($reason) < 3) {
            $reason = 'Ingin menjadi member';
        }

        // Limit reason length
        if (strlen($reason) > 500) {
            $reason = substr($reason, 0, 500);
        }

        // Get current user
        $user = $this->userModel->find($userId);
        if (!$user) {
            Response::error('User tidak ditemukan', 404);
        }

        // Check if already a member
        if ($user['role'] === 'member') {
            Response::error('Anda sudah menjadi member', 422);
        }

        // Check if already has pending request
        $pending = $this->membershipModel->getPendingByUser($userId);
        if ($pending) {
            Response::error('Anda sudah memiliki pengajuan yang sedang diproses', 422);
        }

        // Create request
        $requestId = $this->membershipModel->createRequest(
            $userId,
            $reason,
            'upgrade'
        );

        if (!$requestId) {
            Response::error('Gagal membuat pengajuan', 500);
        }

        Response::success([
            'request_id' => $requestId
        ], 'Pengajuan membership berhasil dikirim. Mohon tunggu approval dari admin.');
    }

    /**
     * Get my membership request status (Mobile App)
     * GET /api/membership/status
     */
    public function getMyStatus(): void
    {
        $authUser = $this->requireAuth();
        $userId = $authUser['user_id'];

        // Get user info
        $user = $this->userModel->find($userId);

        // Get pending request if any
        $pendingRequest = $this->membershipModel->getPendingByUser($userId);

        // Get request history
        $history = $this->membershipModel->getUserHistory($userId);

        Response::success([
            'current_role' => $user['role'],
            'has_pending' => !empty($pendingRequest),
            'pending_request' => $pendingRequest ?: null,
            'history' => $history
        ], 'Status membership');
    }

    /**
     * Cancel pending request (Mobile App)
     * POST /api/membership/cancel
     */
    public function cancelRequest(): void
    {
        $authUser = $this->requireAuth();
        $userId = $authUser['user_id'];

        $pending = $this->membershipModel->getPendingByUser($userId);
        if (!$pending) {
            Response::error('Tidak ada pengajuan yang dapat dibatalkan', 404);
        }

        // Delete the pending request (cast to int for safety)
        $this->membershipModel->delete((int) $pending['id']);

        Response::success(null, 'Pengajuan membership berhasil dibatalkan');
    }
}
