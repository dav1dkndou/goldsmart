<?php declare(strict_types=1);

/**
 * Membership Requests Management
 * Admin can approve/reject membership requests
 */
require_once __DIR__ . '/../../models/MembershipRequest.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Config.php';
require_once __DIR__ . '/../../models/ReferralHistory.php';

$membershipModel = new MembershipRequest();
$userModel = new User();
$configModel = new Config();
$referralHistoryModel = new ReferralHistory();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $requestId = (int) filter_input(INPUT_POST, 'request_id', FILTER_SANITIZE_NUMBER_INT);
    $adminId = (int) getCurrentAdmin()['id'];

    if ($action === 'approve' && $requestId > 0) {
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Approved by admin';
        if ($membershipModel->approve($requestId, $adminId, $notes)) {
            $message = 'Pengajuan berhasil disetujui! User sekarang menjadi member.';
        } else {
            $error = 'Gagal menyetujui pengajuan!';
        }
    }

    if ($action === 'reject' && $requestId > 0) {
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Rejected by admin';
        if ($membershipModel->reject($requestId, $adminId, $reason)) {
            $message = 'Pengajuan ditolak.';
        } else {
            $error = 'Gagal menolak pengajuan!';
        }
    }

    // Manual upgrade user to member
    if ($action === 'upgrade_user' && filter_input(INPUT_POST, 'user_id') !== null) {
        $userId = (int) filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $user = $userModel->find($userId);

        if (!$user) {
            $error = 'User tidak ditemukan!';
        } elseif ($user['role'] === 'member') {
            $error = 'User sudah menjadi member!';
        } else {
            // Upgrade user to member
            $userModel->update($userId, ['role' => 'member']);

            // === REFERRAL BONUS WHEN MANUAL UPGRADING TO MEMBER ===
            $bonusMessage = '';
            if (!empty($user['referred_by'])) {
                $referrerId = (int) $user['referred_by'];
                $referrer = $userModel->find($referrerId);

                // Only give bonus if referrer is still a member
                if ($referrer && $referrer['role'] === 'member') {
                    // Get referral bonus from config
                    $referralBonus = (float) $configModel->getValue('referral_bonus', '5');

                    if ($referralBonus > 0) {
                        // Add bonus to referrer
                        $userModel->updateGCBalance($referrerId, $referralBonus, 'add');

                        // Record in referral history
                        $referralHistoryModel->addSignupBonus(
                            $referrerId,
                            $userId,
                            $referralBonus,
                            'Bonus referral: ' . $user['name'] . ' upgrade ke Member (manual)'
                        );

                        $bonusMessage = ' Bonus referral +' . number_format($referralBonus, 2) . " GC diberikan ke {$referrer['name']}.";
                    }
                }
            }

            $message = "User '{$user['name']}' berhasil diupgrade menjadi member!" . $bonusMessage;
        }
    }

    // Demote member to user
    if ($action === 'demote_member' && filter_input(INPUT_POST, 'user_id') !== null) {
        $userId = (int) filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $user = $userModel->find($userId);

        if (!$user) {
            $error = 'User tidak ditemukan!';
        } elseif ($user['role'] !== 'member') {
            $error = 'User bukan member!';
        } else {
            $userModel->update($userId, ['role' => 'user']);
            $message = "Member '{$user['name']}' berhasil didemote menjadi user!";
        }
    }
}

// Get filter
$statusFilter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'pending';

// Get requests
$requests = $membershipModel->getAllWithUser($statusFilter);

// Get stats
$stats = $membershipModel->getStats();

// Get users for manual upgrade/demote
$regularUsers = $userModel->query("SELECT id, name, email, gc_balance FROM users WHERE role = 'user' ORDER BY name ASC");
$members = $userModel->query("SELECT id, name, email, gc_balance FROM users WHERE role = 'member' ORDER BY name ASC");

$pageTitle = 'Membership Management';
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

<!-- Stats -->
<div class="mini-stats-grid">
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Pending</div>
            <div class="mini-stat-value text-warning"><?= number_format((int) $stats['pending']) ?></div>
        </div>
        <div class="mini-stat-icon text-warning">
            <i class="bi bi-hourglass-split"></i>
        </div>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Approved</div>
            <div class="mini-stat-value text-success"><?= number_format((int) $stats['approved']) ?></div>
        </div>
        <div class="mini-stat-icon text-success">
            <i class="bi bi-check-circle"></i>
        </div>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Rejected</div>
            <div class="mini-stat-value text-danger"><?= number_format((int) $stats['rejected']) ?></div>
        </div>
        <div class="mini-stat-icon text-danger">
            <i class="bi bi-x-circle"></i>
        </div>
    </div>
    
    <div class="mini-stat-card">
        <div class="mini-stat-info">
            <div class="mini-stat-label">Total Requests</div>
            <div class="mini-stat-value text-primary"><?= number_format((int) $stats['total']) ?></div>
        </div>
        <div class="mini-stat-icon text-primary">
            <i class="bi bi-clipboard-data"></i>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-lightning me-2"></i>Quick Actions
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#upgradeUserModal">
                    <i class="bi bi-arrow-up-circle me-2"></i>Upgrade User ke Member
                </button>
            </div>
            <div class="col-md-6">
                <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#demoteMemberModal">
                    <i class="bi bi-arrow-down-circle me-2"></i>Demote Member ke User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="filter-section">
    <span class="filter-label">Filter Status:</span>
    <div class="filter-buttons">
        <a href="?page=membership&status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?page=membership&status=approved" class="filter-btn <?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
        <a href="?page=membership&status=rejected" class="filter-btn <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        <a href="?page=membership" class="filter-btn <?= !$statusFilter ? 'active' : '' ?>">All</a>
    </div>
