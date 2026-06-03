<?php declare(strict_types=1);

/**
 * Withdrawal Controller
 * Handles GC withdrawal operations
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Config.php';

class WithdrawalController extends Controller
{
    private Withdrawal $withdrawalModel;
    private User $userModel;
    private Config $configModel;

    public function __construct()
    {
        parent::__construct();
        $this->withdrawalModel = new Withdrawal();
        $this->userModel = new User();
        $this->configModel = new Config();
    }

    /**
     * Format withdrawal data for API response (eliminates duplication)
     */
    private function formatWithdrawal(array $w): array
    {
        return [
            'id' => (int) $w['id'],
            'user_id' => (int) $w['user_id'],
            'userId' => (int) $w['user_id'],
            'gc_amount' => (float) $w['gc_amount'],
            'gcAmount' => (float) $w['gc_amount'],
            'gc_price' => (float) $w['gc_price'],
            'gcPrice' => (float) $w['gc_price'],
            'rupiah_amount' => (float) $w['rupiah_amount'],
            'rupiahAmount' => (float) $w['rupiah_amount'],
            'admin_fee' => (float) $w['admin_fee'],
            'adminFee' => (float) $w['admin_fee'],
            'final_amount' => (float) $w['final_amount'],
            'finalAmount' => (float) $w['final_amount'],
            'bank_name' => $w['bank_name'],
            'bankName' => $w['bank_name'],
            'bank_account_number' => $w['bank_account_number'],
            'accountNumber' => $w['bank_account_number'],
            'bank_account_name' => $w['bank_account_name'],
            'accountHolder' => $w['bank_account_name'],
            'status' => $w['status'],
            'notes' => $w['notes'] ?? null,
            'admin_notes' => $w['admin_notes'] ?? null,
            'adminNotes' => $w['admin_notes'] ?? null,
            'rejection_reason' => $w['rejection_reason'] ?? null,
            'rejectionReason' => $w['rejection_reason'] ?? null,
            'processed_at' => $w['processed_at'] ?? null,
            'processedAt' => $w['processed_at'] ?? null,
            'created_at' => $w['created_at'],
            'createdAt' => $w['created_at']
        ];
    }

    /**
     * Get all withdrawals for current user
     * GET /api/withdrawals
     */
    public function index(): void
    {
        $authUser = $this->requireAuth();

        try {
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: null;
            $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT) ?: 15;

            $result = $this->withdrawalModel->getByUser($authUser['user_id'], $page, $perPage);

            if ($page !== null) {
                $result['data'] = array_map([$this, 'formatWithdrawal'], $result['data']);
                Response::success($result);
            } else {
                $formatted = array_map([$this, 'formatWithdrawal'], $result);
                Response::success($formatted);
            }
        } catch (Exception $e) {
            error_log('Get withdrawals error: ' . $e->getMessage());
            Response::error('Gagal mengambil data withdrawal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single withdrawal
     * GET /api/withdrawals/{id}
     */
    public function show($id): void
    {
        $authUser = $this->requireAuth();

        try {
            $withdrawal = $this->withdrawalModel->findById((int) $id);

            if (!$withdrawal || (int) $withdrawal['user_id'] !== $authUser['user_id']) {
                Response::notFound('Withdrawal tidak ditemukan');
            }

            // Use helper method for consistent formatting
            Response::success($this->formatWithdrawal($withdrawal));
        } catch (Exception $e) {
            error_log('Get withdrawal error: ' . $e->getMessage());
            Response::error('Gagal mengambil data withdrawal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new withdrawal request
     * POST /api/withdrawals
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();

        // Check if user is member
        if ($authUser['role'] !== 'member') {
            Response::forbidden('Hanya member yang dapat melakukan withdrawal');
        }

        $data = $this->getRequestBody();

        // Validate early
        $errors = $this->validate($data, [
            'gc_amount' => 'required|numeric',
            'bank_name' => 'required',
            'bank_account_number' => 'required',
            'bank_account_name' => 'required'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Get user and config
        $user = $this->userModel->findById($authUser['user_id']);
        $config = $this->configModel->getAppConfig();

        $gcAmount = (float) $data['gc_amount'];
        $gcPrice = (float) $config['gc']['current_price'];
        $adminFee = (float) $config['withdrawal']['admin_fee'];
        $minAmount = (float) $config['withdrawal']['min_amount'];

        // Validate amount
        if ($gcAmount < $minAmount) {
            Response::error("Minimal withdrawal adalah {$minAmount} GC", 400);
        }

        if ($gcAmount > (float) $user['gc_balance']) {
            Response::error('Saldo GC tidak mencukupi', 400);
        }

        // Check for pending withdrawal
        if ($this->withdrawalModel->hasPendingWithdrawal($authUser['user_id'])) {
            Response::error('Anda masih memiliki withdrawal yang sedang diproses', 400);
        }

        // Calculate amounts
        $rupiahAmount = $gcAmount * $gcPrice;
        $finalAmount = $rupiahAmount - $adminFee;

        if ($finalAmount <= 0.0) {
            Response::error('Jumlah withdrawal terlalu kecil setelah dipotong admin fee', 400);
        }

        // Create withdrawal (wrapped in DB transaction for atomicity)
        try {
            $this->withdrawalModel->beginTransaction();

            $withdrawalId = $this->withdrawalModel->createWithdrawal([
                'user_id' => $authUser['user_id'],
                'gc_amount' => $gcAmount,
                'gc_price' => $gcPrice,
                'rupiah_amount' => $rupiahAmount,
                'admin_fee' => $adminFee,
                'final_amount' => $finalAmount,
                'bank_name' => $data['bank_name'],
                'bank_account_number' => $data['bank_account_number'],
                'bank_account_name' => $data['bank_account_name'],
                'notes' => $data['notes'] ?? null
            ]);

            // Deduct GC from user balance (atomic operation)
            $deducted = $this->userModel->updateGCBalance($authUser['user_id'], $gcAmount, 'subtract');
            if (!$deducted) {
                $this->withdrawalModel->rollBack();
                Response::error('Gagal memotong saldo GC. Saldo mungkin tidak mencukupi.', 400);
                return;
            }

            $this->withdrawalModel->commit();

            $withdrawal = $this->withdrawalModel->findById($withdrawalId);
            Response::success($withdrawal, 'Permintaan withdrawal berhasil dibuat', 201);
        } catch (Exception $e) {
            $this->withdrawalModel->rollBack();
            Response::error('Gagal membuat withdrawal: ' . $e->getMessage(), 500);
        }
    }
}
