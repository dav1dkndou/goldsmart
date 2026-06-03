<?php declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

/**
 * Mining Model V2
 * Handles daily bonus claims, mining packages, and daily package claims
 */
class Mining extends Model
{
    protected string $table = 'mining_plans';

    // ==================== DAILY LOGIN BONUS ====================

    /**
     * Check if user already claimed daily bonus today
     */
    public function hasDailyBonusClaimed(int $userId): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM daily_bonus_claims
                WHERE user_id = :user_id 
                AND DATE(claimed_at) = CURDATE()';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return ((int) ($row['cnt'] ?? 0)) > 0;
    }

    /**
     * Record a daily bonus claim
     */
    public function recordDailyBonusClaim(int $userId, float $amount, string $role): void
    {
        $sql = 'INSERT INTO daily_bonus_claims (user_id, gc_amount, role_at_claim, claimed_at, created_at)
                VALUES (:user_id, :gc_amount, :role, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'gc_amount' => $amount,
            'role' => $role,
        ]);
    }

    // ==================== MINING PACKAGES ====================

    /**
     * Get all active packages for a user
     */
    public function getActivePackages(int $userId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id 
                AND is_active = 1
                AND end_date > NOW()
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get a specific active package by plan_id for a user
     */
    public function getActivePackageByPlanId(int $userId, string $planId): array|false
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id
                AND plan_id = :plan_id
                AND is_active = 1
                AND end_date > NOW()
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'plan_id' => $planId]);
        return $stmt->fetch();
    }

    /**
     * Get package by ID
     */
    public function getPackageById(int $id): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create a mining package
     */
    public function createPackage(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                (user_id, plan_id, plan_name, start_date, end_date, gc_per_session, sessions_per_day, cost_gc,
                 deposit_gc, profit_gc, total_return_gc, daily_claim_gc, days_claimed, total_claimed_gc, is_active, created_at)
                VALUES
                (:user_id, :plan_id, :plan_name, NOW(), DATE_ADD(NOW(), INTERVAL :duration DAY),
                 :gc_per_session, :sessions_per_day, :cost_gc,
                 :deposit_gc, :profit_gc, :total_return_gc, :daily_claim_gc, 0, 0, 1, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'plan_id' => $data['plan_id'],
            'plan_name' => $data['plan_name'],
            'duration' => $data['duration_days'],
            'gc_per_session' => $data['gc_per_session'],
            'sessions_per_day' => $data['sessions_per_day'],
            'cost_gc' => $data['cost_gc'],
            'deposit_gc' => $data['deposit_gc'],
            'profit_gc' => $data['profit_gc'],
            'total_return_gc' => $data['total_return_gc'],
            'daily_claim_gc' => $data['daily_claim_gc'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Check if user already claimed for a package today
     */
    public function hasPackageClaimedToday(int $userId, int $planRecordId): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM mining_daily_claims
                WHERE user_id = :user_id
                AND plan_record_id = :plan_record_id
                AND DATE(claimed_at) = CURDATE()';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'plan_record_id' => $planRecordId]);
        $row = $stmt->fetch();
        return ((int) ($row['cnt'] ?? 0)) > 0;
    }

    /**
     * Record a daily package claim
     */
    public function recordPackageClaim(array $data): int
    {
        $sql = 'INSERT INTO mining_daily_claims
                (user_id, plan_record_id, plan_id, gc_earned, day_number, ad_watched, claimed_at, created_at)
                VALUES
                (:user_id, :plan_record_id, :plan_id, :gc_earned, :day_number, 1, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'plan_record_id' => $data['plan_record_id'],
            'plan_id' => $data['plan_id'],
            'gc_earned' => $data['gc_earned'],
            'day_number' => $data['day_number'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update package counters after a claim
     */
    public function updatePackageClaimCounters(int $planRecordId, float $gcClaimed): void
    {
        $sql = "UPDATE {$this->table}
                SET days_claimed = days_claimed + 1,
                    total_claimed_gc = total_claimed_gc + :gc
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['gc' => $gcClaimed, 'id' => $planRecordId]);
    }

    /**
     * Deactivate a package (completed or expired)
     */
    public function deactivatePackage(int $planRecordId): void
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $planRecordId]);
    }

    /**
     * Get aggregate package stats for a user
     */
    public function getPackageStats(int $userId): array
    {
        $sql = "SELECT
                    COALESCE(SUM(total_claimed_gc), 0) as total_mined,
                    COUNT(*) as total_packages
                FROM {$this->table}
                WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: ['total_mined' => 0, 'total_packages' => 0];
    }

    /**
     * Get claim history (mining_daily_claims)
     */
    public function getClaimHistory(int $userId, int $limit = 50): array
    {
        $sql = 'SELECT * FROM mining_daily_claims
                WHERE user_id = :user_id
                ORDER BY claimed_at DESC
                LIMIT :lmt';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lmt', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Expire old plans (auto-deactivate past end_date)
     */
    public function expireOldPlans(): int
    {
        $sql = "UPDATE {$this->table}
                SET is_active = 0
                WHERE is_active = 1
                AND end_date <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // ==================== ADMIN QUERIES ====================

    /**
     * Get all active mining members with package details
     */
    public function getActiveMiningMembers(): array
    {
        $sql = "SELECT
                    mp.id as plan_record_id,
                    mp.user_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.role as user_role,
                    mp.plan_id,
                    mp.plan_name,
                    mp.deposit_gc,
                    mp.daily_claim_gc,
                    mp.total_return_gc,
                    mp.days_claimed,
                    mp.total_claimed_gc,
                    mp.start_date,
                    mp.end_date,
                    DATEDIFF(mp.end_date, NOW()) as days_remaining,
                    mp.created_at
                FROM {$this->table} mp
                JOIN users u ON u.id = mp.user_id
                WHERE mp.is_active = 1
                AND mp.end_date > NOW()
                ORDER BY mp.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get mining summary stats for admin dashboard
     */
    public function getAdminMiningStats(): array
    {
        // Active packages count
        $sql = "SELECT COUNT(*) as active_count FROM {$this->table} WHERE is_active = 1 AND end_date > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $active = $stmt->fetch();

        // Total deposited
        $sql = "SELECT COALESCE(SUM(deposit_gc), 0) as total_deposited FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $deposited = $stmt->fetch();

        // Total claimed
        $sql = "SELECT COALESCE(SUM(total_claimed_gc), 0) as total_claimed FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $claimed = $stmt->fetch();

        // Today's bonus claims
        $sql = 'SELECT COUNT(*) as today_bonus, COALESCE(SUM(gc_amount), 0) as today_bonus_gc 
                FROM daily_bonus_claims WHERE DATE(claimed_at) = CURDATE()';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $todayBonus = $stmt->fetch();

        return [
            'active_packages' => (int) ($active['active_count'] ?? 0),
            'total_deposited' => (float) ($deposited['total_deposited'] ?? 0),
            'total_claimed' => (float) ($claimed['total_claimed'] ?? 0),
            'today_bonus_claims' => (int) ($todayBonus['today_bonus'] ?? 0),
            'today_bonus_gc' => (float) ($todayBonus['today_bonus_gc'] ?? 0),
        ];
    }
}