</div>

<!-- Requests Table -->
<div class="table-container">
    <div class="card-header">
        <i class="bi bi-person-badge me-2"></i>Membership Requests (<?= ucfirst($statusFilter ?: 'All') ?>)
    </div>
    <div class="table-responsive">
        <table class="table datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Current Role</th>
                    <th>Request Type</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td>#<?= (int) $req['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($req['user_name']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($req['user_email']) ?></small>
                        <br><small class="text-muted">GC: <?= number_format((float) $req['gc_balance'], 2) ?></small>
                    </td>
                    <td>
                        <span class="badge <?= $req['user_role'] === 'member' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= ucfirst($req['user_role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $req['request_type'] === 'upgrade' ? 'badge-verified' : 'badge-pending' ?>">
                            <i class="bi bi-arrow-<?= $req['request_type'] === 'upgrade' ? 'up' : 'down' ?>"></i>
                            <?= ucfirst($req['request_type']) ?>
                        </span>
                    </td>
                    <td>
                        <small><?= htmlspecialchars(substr($req['reason'] ?? '-', 0, 50)) ?><?= strlen($req['reason'] ?? '') > 50 ? '...' : '' ?></small>
                    </td>
                    <td>
                        <?php
                        $badgeClass = [
                            'pending' => 'badge-pending',
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected'
                        ][$req['status']] ?? 'badge-pending';
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <?= ucfirst($req['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?= date('d M Y', strtotime($req['created_at'])) ?>
                        <br><small class="text-muted"><?= date('H:i', strtotime($req['created_at'])) ?></small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-sm btn-primary" onclick="viewRequest(<?= htmlspecialchars(json_encode($req)) ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($req['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="approveRequest(<?= (int) $req['id'] ?>)">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?= (int) $req['id'] ?>)">
                                    <i class="bi bi-x-lg"></i>
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

<!-- View Request Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pengajuan Membership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm">
                    <tr><th width="40%">Request ID</th><td id="viewId"></td></tr>
                    <tr><th>User</th><td id="viewUser"></td></tr>
                    <tr><th>Current Role</th><td id="viewCurrentRole"></td></tr>
                    <tr><th>Request Type</th><td id="viewType"></td></tr>
                    <tr><th>Reason</th><td id="viewReason"></td></tr>
                    <tr><th>Status</th><td id="viewStatus"></td></tr>
                    <tr><th>Admin Notes</th><td id="viewAdminNotes"></td></tr>
                    <tr><th>Processed By</th><td id="viewProcessedBy"></td></tr>
                    <tr><th>Date</th><td id="viewDate"></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approveRequestId">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Membership Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        User akan diupgrade menjadi <strong>MEMBER</strong> dan dapat melakukan transaksi.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional):</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan untuk user..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Membership Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Berikan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upgrade User Modal -->
<div class="modal fade" id="upgradeUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="upgrade_user">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Upgrade User ke Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Upgrade manual tanpa perlu pengajuan. Gunakan fitur ini dengan bijak.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pilih User <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Pilih User --</option>
                            <?php foreach ($regularUsers as $user): ?>
                                <option value="<?= (int) $user['id'] ?>">
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>) - GC: <?= number_format((float) $user['gc_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Upgrade to Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Demote Member Modal -->
<div class="modal fade" id="demoteMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="demote_member">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Demote Member ke User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Member akan dikembalikan ke status User dan tidak dapat bertransaksi sampai upgrade lagi.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pilih Member <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Pilih Member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= (int) $member['id'] ?>">
                                    <?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['email']) ?>) - GC: <?= number_format((float) $member['gc_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Yakin demote member ini?')">
                        <i class="bi bi-arrow-down me-1"></i> Demote to User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
'use strict';

const viewRequest = (req) => {
    document.getElementById('viewId').textContent = '#' + req.id;
    document.getElementById('viewUser').textContent = req.user_name + ' (' + req.user_email + ')';
    document.getElementById('viewCurrentRole').innerHTML = '<span class="badge ' + (req.user_role === 'member' ? 'badge-active' : 'badge-inactive') + '">' + req.user_role.toUpperCase() + '</span>';
    document.getElementById('viewType').textContent = req.request_type.toUpperCase();
    document.getElementById('viewReason').textContent = req.reason || '-';
    document.getElementById('viewStatus').innerHTML = '<span class="badge badge-' + req.status + '">' + req.status.toUpperCase() + '</span>';
    document.getElementById('viewAdminNotes').textContent = req.admin_notes || '-';
    document.getElementById('viewProcessedBy').textContent = req.processed_by_name || '-';
    document.getElementById('viewDate').textContent = req.created_at;
    
    new bootstrap.Modal(document.getElementById('viewModal')).show();
};

const approveRequest = (id) => {
    document.getElementById('approveRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
};

const rejectRequest = (id) => {
    document.getElementById('rejectRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
