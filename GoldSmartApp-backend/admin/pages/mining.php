<?php declare(strict_types=1);

require_once __DIR__ . '/../../models/Mining.php';
require_once __DIR__ . '/../../models/User.php';

$miningModel = new Mining();
$userModel = new User();

// Get admin mining stats
$miningStats = $miningModel->getAdminMiningStats();

// Get active mining members
$activeMembers = $miningModel->getActiveMiningMembers();

// Expire old plans (maintenance)
$miningModel->expireOldPlans();

$pageTitle = 'Mining';
include __DIR__ . '/../includes/header.php';
?>

<!-- Mining Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-gem"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format($miningStats['active_packages']) ?></div>
            <div class="stat-label">Paket Aktif</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gold">
            <i class="bi bi-coin"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format($miningStats['total_deposited'], 2) ?></div>
            <div class="stat-label">Total Deposit GC</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format($miningStats['total_claimed'], 2) ?></div>
            <div class="stat-label">Total Diklaim GC</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-gift"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?= number_format($miningStats['today_bonus_claims']) ?></div>
            <div class="stat-label">Bonus Hari Ini (<?= number_format($miningStats['today_bonus_gc'], 2) ?> GC)</div>
        </div>
    </div>
</div>

<!-- Active Mining Members Table -->
<div class="table-container">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-gem me-2"></i>Member Mining Aktif (<?= count($activeMembers) ?>)</span>
    </div>
    <div class="table-responsive">
        <table class="table" id="miningTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Paket</th>
                    <th>Deposit</th>
                    <th>Hari Klaim</th>
                    <th>Total Diklaim</th>
                    <th>Sisa Hari</th>
                    <th>Mulai</th>
                    <th>Berakhir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeMembers)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="bi bi-gem fs-1 d-block mb-2"></i>
                            Belum ada member yang Mining
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeMembers as $member): ?>
                        <?php
                        $daysRemaining = max(0, (int) $member['days_remaining']);
                        $daysClaimed = (int) $member['days_claimed'];
                        $progress = $daysClaimed > 0 ? round(($daysClaimed / 60) * 100) : 0;

                        // Color-code by package
                        $packageColors = [
                            'abonus' => '#4CAF50',
                            'bbonus' => '#2196F3',
                            'cbonus' => '#90A4AE',
                            'vbonus' => '#FFD700',
                            'vipbonus' => '#E040FB',
                        ];
                        $planColor = $packageColors[$member['plan_id']] ?? '#6c757d';
                        ?>
                        <tr>
                            <td>#<?= (int) $member['plan_record_id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge" style="background-color: <?= $planColor ?>20; color: <?= $planColor ?>; font-size: 0.7rem;">
                                        <?= strtoupper(htmlspecialchars($member['user_role'])) ?>
                                    </span>
                                    <?= htmlspecialchars($member['user_name']) ?>
                                </div>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($member['user_email']) ?></small></td>
                            <td>
                                <span class="badge" style="background-color: <?= $planColor ?>; color: #000; font-weight: 600;">
                                    <?= htmlspecialchars($member['plan_name']) ?>
                                </span>
                            </td>
                            <td class="text-end"><?= number_format((float) $member['deposit_gc'], 2) ?> GC</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?= $progress ?>%; background-color: <?= $planColor ?>;"
                                             aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <small class="text-nowrap"><?= $daysClaimed ?>/60</small>
                                </div>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?= number_format((float) $member['total_claimed_gc'], 2) ?> GC
                            </td>
                            <td>
                                <?php if ($daysRemaining <= 7): ?>
                                    <span class="badge bg-warning text-dark"><?= $daysRemaining ?> hari</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= $daysRemaining ?> hari</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d M Y', strtotime($member['start_date'])) ?></small></td>
                            <td><small><?= date('d M Y', strtotime($member['end_date'])) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Package Summary -->
<div class="table-container mt-4">
    <div class="card-header">
        <span><i class="bi bi-bar-chart me-2"></i>Ringkasan Paket</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Paket</th>
                    <th class="text-end">Deposit</th>
                    <th class="text-end">Profit</th>
                    <th class="text-end">Total Return</th>
                    <th class="text-end">Klaim Harian</th>
                    <th class="text-center">Durasi</th>
                    <th class="text-center">Aktif</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $packages = [
                    ['id' => 'abonus', 'name' => 'ABonus', 'deposit' => 100, 'profit' => 8, 'total' => 108, 'daily' => 1.8, 'color' => '#4CAF50'],
                    ['id' => 'bbonus', 'name' => 'BBonus', 'deposit' => 1000, 'profit' => 80, 'total' => 1080, 'daily' => 18, 'color' => '#2196F3'],
                    ['id' => 'cbonus', 'name' => 'CBonus', 'deposit' => 5000, 'profit' => 400, 'total' => 5400, 'daily' => 90, 'color' => '#90A4AE'],
                    ['id' => 'vbonus', 'name' => 'VBonus', 'deposit' => 10000, 'profit' => 800, 'total' => 10800, 'daily' => 180, 'color' => '#FFD700'],
                    ['id' => 'vipbonus', 'name' => 'VIPBonus', 'deposit' => 100000, 'profit' => 8000, 'total' => 108000, 'daily' => 1800, 'color' => '#E040FB'],
                ];

                foreach ($packages as $pkg):
                    // Count active for this package
                    $activeCount = 0;
                    foreach ($activeMembers as $m) {
                        if ($m['plan_id'] === $pkg['id']) {
                            $activeCount++;
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="badge" style="background-color: <?= $pkg['color'] ?>; color: #000; font-weight: 600; font-size: 0.85rem;">
                                <?= $pkg['name'] ?>
                            </span>
                        </td>
                        <td class="text-end"><?= number_format($pkg['deposit']) ?> GC</td>
                        <td class="text-end text-success">+<?= number_format($pkg['profit']) ?> GC</td>
                        <td class="text-end fw-semibold"><?= number_format($pkg['total']) ?> GC</td>
                        <td class="text-end"><?= number_format($pkg['daily'], 1) ?> GC</td>
                        <td class="text-center">60 hari</td>
                        <td class="text-center">
                            <?php if ($activeCount > 0): ?>
                                <span class="badge bg-primary"><?= $activeCount ?> user</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable for mining members
    if ($('#miningTable tbody tr').length > 1 || !$('#miningTable tbody tr td[colspan]').length) {
        $('#miningTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(filter dari _MAX_ total data)',
                zeroRecords: 'Data tidak ditemukan',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Selanjutnya',
                    previous: 'Sebelumnya'
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
