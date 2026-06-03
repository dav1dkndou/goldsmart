<?php declare(strict_types=1);

/** Transactions Management */
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Config.php';
require_once __DIR__ . '/../../models/ReferralHistory.php';

$transactionModel = new Transaction();
$userModel = new User();
$productModel = new Product();
$configModel = new Config();
$referralHistoryModel = new ReferralHistory();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

    // Manual Create Transaction (Admin Only)
    if ($postAction === 'create') {
        $userId = (int) filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $productId = (int) filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $quantity = (int) filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);

        $user = $userModel->find($userId);
        $product = $productModel->find($productId);

        // Validasi: Hanya member yang bisa transaksi
        if (!$user || !in_array($user['role'], ['member', 'user'], true)) {
            $error = 'User harus memiliki role member atau user untuk melakukan transaksi!';
        } elseif (!$product || (int) $product['is_active'] !== 1) {
            $error = 'Produk tidak valid atau tidak aktif!';
        } elseif ((int) $product['stock'] < $quantity) {
            $error = 'Stok produk tidak mencukupi!';
        } else {
            // Calculate totals
            $totalAmount = (float) $product['price'] * $quantity;
            $gcEarned = (float) $product['gc_bonus'] * $quantity;
            $orderNumber = 'ORD-' . strtoupper(uniqid());

            // Create transaction
            $transactionId = $transactionModel->create([
                'user_id' => $userId,
                'product_id' => $productId,
                'order_number' => $orderNumber,
                'quantity' => $quantity,
                'price' => $product['price'],
                'total_amount' => $totalAmount,
                'gc_earned' => $gcEarned,
                'status' => 'pending',  // Sesuai alur, selalu berawal dari pending
                'notes' => 'Transaksi dibuat oleh admin',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($transactionId) {
                // Kurangi stok (booking) saat transaksi pending
                $newStock = (int) $product['stock'] - $quantity;
                $productModel->update($productId, [
                    'stock' => $newStock,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $message = 'Transaksi berhasil dibuat! Order: ' . $orderNumber;
            } else {
                $error = 'Gagal membuat transaksi!';
            }
        }
    }

    // Verify transaction
    if ($postAction === 'verify' && $id) {
        $transactionModel->updateStatus($id, 'verified');
        $message = 'Transaksi berhasil diverifikasi';
    }

    // Complete transaction - Tambah GC & Kurangi Stock + Komisi Referral
    if ($postAction === 'complete' && $id > 0) {
        $transaction = $transactionModel->find($id);
        if ($transaction && $transaction['status'] === 'verified') {
            $product = $productModel->find((int) $transaction['product_id']);

            // Check stock
            if (!$product || (int) $product['stock'] < (int) $transaction['quantity']) {
                $error = 'Stok produk tidak mencukupi!';
            } else {
                // Update status
                $transactionModel->updateStatus($id, 'completed');

                // Add GC bonus to user
                if ((float) $transaction['gc_earned'] > 0) {
                    $userModel->updateGCBalance((int) $transaction['user_id'], (float) $transaction['gc_earned'], 'add');
                }
                
                // NOTA: Kita tidak lagi memotong stok di sini karena stok 
                // sudah dipotong saat checkout (pending) atau saat admin buat manual.

                // === REFERRAL COMMISSION ===
                // Check if user was referred by someone and give commission
                $commissionMessage = '';
                $transactionUser = $userModel->find((int) $transaction['user_id']);
                if ($transactionUser && !empty($transactionUser['referred_by'])) {
                    $referrerId = (int) $transactionUser['referred_by'];
                    $referrer = $userModel->find($referrerId);

                    // Only give commission if referrer is still a member
                    if ($referrer && $referrer['role'] === 'member') {
                        // Check if commission already given for this transaction
                        if (!$referralHistoryModel->commissionExists($id)) {
                            // Get commission bonus from config
                            $commissionBonus = (float) $configModel->getValue('commission_bonus', '0.5');

                            if ($commissionBonus > 0) {
                                // Add commission to referrer
                                $userModel->updateGCBalance($referrerId, $commissionBonus, 'add');

                                // Record in referral history
                                $referralHistoryModel->addCommission(
                                    $referrerId,
                                    (int) $transaction['user_id'],
                                    $commissionBonus,
                                    $id,
                                    'Komisi transaksi ' . $transaction['order_number']
                                );

                                $commissionMessage = ', Komisi referral +' . number_format($commissionBonus, 2) . ' GC ke ' . $referrer['name'];
                            }
                        }
                    }
                }

                $message = 'Transaksi selesai! GC +' . number_format((float) $transaction['gc_earned'], 2) . ' ditambahkan' . $commissionMessage;
            }
        } else {
            $error = 'Transaksi harus diverifikasi dulu sebelum diselesaikan!';
        }
    }

    // Cancel transaction - Kembalikan stok
    if ($postAction === 'cancel' && $id > 0) {
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Dibatalkan oleh admin';

        // Get transaction details before cancel
        $transaction = $transactionModel->find($id);

        if ($transaction && $transaction['status'] !== 'cancelled') {
            // Kembalikan stok produk
            $product = $productModel->find((int) $transaction['product_id']);
            if ($product) {
                $newStock = (int) $product['stock'] + (int) $transaction['quantity'];
                $productModel->update($transaction['product_id'], [
                    'stock' => $newStock,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Update status to cancelled
            $transactionModel->update($id, [
                'status' => 'cancelled',
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $message = 'Transaksi dibatalkan dan stok +' . (int) $transaction['quantity'] . ' dikembalikan';
        } else {
            $error = 'Transaksi sudah dibatalkan atau tidak ditemukan!';
        }
    }
}

// Get members and products for create form
$members = $userModel->query("SELECT id, name, email, role FROM users WHERE role IN ('member', 'user') ORDER BY name ASC");
$products = $productModel->query('SELECT id, name, price, stock, gc_bonus FROM products WHERE is_active = 1 ORDER BY name ASC');

// Filter
$statusFilter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$whereClause = $statusFilter ? "WHERE t.status = '" . addslashes($statusFilter) . "'" : '';

$transactions = $transactionModel->query(
    "SELECT t.*, u.name as user_name, u.email as user_email, u.role as user_role, p.name as product_name 
     FROM transactions t 
     LEFT JOIN users u ON t.user_id = u.id 
     LEFT JOIN products p ON t.product_id = p.id 
     $whereClause
     ORDER BY t.created_at DESC"
);

$pageTitle = 'Transactions';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="filter-section">
    <span class="filter-label">Filter Status:</span>
    <div class="filter-buttons">
        <a href="?page=transactions" class="filter-btn <?= !$statusFilter ? 'active' : '' ?>">Semua</a>
        <a href="?page=transactions&status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?page=transactions&status=verified" class="filter-btn <?= $statusFilter === 'verified' ? 'active' : '' ?>">Verified</a>
        <a href="?page=transactions&status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="?page=transactions&status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>
</div>

<div class="table-container">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Daftar Transaksi</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i> Buat Transaksi Manual
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable custom-init">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order #</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>GC Bonus</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= (int) $t['id'] ?></td>
                    <td><code><?= htmlspecialchars($t['order_number']) ?></code></td>
                    <td>
                        <?= htmlspecialchars($t['user_name']) ?>
                        <br><small class="text-muted"><?= htmlspecialchars($t['user_email']) ?></small>
                    </td>
                    <td>
                        <span class="badge <?= in_array($t['user_role'], ['member', 'user'], true) ? 'badge-active' : 'badge-inactive' ?>">
                            <?= ucfirst($t['user_role'] ?? 'unknown') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($t['product_name']) ?></td>
                    <td><?= (int) $t['quantity'] ?></td>
                    <td>Rp <?= number_format((float) $t['total_amount']) ?></td>
                    <td><?= number_format((float) $t['gc_earned'], 2) ?> GC</td>
                    <td><span class="badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if (!empty($t['payment_proof'])): ?>
                                <button class="btn btn-sm btn-info text-white" onclick="viewProof('<?= htmlspecialchars($t['payment_proof']) ?>')" title="Lihat Bukti Bayar">
                                    <i class="bi bi-card-image"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($t['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="verify">
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Verify">
                                        <i class="bi bi-check"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-danger" onclick="cancelTransaction(<?= (int) $t['id'] ?>)" title="Cancel">
                                    <i class="bi bi-x"></i>
                                </button>
                            <?php elseif ($t['status'] === 'verified'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Complete transaksi? GC dan Komisi Referral akan ditambahkan ke user terkait.')">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Complete">
                                        <i class="bi bi-check-all"></i> Complete
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-danger" onclick="cancelTransaction(<?= (int) $t['id'] ?>)" title="Cancel">
                                    <i class="bi bi-x"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Transaction Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="createTransactionForm">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Buat Transaksi Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Informasi:</strong> Hanya user dengan role <strong>Member</strong> atau <strong>User</strong> yang dapat melakukan transaksi.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pilih Member <span class="text-danger">*</span></label>
                        <select name="user_id" id="selectUser" class="form-select" required onchange="updateUserInfo()">
                            <option value="">-- Pilih Member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= (int) $member['id'] ?>" 
                                        data-name="<?= htmlspecialchars($member['name']) ?>" 
                                        data-email="<?= htmlspecialchars($member['email']) ?>" 
                                        data-role="<?= htmlspecialchars($member['role']) ?>">
                                    <?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['email']) ?>) - <?= ucfirst($member['role']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="userInfo" class="text-muted"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pilih Produk <span class="text-danger">*</span></label>
                        <select name="product_id" id="selectProduct" class="form-select" required onchange="calculateTotal()">
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= (int) $prod['id'] ?>" 
                                        data-price="<?= (float) $prod['price'] ?>" 
                                        data-stock="<?= (int) $prod['stock'] ?>" 
                                        data-gc="<?= (float) $prod['gc_bonus'] ?>">
                                    <?= htmlspecialchars($prod['name']) ?> - Rp <?= number_format((float) $prod['price']) ?> (Stok: <?= (int) $prod['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="productInfo" class="text-muted"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="inputQuantity" class="form-control" 
                               value="1" min="1" required onchange="calculateTotal()">
                    </div>

                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="mb-3">Ringkasan:</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td>Harga Satuan:</td>
                                    <td class="text-end"><strong id="displayUnitPrice">Rp 0</strong></td>
                                </tr>
                                <tr>
                                    <td>Jumlah:</td>
                                    <td class="text-end"><strong id="displayQuantity">0</strong></td>
                                </tr>
                                <tr>
                                    <td>Total Harga:</td>
                                    <td class="text-end"><strong id="displayTotalPrice" class="text-primary">Rp 0</strong></td>
                                </tr>
                                <tr>
                                    <td>GC Bonus:</td>
                                    <td class="text-end"><strong id="displayGCBonus" class="text-success">0 GC</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-check-lg me-1"></i> Buat Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" id="cancelTransactionId">
                <div class="modal-header">
                    <h5 class="modal-title">Batalkan Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Alasan pembatalan:</label>
                        <textarea name="notes" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Batalkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Proof Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bukti Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img id="proofImage" src="" alt="Bukti Pembayaran" class="img-fluid rounded shadow-sm" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
'use strict';

const updateUserInfo = () => {
    const select = document.getElementById('selectUser');
    const option = select.options[select.selectedIndex];
    const info = document.getElementById('userInfo');
    
    if (option.value) {
        const role = option.dataset.role;
        const roleClass = (role === 'member' || role === 'user') ? 'text-success' : 'text-danger';
        info.innerHTML = `<span class="${roleClass}">Role: ${role.toUpperCase()}</span>`;
    } else {
        info.textContent = '';
    }
};

const calculateTotal = () => {
    const productSelect = document.getElementById('selectProduct');
    const quantityInput = document.getElementById('inputQuantity');
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    
    if (!selectedOption.value) {
        document.getElementById('displayUnitPrice').textContent = 'Rp 0';
        document.getElementById('displayQuantity').textContent = '0';
        document.getElementById('displayTotalPrice').textContent = 'Rp 0';
        document.getElementById('displayGCBonus').textContent = '0 GC';
        document.getElementById('productInfo').textContent = '';
        return;
    }
    
    const price = parseInt(selectedOption.dataset.price);
    const stock = parseInt(selectedOption.dataset.stock);
    const gcBonus = parseFloat(selectedOption.dataset.gc);
    const quantity = parseInt(quantityInput.value) || 1;
    
    // Validasi stok
    if (quantity > stock) {
        quantityInput.value = stock;
        alert(`Stok tidak mencukupi! Maksimal: ${stock}`);
        return;
    }
    
    const totalPrice = price * quantity;
    const totalGC = gcBonus * quantity;
    
    document.getElementById('displayUnitPrice').textContent = 'Rp ' + price.toLocaleString('id-ID');
    document.getElementById('displayQuantity').textContent = quantity;
    document.getElementById('displayTotalPrice').textContent = 'Rp ' + totalPrice.toLocaleString('id-ID');
    document.getElementById('displayGCBonus').textContent = totalGC.toFixed(2) + ' GC';
    
    document.getElementById('productInfo').innerHTML = 
        `<span class="text-primary">Stok tersedia: ${stock} | GC Bonus per item: ${gcBonus}</span>`;
};

const cancelTransaction = (id) => {
    document.getElementById('cancelTransactionId').value = id;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
};

const viewProof = (filename) => {
    const img = document.getElementById('proofImage');
    // Asumsi file diupload ke /uploads/payments/
    img.src = '/uploads/payments/' + filename;
    new bootstrap.Modal(document.getElementById('proofModal')).show();
};

// Form validation
document.getElementById('createTransactionForm').addEventListener('submit', (e) => {
    const userId = document.getElementById('selectUser').value;
    const productId = document.getElementById('selectProduct').value;
    const quantity = document.getElementById('inputQuantity').value;
    
    if (!userId || !productId || !quantity || quantity < 1) {
        e.preventDefault();
        alert('Mohon lengkapi semua field!');
        return false;
    }
    
    if (!confirm('Buat transaksi manual ini? Transaksi akan berstatus PENDING dan stok akan langsung dipesan (dikurangi).')) {
        e.preventDefault();
        return false;
    }
});
</script>

<script>
// Custom DataTable config for transactions
$(() => {
    if ($('.datatable').data('datatables')) {
        $('.datatable').DataTable().destroy();
    }
    $('.datatable').DataTable({
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            infoFiltered: "(difilter dari _MAX_ total data)",
            zeroRecords: "Tidak ada data yang cocok",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
        pageLength: 25,
        lengthMenu: [[5, 15, 25, 50, 100], [5, 15, 25, 50, 100]],
        responsive: true,
        order: [[9, 'desc']] // Sort by date column
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
