<?php declare(strict_types=1);

/** App Configuration */
require_once __DIR__ . '/../../models/Config.php';

$configModel = new Config();
$message = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configs = [
        'gc_price' => filter_input(INPUT_POST, 'gc_price', FILTER_SANITIZE_NUMBER_INT),
        'min_withdrawal' => filter_input(INPUT_POST, 'min_withdrawal', FILTER_SANITIZE_NUMBER_INT),
        'withdrawal_fee' => filter_input(INPUT_POST, 'withdrawal_fee', FILTER_SANITIZE_NUMBER_INT),
        'referral_bonus' => filter_input(INPUT_POST, 'referral_bonus', FILTER_SANITIZE_SPECIAL_CHARS),
        'commission_bonus' => filter_input(INPUT_POST, 'commission_bonus', FILTER_SANITIZE_SPECIAL_CHARS),
        'maintenance_mode' => filter_input(INPUT_POST, 'maintenance_mode') !== null ? '1' : '0'
    ];

    $success = true;
    foreach ($configs as $key => $value) {
        if (!$configModel->setValue($key, $value)) {
            $success = false;
            break;
        }
    }

    if ($success) {
        $message = 'Konfigurasi berhasil disimpan';
    } else {
        $message = 'Gagal menyimpan beberapa konfigurasi';
    }
}

// Get all configs
$allConfigs = $configModel->findAll();
$configValues = [];
foreach ($allConfigs as $c) {
    if (isset($c['config_key']) && isset($c['config_value'])) {
        $configValues[$c['config_key']] = $c['config_value'];
    }
}

// Defaults
$defaults = [
    'gc_price' => '10000',
    'min_withdrawal' => '50',
    'withdrawal_fee' => '7000',
    'referral_bonus' => '5',
    'commission_bonus' => '0.5',
    'maintenance_mode' => '0'
];

foreach ($defaults as $key => $value) {
    if (!isset($configValues[$key])) {
        $configValues[$key] = $value;
    }
}

$pageTitle = 'Configuration';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <!-- GC Settings -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-coin me-2"></i>Pengaturan GC (Gold Cash)
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Harga per GC (Rupiah)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="gc_price" class="form-control" 
                                   value="<?= htmlspecialchars($configValues['gc_price']) ?>" required min="1">
                        </div>
                        <small class="text-muted">Nilai 1 GC dalam Rupiah</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimum Withdrawal (GC)</label>
                        <div class="input-group">
                            <input type="number" name="min_withdrawal" class="form-control" 
                                   value="<?= htmlspecialchars($configValues['min_withdrawal']) ?>" required min="1">
                            <span class="input-group-text">GC</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Withdrawal Fee (Rupiah)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="withdrawal_fee" class="form-control" 
                                   value="<?= htmlspecialchars($configValues['withdrawal_fee']) ?>" required min="0">
                        </div>
                        <small class="text-muted">Biaya admin tetap untuk setiap penarikan</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Referral & Maintenance -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-gift me-2"></i>Pengaturan Referral
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Referral Bonus</label>
                        <div class="input-group">
                            <input type="number" name="referral_bonus" class="form-control" 
                                   value="<?= htmlspecialchars($configValues['referral_bonus']) ?>" required min="0" step="0.01">
                            <span class="input-group-text">GC</span>
                        </div>
                        <small class="text-muted">Bonus GC untuk setiap referral yang upgrade jadi Member</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Commission Bonus</label>
                        <div class="input-group">
                            <input type="number" name="commission_bonus" class="form-control" 
                                   value="<?= htmlspecialchars($configValues['commission_bonus']) ?>" required min="0" step="0.01">
                            <span class="input-group-text">GC</span>
                        </div>
                        <small class="text-muted">Bonus GC untuk setiap transaksi user yang diajak (per transaksi)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Mode -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-tools me-2"></i>Mode Maintenance
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="maintenance_mode" class="form-check-input" 
                               id="maintenanceMode" <?= $configValues['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="maintenanceMode">
                            <strong>Aktifkan Maintenance Mode</strong>
                        </label>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Jika aktif, semua user dan member tidak bisa menggunakan aplikasi. Hanya admin yang bisa mengakses.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Info -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>Info
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Harga GC</th>
                            <td>Rp <?= number_format((float) $configValues['gc_price']) ?>/GC</td>
                        </tr>
                        <tr>
                            <th>Min. Withdrawal</th>
                            <td><?= number_format((float) $configValues['min_withdrawal']) ?> GC = Rp <?= number_format((float) $configValues['min_withdrawal'] * (float) $configValues['gc_price']) ?></td>
                        </tr>
                        <tr>
                            <th>Fee Withdrawal</th>
                            <td>Rp <?= number_format((float) $configValues['withdrawal_fee']) ?></td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info mb-0">
                        <small>
                            <?php
                            $minWd = (float) $configValues['min_withdrawal'];
                            $gcPrice = (float) $configValues['gc_price'];
                            $fee = (float) $configValues['withdrawal_fee'];
                            $totalRp = $minWd * $gcPrice;
                            $finalAmount = $totalRp - $fee;
                            ?>
                            <i class="bi bi-lightbulb"></i> 
                            Contoh: User withdraw <?= number_format($minWd) ?> GC<br>
                            = Rp <?= number_format($totalRp) ?> - Fee (Rp <?= number_format($fee) ?>)<br>
                            = Rp <?= number_format($finalAmount) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-end">
        <button type="submit" class="btn btn-gold btn-lg">
            <i class="bi bi-save me-2"></i>Simpan Konfigurasi
        </button>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
