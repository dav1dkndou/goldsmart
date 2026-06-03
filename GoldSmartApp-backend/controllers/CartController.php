<?php declare(strict_types=1);

/**
 * Cart Controller
 * Handles shopping cart operations
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/OrderValidation.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Config.php';
require_once __DIR__ . '/../models/ReferralHistory.php';
require_once __DIR__ . '/../models/Transaction.php';

class CartController extends Controller
{
    use OrderValidation;

    private Cart $cartModel;
    private Product $productModel;
    private User $userModel;
    private Config $configModel;
    private ReferralHistory $referralHistoryModel;
    private Transaction $transactionModel;

    public function __construct()
    {
        parent::__construct();
        $this->cartModel = new Cart();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->configModel = new Config();
        $this->referralHistoryModel = new ReferralHistory();
        $this->transactionModel = new Transaction();
    }

    /**
     * Get user's cart
     * GET /api/cart
     */
    public function index(): void
    {
        $userId = $this->requireUserId();
        $cartItems = $this->cartModel->getUserCartWithProducts($userId);
        $summary = $this->cartModel->getUserCartSummary($userId);

        Response::success([
            'items' => $cartItems,
            'summary' => $summary
        ]);
    }

    /**
     * Add item to cart
     * POST /api/cart
     * Body: { product_id: number, quantity: number }
     */
    public function add(): void
    {
        $userId = $this->requireUserId();
        $data = $this->getRequestData();

        // Validate input
        if (empty($data['product_id'])) {
            Response::error('Product ID diperlukan', 400);
        }

        $productId = (int) $data['product_id'];
        $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;

        if ($quantity < 1) {
            Response::error('Jumlah minimal 1', 400);
        }

        // Verify product and stock
        $product = $this->validateProductStock($productId, $quantity);

        // Add or update cart
        if (!$this->cartModel->addOrUpdate($userId, $productId, $quantity)) {
            Response::error('Gagal menambahkan ke keranjang');
        }

        // Get updated cart summary
        $summary = $this->cartModel->getUserCartSummary($userId);

        Response::success([
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'summary' => $summary
        ], 'Berhasil ditambahkan ke keranjang');
    }

    /**
     * Update cart item quantity
     * PUT /api/cart/{id}
     * Body: { quantity: number }
     */
    public function update($id): void
    {
        $userId = $this->requireUserId();
        $data = $this->getRequestData();
        $cartId = (int) $id;

        if (!isset($data['quantity']) || $data['quantity'] < 1) {
            Response::error('Jumlah minimal 1', 400);
        }

        $quantity = (int) $data['quantity'];

        // Get cart item to verify ownership and product stock
        $cartItem = $this->cartModel->findById($cartId);
        if (!$cartItem) {
            Response::notFound('Item tidak ditemukan di keranjang');
        }

        // Verify ownership
        if ((int) $cartItem['user_id'] !== $userId) {
            Response::forbidden('Tidak memiliki akses');
        }

        // Validate product stock
        $this->validateProductStock((int) $cartItem['product_id'], $quantity);

        // Update quantity
        if (!$this->cartModel->updateQuantity($cartId, $userId, $quantity)) {
            Response::error('Gagal mengupdate keranjang');
        }

        // Get updated cart summary
        $summary = $this->cartModel->getUserCartSummary($userId);

        Response::success([
            'message' => 'Keranjang berhasil diupdate',
            'summary' => $summary
        ], 'Berhasil diupdate');
    }

    /**
     * Remove item from cart
     * DELETE /api/cart/{id}
     */
    public function remove($id): void
    {
        $userId = $this->requireUserId();
        $cartId = (int) $id;

        if (!$this->cartModel->removeItem($cartId, $userId)) {
            Response::error('Gagal menghapus item dari keranjang');
        }

        // Get updated cart summary
        $summary = $this->cartModel->getUserCartSummary($userId);

        Response::success([
            'message' => 'Item berhasil dihapus dari keranjang',
            'summary' => $summary
        ], 'Berhasil dihapus');
    }

    /**
     * Clear all items from cart
     * DELETE /api/cart
     */
    public function clear(): void
    {
        $userId = $this->requireUserId();

        if (!$this->cartModel->clearUserCart($userId)) {
            Response::error('Gagal mengosongkan keranjang');
        }

        Response::success([
            'message' => 'Keranjang berhasil dikosongkan'
        ], 'Berhasil dikosongkan');
    }

    /**
     * Get checkout status (daily limit, cooldown)
     * GET /api/cart/checkout-status
     */
    public function checkoutStatus(): void
    {
        $userId = $this->requireUserId();
        $user = $this->userModel->find($userId);
        if (!$user) {
            Response::unauthorized();
        }

        Response::success($this->getCheckoutStatusInfo($userId, $user, $this->transactionModel));
    }

    /**
     * Checkout - Create transactions from cart items
     * POST /api/cart/checkout
     */
    public function checkout(): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            Response::unauthorized();
        }

        // Get user data for referral check
        $user = $this->userModel->find($userId);
        if (!$user) {
            Response::unauthorized();
        }

        // Validate order limits (daily limit & cooldown) - uses shared trait
        $this->validateOrderLimits($userId, $user, $this->transactionModel);

        // Check for direct checkout from body
        $data = $this->getRequestData();
        $isDirectCheckout = false;

        if (!empty($data['product_id']) && !empty($data['quantity'])) {
            $productId = (int) $data['product_id'];
            $quantity = (int) $data['quantity'];
            
            $product = $this->productModel->findById($productId);
            if (!$product) {
                Response::error('Produk tidak ditemukan', 404);
            }
            if (!(bool) $product['is_active']) {
                Response::error('Produk tidak tersedia', 400);
            }
            
            // Construct a fake cart item structure
            $cartItems = [[
                'product_id' => $product['id'],
                'quantity' => $quantity,
                'price' => $product['price'],
                'product_name' => $product['name'],
                'product_is_active' => $product['is_active'],
                'stock' => $product['stock'],
                'items_per_unit' => $product['items_per_unit'] ?? 1,
                'gc_reward' => $product['gc_bonus'] ?? 0
            ]];
            $isDirectCheckout = true;
        } else {
            // Get cart items
            $cartItems = $this->cartModel->getUserCartWithProducts($userId);
        }

        if (empty($cartItems)) {
            Response::error('Keranjang kosong', 400);
        }

        // Validate all items before processing
        $this->validateCartItems($cartItems);

        // Create transactions for each cart item
        $transactionModel = $this->transactionModel;

        $createdTransactions = [];
        $totalAmount = 0;
        $totalGCReward = 0;
        $totalItems = 0;  // Total items considering items_per_unit

        // Use model's transaction methods
        $this->cartModel->beginTransaction();

        try {
            foreach ($cartItems as $item) {
                $transaction = $this->createTransactionFromCartItem($item, $userId, $transactionModel);
                $createdTransactions[] = $transaction;
                $totalAmount += $transaction['total_amount'];
                $totalGCReward += $transaction['gc_earned'];

                // Calculate total items based on items_per_unit
                $itemsPerUnit = (int) ($item['items_per_unit'] ?? 1);
                $totalItems += (int) $item['quantity'] * $itemsPerUnit;
            }

            // === REFERRAL COMMISSION BONUS ===
            // Give commission to referrer based on TOTAL ITEMS (qty × items_per_unit)
            if (!empty($user['referred_by'])) {
                $referrerId = (int) $user['referred_by'];
                $referrer = $this->userModel->find($referrerId);

                // Only give commission if referrer is still a member
                if ($referrer && $referrer['role'] === 'member') {
                    // Get commission bonus per item from config
                    $commissionPerItem = (float) $this->configModel->getValue('commission_bonus', '0.5');

                    if ($commissionPerItem > 0 && $totalItems > 0) {
                        // Calculate total commission based on total items
                        $totalCommission = $commissionPerItem * $totalItems;

                        // Add commission to referrer
                        $this->userModel->updateGCBalance($referrerId, $totalCommission, 'add');

                        // Record in referral history
                        $productNames = array_map(fn($t) => $t['product_name'], $createdTransactions);
                        $this->referralHistoryModel->addCommission(
                            $referrerId,
                            $userId,
                            $totalCommission,
                            null,  // No single transaction ID for cart checkout
                            'Komisi checkout cart: ' . implode(', ', $productNames) . ' (total ' . $totalItems . ' item)'
                        );
                    }
                }
            }

            // Clear cart after successful checkout ONLY IF not direct checkout
            if (!$isDirectCheckout) {
                $this->cartModel->clearUserCart($userId);
            }

            $this->cartModel->commit();

            Response::success([
                'message' => 'Checkout berhasil',
                'transactions' => $createdTransactions,
                'summary' => [
                    'total_orders' => count($createdTransactions),
                    'total_amount' => $totalAmount,
                    'total_gc_reward' => $totalGCReward
                ]
            ], 'Checkout berhasil! Pesanan Anda sedang menunggu verifikasi admin.');
        } catch (Exception $e) {
            $this->cartModel->rollBack();
            error_log('Checkout error: ' . $e->getMessage());
            Response::error('Gagal melakukan checkout: ' . $e->getMessage());
        }
    }

    /**
     * Get cart summary only (count and totals)
     * GET /api/cart/summary
     */
    public function summary(): void
    {
        $userId = $this->requireUserId();
        $summary = $this->cartModel->getUserCartSummary($userId);
        Response::success($summary);
    }

    /**
     * Require user ID with automatic error handling
     */
    private function requireUserId(): int
    {
        $userId = $this->getUserId();
        if (!$userId) {
            Response::unauthorized();
        }
        return $userId;
    }

    /**
     * Validate product exists, is active, and has enough stock
     */
    private function validateProductStock(int $productId, int $quantity): array
    {
        $product = $this->productModel->findById($productId);

        if (!$product) {
            Response::notFound('Produk tidak ditemukan');
        }

        if (!(bool) $product['is_active']) {
            Response::error('Produk tidak tersedia', 400);
        }

        if ((int) $product['stock'] < $quantity) {
            Response::error('Stok tidak mencukupi', 400);
        }

        return $product;
    }

    /**
     * Validate cart items before checkout
     */
    private function validateCartItems(array $cartItems): void
    {
        $errors = [];

        foreach ($cartItems as $item) {
            if (!(bool) $item['product_is_active']) {
                $errors[] = "{$item['product_name']} tidak tersedia";
                continue;
            }

            // Calculate total items needed based on items_per_unit
            $itemsPerUnit = (int) ($item['items_per_unit'] ?? 1);
            $totalItemsNeeded = (int) $item['quantity'] * $itemsPerUnit;

            if ((int) $item['stock'] < $totalItemsNeeded) {
                $maxQty = $itemsPerUnit > 0 ? floor((int) $item['stock'] / $itemsPerUnit) : 0;
                $errors[] = "{$item['product_name']}: stok tidak mencukupi (maksimal: {$maxQty} unit)";
            }
        }

        if (!empty($errors)) {
            Response::error(implode(', ', $errors), 400);
        }
    }

    /**
     * Create transaction from cart item
     * Uses createTransaction() for proper order number generation with FOR UPDATE lock
     * Also decreases product stock to prevent over-purchasing
     */
    private function createTransactionFromCartItem(array $item, int $userId, object $transactionModel): array
    {
        $subtotal = (float) $item['price'] * (int) $item['quantity'];
        $gcEarned = (float) $item['gc_reward'] * (int) $item['quantity'];

        // Use createTransaction() for consistent order number generation (with FOR UPDATE lock)
        $transactionId = $transactionModel->createTransaction([
            'user_id' => $userId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total_amount' => $subtotal,
            'gc_earned' => $gcEarned,
            'notes' => 'Dibuat dari keranjang belanja'
        ]);

        if (!$transactionId) {
            throw new Exception("Gagal membuat transaksi untuk {$item['product_name']}");
        }

        // Decrease product stock (uses items_per_unit internally)
        $this->productModel->decreaseStock((int) $item['product_id'], (int) $item['quantity']);

        // Fetch the created transaction to get the generated order number
        $transaction = $transactionModel->findById($transactionId);
        $orderNumber = $transaction['order_number'] ?? 'ORD-' . strtoupper(uniqid());

        return [
            'order_number' => $orderNumber,
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'total_amount' => $subtotal,
            'gc_earned' => $gcEarned
        ];
    }
}
