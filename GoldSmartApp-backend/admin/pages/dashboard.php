<?php declare(strict_types=1);

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/Withdrawal.php';
require_once __DIR__ . '/../../models/Video.php';
require_once __DIR__ . '/../../models/Config.php';

$userModel = new User();
$productModel = new Product();
$transactionModel = new Transaction();
$withdrawalModel = new Withdrawal();
$videoModel = new Video();
$configModel = new Config();

// Statistics
$totalUsers = $userModel->count(['role' => 'user']) + $userModel->count(['role' => 'member']);
$totalProducts = $productModel->count(['is_active' => 1]);
$pendingTransactions = $transactionModel->count(['status' => 'pending']);
$pendingWithdrawals = $withdrawalModel->count(['status' => 'pending']);
$totalVideos = $videoModel->count(['is_active' => 1]);

$gcStats = $userModel->query("SELECT SUM(gc_balance) as total_gc FROM users WHERE role IN ('user', 'member') LIMIT 1");
$totalGC = (float) ($gcStats[0]['total_gc'] ?? 0);

$salesStats = $transactionModel->query("SELECT SUM(total_amount) as total_sales FROM transactions WHERE status = 'completed' LIMIT 1");
$totalSales = (float) ($salesStats[0]['total_sales'] ?? 0);

$recentTransactions = $transactionModel->query(
    'SELECT t.*, u.name as user_name, p.name as product_name 
     FROM transactions t 
     LEFT JOIN users u ON t.user_id = u.id 
     LEFT JOIN products p ON t.product_id = p.id 
     ORDER BY t.created_at DESC LIMIT 5'
);

$recentWithdrawals = $withdrawalModel->query(
    'SELECT w.*, u.name as user_name 
     FROM withdrawals w 
     LEFT JOIN users u ON w.user_id = u.id 
     ORDER BY w.created_at DESC LIMIT 5'
);

$gcPrice = (float) $configModel->getValue('gc_price', 100);

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Main Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-people"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format((int) $totalUsers) ?></div>
            <div class="stat-label">Total User</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gold">
            <i class="bi bi-coin"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format($totalGC, 2) ?></div>
            <div class="stat-label">Total GC Beredar</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-cash-stack"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value">Rp <?= number_format($totalSales, 0) ?></div>
            <div class="stat-label">Total Penjualan</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value">Rp <?= number_format($gcPrice, 0) ?></div>
            <div class="stat-label">Harga GC Saat Ini</div>
        </div>
    </div>
</div>

<!-- Mini Stats -->
<div class="mini-stats-grid">
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Transaksi Pending</div>
            <div class="mini-stat-value text-warning"><?= number_format((int) $pendingTransactions) ?></div>
        </div>
        <a href="?page=transactions&status=pending" class="btn btn-sm btn-primary">Lihat</a>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Withdrawal Pending</div>
            <div class="mini-stat-value text-danger"><?= number_format((int) $pendingWithdrawals) ?></div>
        </div>
        <a href="?page=withdrawals&status=pending" class="btn btn-sm btn-primary">Lihat</a>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Total Produk</div>
            <div class="mini-stat-value text-primary"><?= number_format((int) $totalProducts) ?></div>
        </div>
        <a href="?page=products" class="btn btn-sm btn-primary">Lihat</a>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Total Video</div>
            <div class="mini-stat-value text-info"><?= number_format((int) $totalVideos) ?></div>
        </div>
        <a href="?page=videos" class="btn btn-sm btn-primary">Lihat</a>
    </div>
</div>

<!-- Recent Activity Tables -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="table-container">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt me-2"></i>Transaksi Terbaru</span>
                <a href="?page=transactions" class="btn btn-sm btn-primary">Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Produk</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTransactions)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada transaksi</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $trx): ?>
                                <tr>
                                    <td>#<?= (int) $trx['id'] ?></td>
                                    <td><?= htmlspecialchars($trx['user_name']) ?></td>
                                    <td><?= htmlspecialchars($trx['product_name']) ?></td>
                                    <td>Rp <?= number_format((float) $trx['total_amount']) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = [
                                            'pending' => 'badge-pending',
                                            'verified' => 'badge-verified',
                                            'completed' => 'badge-completed',
                                            'cancelled' => 'badge-cancelled'
                                        ][$trx['status']] ?? 'badge-pending';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($trx['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="table-container">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-2"></i>Withdrawal Terbaru</span>
                <a href="?page=withdrawals" class="btn btn-sm btn-primary">Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>GC</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentWithdrawals)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada withdrawal</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentWithdrawals as $wd): ?>
                                <tr>
                                    <td>#<?= (int) $wd['id'] ?></td>
                                    <td><?= htmlspecialchars($wd['user_name']) ?></td>
                                    <td><?= number_format((float) $wd['gc_amount']) ?> GC</td>
                                    <td>Rp <?= number_format((float) ($wd['final_amount'] ?? 0)) ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = [
                                            'pending' => 'badge-pending',
                                            'approved' => 'badge-approved',
                                            'processing' => 'badge-processing',
                                            'completed' => 'badge-completed',
                                            'rejected' => 'badge-rejected'
                                        ][$wd['status']] ?? 'badge-pending';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($wd['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
