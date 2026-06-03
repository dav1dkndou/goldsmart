<?php declare(strict_types=1);

/** Withdrawals Management */
require_once __DIR__ . '/../../models/Withdrawal.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Config.php';

$withdrawalModel = new Withdrawal();
$userModel = new User();
$configModel = new Config();

$message = '';
$error = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

    if ($postAction === 'approve' && $id > 0) {
        $withdrawal = $withdrawalModel->find($id);
        if ($withdrawal && $withdrawal['status'] === 'pending') {
            // Update withdrawal status to approved
            $withdrawalModel->update($id, [
                'status' => 'approved',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $message = 'Withdrawal berhasil disetujui';
        }
    }

    if ($postAction === 'process' && $id > 0) {
        $withdrawal = $withdrawalModel->find($id);
        if ($withdrawal && $withdrawal['status'] === 'approved') {
            // Update withdrawal status to processing
            $withdrawalModel->update($id, [
                'status' => 'processing',
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $message = 'Withdrawal sedang diproses untuk transfer';
        }
    }

    if ($postAction === 'complete' && $id > 0) {
        $withdrawalModel->update($id, [
            'status' => 'completed',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $message = 'Withdrawal selesai diproses';
    }

    if ($postAction === 'reject' && $id > 0) {
        $withdrawal = $withdrawalModel->find($id);
        if ($withdrawal && ($withdrawal['status'] === 'pending' || $withdrawal['status'] === 'approved')) {
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Ditolak oleh admin';

            // Return GC to user
            $userModel->updateGCBalance((int) $withdrawal['user_id'], (float) $withdrawal['gc_amount'], 'add');

            // Update withdrawal status
            $withdrawalModel->update($id, [
                'status' => 'rejected',
                'notes' => $notes,
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $message = 'Withdrawal ditolak. GC dikembalikan ke user.';
        }
    }
}

// Filter
$statusFilter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$whereClause = $statusFilter ? "WHERE w.status = '" . addslashes($statusFilter) . "'" : '';

$withdrawals = $withdrawalModel->query(
    "SELECT w.*, u.name as user_name, u.email as user_email, u.phone as user_phone
     FROM withdrawals w 
     LEFT JOIN users u ON w.user_id = u.id 
     $whereClause
     ORDER BY w.created_at DESC"
);

// Stats
$pendingCount = $withdrawalModel->count(['status' => 'pending']);
$pendingTotal = $withdrawalModel->query(
    "SELECT SUM(final_amount) as total FROM withdrawals WHERE status = 'pending' LIMIT 1"
)[0]['total'] ?? 0;

$pageTitle = 'Withdrawals';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="mini-stat-card">
            <div class="stat-info">
                <div class="stat-label">Withdrawal Pending</div>
                <div class="stat-value text-warning"><?= number_format((int) $pendingCount) ?></div>
            </div>
            <div class="stat-icon" style="width: 48px; height: 48px; background: #fef3c7; color: #d97706; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="mini-stat-card">
            <div class="stat-info">
                <div class="stat-label">Total Pending Amount</div>
                <div class="stat-value text-info">Rp <?= number_format((float) $pendingTotal) ?></div>
            </div>
            <div class="stat-icon" style="width: 48px; height: 48px; background: #cffafe; color: #0891b2; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
            <label class="form-label mb-0 text-nowrap">Filter Status:</label>
            <div class="d-flex flex-wrap gap-2">
                <a href="?page=withdrawals" class="btn btn-sm <?= !$statusFilter ? 'btn-gold' : 'btn-outline-secondary' ?>">Semua</a>
                <a href="?page=withdrawals&status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">Pending</a>
                <a href="?page=withdrawals&status=approved" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-info' : 'btn-outline-info' ?>">Approved</a>
                <a href="?page=withdrawals&status=processing" class="btn btn-sm <?= $statusFilter === 'processing' ? 'btn-primary' : 'btn-outline-primary' ?>">Processing</a>
                <a href="?page=withdrawals&status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-success' : 'btn-outline-success' ?>">Completed</a>
                <a href="?page=withdrawals&status=rejected" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">Rejected</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-cash-stack me-2"></i>Daftar Withdrawal
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>GC Amount</th>
                    <th>Rate</th>
                    <th>Fee</th>
                    <th>Final</th>
                    <th>Bank</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $w): ?>
                <tr>
                    <td><?= (int) $w['id'] ?></td>
                    <td>
                        <?= htmlspecialchars($w['user_name']) ?>
                        <br><small class="text-muted"><?= htmlspecialchars($w['user_phone'] ?? $w['user_email']) ?></small>
                    </td>
                    <td><?= number_format((float) $w['gc_amount'], 2) ?> GC</td>
                    <td>Rp <?= number_format((float) $w['gc_price']) ?></td>
                    <td>Rp <?= number_format((float) $w['admin_fee']) ?></td>
                    <td><strong>Rp <?= number_format((float) $w['final_amount']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($w['bank_name']) ?>
                        <br><small><?= htmlspecialchars($w['bank_account_number'] ?? '') ?></small>
                        <br><small><?= htmlspecialchars($w['bank_account_name'] ?? '') ?></small>
                    </td>
                    <td><span class="badge badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span></td>
                    <td><?= date('d M Y H:i', strtotime($w['created_at'])) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($w['status'] === 'pending'): ?>
                                <button class="btn btn-outline-success" onclick="approveWithdrawal(<?= (int) $w['id'] ?>)" title="Approve">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="rejectWithdrawal(<?= (int) $w['id'] ?>)" title="Reject">
                                    <i class="bi bi-x"></i>
                                </button>
                            <?php elseif ($w['status'] === 'approved'): ?>
                                <button class="btn btn-outline-primary" onclick="processWithdrawal(<?= (int) $w['id'] ?>)" title="Process Transfer">
                                    <i class="bi bi-send"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="rejectWithdrawal(<?= (int) $w['id'] ?>)" title="Reject">
                                    <i class="bi bi-x"></i>
                                </button>
                            <?php elseif ($w['status'] === 'processing'): ?>
                                <button class="btn btn-outline-success" onclick="completeWithdrawal(<?= (int) $w['id'] ?>)" title="Mark Complete">
                                    <i class="bi bi-check-all"></i>
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

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectWithdrawalId">
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> GC akan dikembalikan ke user</p>
                    <div class="mb-3">
                        <label class="form-label">Alasan penolakan:</label>
                        <textarea name="notes" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Action Forms -->
<form id="approveForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="id" id="approveWithdrawalId">
</form>

<form id="processForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="process">
    <input type="hidden" name="id" id="processWithdrawalId">
</form>

<form id="completeForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="complete">
    <input type="hidden" name="id" id="completeWithdrawalId">
</form>

<script>
'use strict';

const approveWithdrawal = (id) => {
    if (confirm('Approve withdrawal ini?')) {
        document.getElementById('approveWithdrawalId').value = id;
        document.getElementById('approveForm').submit();
    }
};

const processWithdrawal = (id) => {
    if (confirm('Proses transfer untuk withdrawal ini?')) {
        document.getElementById('processWithdrawalId').value = id;
        document.getElementById('processForm').submit();
    }
};

const completeWithdrawal = (id) => {
    if (confirm('Tandai withdrawal ini sebagai selesai?')) {
        document.getElementById('completeWithdrawalId').value = id;
        document.getElementById('completeForm').submit();
    }
};

const rejectWithdrawal = (id) => {
    document.getElementById('rejectWithdrawalId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
