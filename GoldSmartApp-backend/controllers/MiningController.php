<?php declare(strict_types=1);

/**
 * Mining Controller V2
 *
 * Two systems:
 * 1. Daily Login Bonus (displayed on Home):
 *    - Free users: 0.1 GC/day (1x claim)
 *    - Members: 0.2 GC/day (1x claim)
 *
 * 2. Mining Packages (displayed in Mining tab, MEMBER ONLY):
 *    - ABonus:   deposit 100 GC,   profit 8 GC,     total 108 GC,    daily 1.8 GC,   60 days
 *    - BBonus:   deposit 1000 GC,  profit 80 GC,    total 1080 GC,   daily 18 GC,    60 days
 *    - CBonus:   deposit 5000 GC,  profit 400 GC,   total 5400 GC,   daily 90 GC,    60 days
 *    - VBonus:   deposit 10000 GC, profit 800 GC,   total 10800 GC,  daily 180 GC,   60 days
 *    - VIPBonus: deposit 100000 GC,profit 8000 GC,  total 108000 GC, daily 1800 GC,  60 days
 *
 *    Claim 1x per day, shows ad before claim.
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Mining.php';
require_once __DIR__ . '/../models/User.php';

class MiningController extends Controller
{
    private Mining $miningModel;
    private User $userModel;

    // Daily login bonus amounts
    private const DAILY_BONUS = [
        'user' => 0.1,
        'member' => 0.2,
    ];

    // Mining packages (member-only, 60 days)
    private const PACKAGES = [
        'abonus' => [
            'id' => 'abonus',
            'name' => 'ABonus',
            'deposit' => 100,
            'profit' => 8,
            'total_return' => 108,
            'duration_days' => 60,
            'daily_claim' => 1.8,
            'requires_member' => true,
        ],
        'bbonus' => [
            'id' => 'bbonus',
            'name' => 'BBonus',
            'deposit' => 1000,
            'profit' => 80,
            'total_return' => 1080,
            'duration_days' => 60,
            'daily_claim' => 18,
            'requires_member' => true,
        ],
        'cbonus' => [
            'id' => 'cbonus',
            'name' => 'CBonus',
            'deposit' => 5000,
            'profit' => 400,
            'total_return' => 5400,
            'duration_days' => 60,
            'daily_claim' => 90,
            'requires_member' => true,
        ],
        'vbonus' => [
            'id' => 'vbonus',
            'name' => 'VBonus',
            'deposit' => 10000,
            'profit' => 800,
            'total_return' => 10800,
            'duration_days' => 60,
            'daily_claim' => 180,
            'requires_member' => true,
        ],
        'vipbonus' => [
            'id' => 'vipbonus',
            'name' => 'VIPBonus',
            'deposit' => 100000,
            'profit' => 8000,
            'total_return' => 108000,
            'duration_days' => 60,
            'daily_claim' => 1800,
            'requires_member' => true,
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->miningModel = new Mining();
        $this->userModel = new User();
    }

    // ==================== DAILY LOGIN BONUS ====================

    /**
     * Get daily bonus status
     * GET /api/mining/daily-bonus
     */
    public function dailyBonusStatus(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];

        try {
            $user = $this->userModel->findById($userId);
            $role = $user['role'] ?? 'user';
            $amount = self::DAILY_BONUS[$role] ?? self::DAILY_BONUS['user'];
            $claimed = $this->miningModel->hasDailyBonusClaimed($userId);

            Response::success([
                'role' => $role,
                'amount' => $amount,
                'claimed_today' => $claimed,
                'claimedToday' => $claimed,
            ]);
        } catch (\Exception $e) {
            error_log('Daily bonus status error: ' . $e->getMessage());
            Response::error('Gagal mengambil data bonus harian', 500);
        }
    }

    /**
     * Claim daily login bonus
     * POST /api/mining/daily-bonus
     */
    public function claimDailyBonus(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];

        try {
            // Check if already claimed today
            if ($this->miningModel->hasDailyBonusClaimed($userId)) {
                Response::error('Bonus harian sudah diklaim hari ini. Kembali besok!', 400);
                return;
            }

            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::error('User tidak ditemukan', 404);
                return;
            }

            $role = $user['role'] ?? 'user';
            $amount = self::DAILY_BONUS[$role] ?? self::DAILY_BONUS['user'];

            // Atomic: record claim + credit balance
            $this->miningModel->beginTransaction();

            $this->miningModel->recordDailyBonusClaim($userId, $amount, $role);
            $this->userModel->updateGCBalance($userId, $amount, 'add');

            $this->miningModel->commit();

            $freshUser = $this->userModel->findById($userId);
            $newBalance = (float) ($freshUser['gc_balance'] ?? 0);

            Response::success([
                'gc_earned' => $amount,
                'gcEarned' => $amount,
                'new_balance' => $newBalance,
                'newBalance' => $newBalance,
                'role' => $role,
            ], "Bonus harian +{$amount} GC berhasil diklaim!");
        } catch (\Exception $e) {
            $this->miningModel->rollBack();
            error_log('Daily bonus claim error: ' . $e->getMessage());
            Response::error('Gagal mengklaim bonus harian: ' . $e->getMessage(), 500);
        }
    }

    // ==================== MINING PACKAGES ====================

    /**
     * Get mining stats (packages overview)
     * GET /api/mining/stats
     */
    public function stats(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];

        try {
            // Expire old plans
            $this->miningModel->expireOldPlans();

            // Get all active packages for this user
            $activePackages = $this->miningModel->getActivePackages($userId);
            $totalStats = $this->miningModel->getPackageStats($userId);

            $packagesData = [];
            foreach ($activePackages as $pkg) {
                $planDef = self::PACKAGES[$pkg['plan_id']] ?? null;
                $todayClaimed = $this->miningModel->hasPackageClaimedToday($userId, (int) $pkg['id']);

                $packagesData[] = [
                    'id' => (int) $pkg['id'],
                    'plan_id' => $pkg['plan_id'],
                    'planId' => $pkg['plan_id'],
                    'plan_name' => $pkg['plan_name'],
                    'planName' => $pkg['plan_name'],
                    'deposit_gc' => (float) $pkg['deposit_gc'],
                    'depositGc' => (float) $pkg['deposit_gc'],
                    'daily_claim_gc' => (float) $pkg['daily_claim_gc'],
                    'dailyClaimGc' => (float) $pkg['daily_claim_gc'],
                    'total_return_gc' => (float) $pkg['total_return_gc'],
                    'totalReturnGc' => (float) $pkg['total_return_gc'],
                    'days_claimed' => (int) $pkg['days_claimed'],
                    'daysClaimed' => (int) $pkg['days_claimed'],
                    'total_claimed_gc' => (float) $pkg['total_claimed_gc'],
                    'totalClaimedGc' => (float) $pkg['total_claimed_gc'],
                    'duration_days' => $planDef ? $planDef['duration_days'] : 60,
                    'durationDays' => $planDef ? $planDef['duration_days'] : 60,
                    'start_date' => $pkg['start_date'],
                    'startDate' => $pkg['start_date'],
                    'end_date' => $pkg['end_date'],
                    'endDate' => $pkg['end_date'],
                    'is_active' => (bool) $pkg['is_active'],
                    'isActive' => (bool) $pkg['is_active'],
                    'claimed_today' => $todayClaimed,
                    'claimedToday' => $todayClaimed,
                    'can_claim' => !$todayClaimed && (int) $pkg['days_claimed'] < ($planDef ? $planDef['duration_days'] : 60),
                    'canClaim' => !$todayClaimed && (int) $pkg['days_claimed'] < ($planDef ? $planDef['duration_days'] : 60),
                ];
            }

            Response::success([
                'total_mined' => (float) ($totalStats['total_mined'] ?? 0),
                'totalMined' => (float) ($totalStats['total_mined'] ?? 0),
                'total_packages' => (int) ($totalStats['total_packages'] ?? 0),
                'totalPackages' => (int) ($totalStats['total_packages'] ?? 0),
                'active_packages' => $packagesData,
                'activePackages' => $packagesData,
            ]);
        } catch (\Exception $e) {
            error_log('Mining stats error: ' . $e->getMessage());
            Response::error('Gagal mengambil data mining: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activate a mining package
     * POST /api/mining/activate
     */
    public function activate(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];
        $data = $this->getRequestBody();

        $planId = $data['plan_id'] ?? '';
        if (!isset(self::PACKAGES[$planId])) {
            Response::error('Paket tidak ditemukan', 400);
            return;
        }

        $package = self::PACKAGES[$planId];

        try {
            // Check member requirement
            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::error('User tidak ditemukan', 404);
                return;
            }

            if ($package['requires_member'] && ($user['role'] ?? 'user') !== 'member') {
                Response::error('Paket ini hanya tersedia untuk Member. Upgrade akun kamu terlebih dahulu.', 403);
                return;
            }

            // Check balance for deposit
            $currentBalance = (float) $user['gc_balance'];
            if ($currentBalance < $package['deposit']) {
                Response::error(
                    "Saldo GC tidak cukup. Dibutuhkan {$package['deposit']} GC, saldo kamu: {$currentBalance} GC",
                    400
                );
                return;
            }

            // Check if user already has this same package active
            $existingSame = $this->miningModel->getActivePackageByPlanId($userId, $planId);
            if ($existingSame) {
                Response::error("Kamu sudah memiliki paket {$package['name']} yang masih aktif.", 400);
                return;
            }

            // Atomic: deduct deposit + create package
            $this->miningModel->beginTransaction();

            $deducted = $this->userModel->updateGCBalance($userId, $package['deposit'], 'subtract');
            if (!$deducted) {
                $this->miningModel->rollBack();
                Response::error('Gagal memotong saldo GC. Saldo mungkin tidak mencukupi.', 400);
                return;
            }

            $planRecordId = $this->miningModel->createPackage([
                'user_id' => $userId,
                'plan_id' => $package['id'],
                'plan_name' => $package['name'],
                'duration_days' => $package['duration_days'],
                'gc_per_session' => $package['daily_claim'],
                'sessions_per_day' => 1,
                'cost_gc' => $package['deposit'],
                'deposit_gc' => $package['deposit'],
                'profit_gc' => $package['profit'],
                'total_return_gc' => $package['total_return'],
                'daily_claim_gc' => $package['daily_claim'],
            ]);

            $this->miningModel->commit();

            $freshUser = $this->userModel->findById($userId);
            $newBalance = (float) ($freshUser['gc_balance'] ?? 0);

            Response::success([
                'plan_record_id' => $planRecordId,
                'gc_deducted' => $package['deposit'],
                'gcDeducted' => $package['deposit'],
                'new_balance' => $newBalance,
                'newBalance' => $newBalance,
            ], "Paket {$package['name']} aktif! {$package['deposit']} GC telah di-deposit. Kamu akan mendapatkan {$package['daily_claim']} GC per hari selama {$package['duration_days']} hari.");
        } catch (\Exception $e) {
            $this->miningModel->rollBack();
            error_log('Mining activate error: ' . $e->getMessage());
            Response::error('Gagal mengaktifkan paket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Claim daily mining reward for a package
     * POST /api/mining/claim
     */
    public function claim(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];
        $data = $this->getRequestBody();

        $planRecordId = (int) ($data['plan_record_id'] ?? 0);
        if ($planRecordId <= 0) {
            Response::error('Plan record ID diperlukan', 400);
            return;
        }

        try {
            // Get the package
            $package = $this->miningModel->getPackageById($planRecordId);
            if (!$package || (int) $package['user_id'] !== $userId) {
                Response::error('Paket tidak ditemukan', 404);
                return;
            }

            if (!(bool) $package['is_active']) {
                Response::error('Paket sudah tidak aktif', 400);
                return;
            }

            $planDef = self::PACKAGES[$package['plan_id']] ?? null;
            $maxDays = $planDef ? $planDef['duration_days'] : 60;

            // Check if already claimed max days
            if ((int) $package['days_claimed'] >= $maxDays) {
                Response::error('Paket sudah selesai. Semua reward sudah diklaim.', 400);
                return;
            }

            // Check if already claimed today
            if ($this->miningModel->hasPackageClaimedToday($userId, $planRecordId)) {
                Response::error('Sudah klaim hari ini. Kembali besok!', 400);
                return;
            }

            $dailyClaim = (float) $package['daily_claim_gc'];
            $dayNumber = (int) $package['days_claimed'] + 1;

            // Atomic: record claim + credit balance + update package counters
            $this->miningModel->beginTransaction();

            $this->miningModel->recordPackageClaim([
                'user_id' => $userId,
                'plan_record_id' => $planRecordId,
                'plan_id' => $package['plan_id'],
                'gc_earned' => $dailyClaim,
                'day_number' => $dayNumber,
            ]);

            $this->miningModel->updatePackageClaimCounters($planRecordId, $dailyClaim);

            $this->userModel->updateGCBalance($userId, $dailyClaim, 'add');

            // If this was the last day, deactivate the package
            if ($dayNumber >= $maxDays) {
                $this->miningModel->deactivatePackage($planRecordId);
            }

            $this->miningModel->commit();

            $freshUser = $this->userModel->findById($userId);
            $newBalance = (float) ($freshUser['gc_balance'] ?? 0);

            Response::success([
                'gc_earned' => $dailyClaim,
                'gcEarned' => $dailyClaim,
                'day_number' => $dayNumber,
                'dayNumber' => $dayNumber,
                'days_remaining' => $maxDays - $dayNumber,
                'daysRemaining' => $maxDays - $dayNumber,
                'new_balance' => $newBalance,
                'newBalance' => $newBalance,
            ], "Klaim hari ke-{$dayNumber} berhasil! +{$dailyClaim} GC");
        } catch (\Exception $e) {
            $this->miningModel->rollBack();
            error_log('Mining claim error: ' . $e->getMessage());
            Response::error('Gagal klaim reward: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get mining history (daily claims)
     * GET /api/mining/history
     */
    public function history(): void
    {
        $authUser = $this->requireAuth();
        $userId = (int) $authUser['user_id'];

        try {
            $history = $this->miningModel->getClaimHistory($userId);

            $formatted = array_map(function ($claim) {
                return [
                    'id' => (int) $claim['id'],
                    'plan_id' => $claim['plan_id'],
                    'planId' => $claim['plan_id'],
                    'day_number' => (int) $claim['day_number'],
                    'dayNumber' => (int) $claim['day_number'],
                    'gc_earned' => (float) $claim['gc_earned'],
                    'gcEarned' => (float) $claim['gc_earned'],
                    'ad_watched' => (bool) $claim['ad_watched'],
                    'adWatched' => (bool) $claim['ad_watched'],
                    'claimed_at' => $claim['claimed_at'],
                    'claimedAt' => $claim['claimed_at'],
                    'created_at' => $claim['created_at'],
                    'createdAt' => $claim['created_at'],
                ];
            }, $history);

            Response::success($formatted);
        } catch (\Exception $e) {
            error_log('Mining history error: ' . $e->getMessage());
            Response::error('Gagal mengambil histori mining: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available packages
     * GET /api/mining/plans
     */
    public function plans(): void
    {
        $this->requireAuth();
        Response::success(array_values(self::PACKAGES));
    }

    /**
     * Get all active mining members (ADMIN only)
     * GET /api/mining/members
     */
    public function activeMembers(): void
    {
        $authUser = $this->requireAuth();

        // Check admin role
        $user = $this->userModel->findById((int) $authUser['user_id']);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::forbidden('Hanya admin yang dapat mengakses data ini.');
            return;
        }

        try {
            $members = $this->miningModel->getActiveMiningMembers();
            Response::success($members);
        } catch (\Exception $e) {
            error_log('Mining active members error: ' . $e->getMessage());
            Response::error('Gagal mengambil data member aktif: ' . $e->getMessage(), 500);
        }
    }

    // Legacy compatibility: redirect old start to claim
    public function start(): void
    {
        $this->claim();
    }
}
