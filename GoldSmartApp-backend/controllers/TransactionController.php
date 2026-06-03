<?php declare(strict_types=1);

/**
 * Transaction Controller
 * Handles transaction operations
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/OrderValidation.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Config.php';
require_once __DIR__ . '/../models/ReferralHistory.php';

class TransactionController extends Controller
{
    use OrderValidation;

    private Transaction $transactionModel;
    private Product $productModel;
    private User $userModel;
    private Config $configModel;
    private ReferralHistory $referralHistoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->transactionModel = new Transaction();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->configModel = new Config();
        $this->referralHistoryModel = new ReferralHistory();
    }

    /**
     * Format transaction data for API response (eliminates duplication)
     */
    private function formatTransaction(array $t): array
    {
        return [
            'id' => (int) $t['id'],
            'order_id' => $t['order_number'],
            'orderId' => $t['order_number'],
            'order_number' => $t['order_number'],
            'orderNumber' => $t['order_number'],
            'user_id' => (int) $t['user_id'],
            'userId' => (int) $t['user_id'],
            'product_id' => (int) $t['product_id'],
            'productId' => (int) $t['product_id'],
            'product_name' => $t['product_name'],
            'productName' => $t['product_name'],
            'quantity' => (int) $t['quantity'],
            'price' => (float) $t['price'],
            'pricePerUnit' => (float) $t['price'],
            'product_price' => (float) $t['price'],
            'productPrice' => (float) $t['price'],
            'total_price' => (float) $t['total_amount'],
            'totalPrice' => (float) $t['total_amount'],
            'total_amount' => (float) $t['total_amount'],
            'totalAmount' => (float) $t['total_amount'],
            'gc_reward' => (float) $t['gc_earned'],
            'gcReward' => (float) $t['gc_earned'],
            'gc_earned' => (float) $t['gc_earned'],
            'gcEarned' => (float) $t['gc_earned'],
            'status' => $t['status'],
            'payment_method' => $t['payment_method'] ?? null,
            'paymentMethod' => $t['payment_method'] ?? null,
            'payment_proof' => $t['payment_proof'] ?? null,
            'paymentProof' => $t['payment_proof'] ?? null,
            'shipping_address' => $t['shipping_address'] ?? null,
            'shippingAddress' => $t['shipping_address'] ?? null,
            'admin_notes' => $t['notes'] ?? null,
            'adminNotes' => $t['notes'] ?? null,
            'notes' => $t['notes'] ?? null,
            'verified_at' => $t['verified_at'] ?? null,
            'verifiedAt' => $t['verified_at'] ?? null,
            'created_at' => $t['created_at'],
            'createdAt' => $t['created_at']
        ];
    }

    /**
     * Get all transactions for current user
     * GET /api/transactions
     */
    public function index(): void
    {
        $authUser = $this->requireAuth();

        try {
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: null;
            $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT) ?: 15;
            $perPage = min(max(1, $perPage), 50); // Enforce strict limit

            $result = $this->transactionModel->getByUser($authUser['user_id'], $page, $perPage);

            if ($page !== null) {
                // Paginated response
                $result['data'] = array_map([$this, 'formatTransaction'], $result['data']);
                Response::success($result);
            } else {
                // Legacy: flat array response
                $formatted = array_map([$this, 'formatTransaction'], $result);
                Response::success($formatted);
            }
        } catch (Exception $e) {
            error_log('Get transactions error: ' . $e->getMessage());
            Response::error('Gagal mengambil data transaksi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single transaction
     * GET /api/transactions/{id}
     */
    public function show($id): void
    {
        $authUser = $this->requireAuth();

        try {
            $transaction = $this->transactionModel->getByIdWithDetails((int) $id, $authUser['user_id']);

            if (!$transaction) {
                Response::notFound('Transaksi tidak ditemukan');
            }

            // Use helper method for consistent formatting
            Response::success($this->formatTransaction($transaction));
        } catch (Exception $e) {
            error_log('Get transaction error: ' . $e->getMessage());
            Response::error('Gagal mengambil data transaksi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new transaction
     * POST /api/transactions
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        // Validate early
        $errors = $this->validate($data, [
            'product_id' => 'required|numeric',
            'quantity' => 'required|numeric'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        // Validate user is active using userModel
        $user = $this->userModel->find($authUser['user_id']);
        if (!$user || !$user['is_active']) {
            Response::error('Akun Anda tidak aktif', 403);
        }

        // Validate order limits (daily limit & cooldown) - uses shared trait
        $this->validateOrderLimits((int) $authUser['user_id'], $user, $this->transactionModel);

        // Check product exists and has stock
        $product = $this->productModel->findById($productId);
        if (!$product) {
            Response::notFound('Produk tidak ditemukan');
        }

        // Check stock availability using items_per_unit
        $stockInfo = $this->productModel->checkStockAvailability($productId, $quantity);
        if (!$stockInfo['available']) {
            Response::error('Stok produk tidak mencukupi. Tersedia: ' . $stockInfo['max_purchasable_qty'] . ' unit', 400);
        }

        // Calculate totals (explicit type casting for accuracy)
        $totalAmount = (float) $product['price'] * $quantity;
        $gcEarned = (float) $product['gc_bonus'] * $quantity;

        // Get items_per_unit for referral calculation
        $itemsPerUnit = (int) ($product['items_per_unit'] ?? 1);
        $totalItems = $quantity * $itemsPerUnit;

        // Get minimum order amount from config
        $minOrderAmount = (float) $this->configModel->getValue('min_order_amount', '0');
        if ($totalAmount < $minOrderAmount) {
            Response::error('Minimum pembelian adalah Rp ' . number_format($minOrderAmount, 0, ',', '.'), 400);
        }

        // Create transaction (wrapped in DB transaction for atomicity)
        try {
            $this->transactionModel->beginTransaction();

            $transactionId = $this->transactionModel->createTransaction([
                'user_id' => $authUser['user_id'],
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => (float) $product['price'],
                'total_amount' => $totalAmount,
                'gc_earned' => $gcEarned,
                'payment_method' => $data['payment_method'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);

            // Decrease product stock (now uses items_per_unit internally)
            $this->productModel->decreaseStock($productId, $quantity);

            // === REFERRAL COMMISSION BONUS ===
            // Give commission to referrer if user was referred by a member
            // Commission is calculated PER TOTAL ITEMS (qty × items_per_unit)
            if (!empty($user['referred_by'])) {
                $referrerId = (int) $user['referred_by'];
                $referrer = $this->userModel->find($referrerId);

                // Only give commission if referrer is still a member
                if ($referrer && $referrer['role'] === 'member') {
                    // Get commission bonus per item from config
                    $commissionPerItem = (float) $this->configModel->getValue('commission_bonus', '0.5');

                    if ($commissionPerItem > 0) {
                        // Calculate total commission based on TOTAL ITEMS (qty × items_per_unit)
                        $totalCommission = $commissionPerItem * $totalItems;

                        // Add commission to referrer
                        $this->userModel->updateGCBalance($referrerId, $totalCommission, 'add');

                        // Record in referral history
                        $this->referralHistoryModel->addCommission(
                            $referrerId,
                            (int) $user['id'],
                            $totalCommission,
                            (int) $transactionId,
                            'Komisi transaksi: ' . $product['name'] . ' (' . $quantity . ' unit × ' . $itemsPerUnit . ' = ' . $totalItems . ' item)'
                        );
                    }
                }
            }

            $transaction = $this->transactionModel->getByIdWithDetails($transactionId);

            $this->transactionModel->commit();

            Response::success([
                'id' => (int) $transaction['id'],
                'order_id' => $transaction['order_number'],
                'order_number' => $transaction['order_number'],
                'product_name' => $transaction['product_name'],
                'quantity' => (int) $transaction['quantity'],
                'price' => (float) $transaction['price'],
                'total_price' => (float) $transaction['total_amount'],
                'gc_reward' => (float) $transaction['gc_earned'],
                'status' => $transaction['status'],
                'created_at' => $transaction['created_at']
            ], 'Transaksi berhasil dibuat', 201);
        } catch (Exception $e) {
            $this->transactionModel->rollBack();
            Response::error('Gagal membuat transaksi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload payment proof
     * POST /api/transactions/{id}/payment-proof
     */
    public function uploadPaymentProof($id): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        // Check transaction exists and belongs to user
        $transaction = $this->transactionModel->getByIdWithDetails((int) $id, $authUser['user_id']);
        if (!$transaction) {
            Response::notFound('Transaksi tidak ditemukan');
        }

        if ($transaction['status'] !== 'pending') {
            Response::error('Transaksi sudah diproses', 400);
        }

        // Handle base64 image
        if (!empty($data['payment_proof'])) {
            $imageData = $data['payment_proof'];

            // Remove data URL prefix if present
            if (strpos($imageData, 'base64,') !== false) {
                $imageData = explode('base64,', $imageData)[1];
            }

            $imageContent = base64_decode($imageData, true);
            if ($imageContent === false) {
                Response::error('Format gambar tidak valid', 400);
            }

            // Create upload directory if not exists
            $uploadDir = UPLOAD_PATH . 'payment_proofs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Save image with unique filename
            $filename = 'payment_' . $id . '_' . time() . '.jpg';
            $filepath = $uploadDir . $filename;

            if (file_put_contents($filepath, $imageContent) === false) {
                Response::error('Gagal menyimpan file', 500);
            }

            // Update transaction
            $imagePath = 'uploads/payment_proofs/' . $filename;
            $this->transactionModel->uploadPaymentProof((int) $id, $imagePath);

            Response::success([
                'payment_proof' => UPLOAD_URL . 'payment_proofs/' . $filename
            ], 'Bukti pembayaran berhasil diupload');
        } else {
            Response::error('Bukti pembayaran wajib diupload', 400);
        }
    }
}
