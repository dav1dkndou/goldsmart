<?php declare(strict_types=1);

/**
 * ReferralHistory Model
 * Tracks all referral bonuses and commissions
 */
require_once __DIR__ . '/../core/Model.php';

class ReferralHistory extends Model
{
    protected string $table = 'referral_history';

    /**
     * Add signup bonus record (when referred user upgrades to member)
     */
    public function addSignupBonus(int $referrerId, int $referredId, float $gcAmount, ?string $description = null): string|bool
    {
        return $this->create([
            'referrer_id' => $referrerId,
            'referred_id' => $referredId,
            'type' => 'signup_bonus',
            'gc_amount' => $gcAmount,
            'description' => $description ?? 'Bonus referral upgrade member',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Add commission record (when referred user makes transaction)
     */
    public function addCommission(int $referrerId, int $referredId, float $gcAmount, ?int $transactionId = null, ?string $description = null): string|bool
    {
        return $this->create([
            'referrer_id' => $referrerId,
            'referred_id' => $referredId,
            'type' => 'commission',
            'gc_amount' => $gcAmount,
            'transaction_id' => $transactionId,
            'description' => $description ?? 'Komisi transaksi referral',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get all referral history for a user (as referrer)
     */
    public function getByReferrer(int $referrerId, ?string $type = null): array
    {
        $sql = "SELECT rh.*, 
                       u.name as referred_name, 
                       u.email as referred_email,
                       t.order_number
                FROM {$this->table} rh
                LEFT JOIN users u ON rh.referred_id = u.id
                LEFT JOIN transactions t ON rh.transaction_id = t.id
                WHERE rh.referrer_id = ?";

        $params = [$referrerId];

        if ($type) {
            $sql .= ' AND rh.type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY rh.created_at DESC';

        return $this->query($sql, $params);
    }

    /**
     * Get total earnings summary for a referrer
     */
    public function getSummaryByReferrer(int $referrerId): array
    {
        $sql = "SELECT 
                    type,
                    COUNT(*) as total_count,
                    SUM(gc_amount) as total_gc
                FROM {$this->table}
                WHERE referrer_id = ?
                GROUP BY type";

        $results = $this->query($sql, [$referrerId]);

        $summary = [
            'signup_bonus' => ['count' => 0, 'total_gc' => 0.0],
            'commission' => ['count' => 0, 'total_gc' => 0.0],
            'total_gc' => 0.0,
            'total_transactions' => 0
        ];

        foreach ($results as $row) {
            $summary[$row['type']] = [
                'count' => (int) $row['total_count'],
                'total_gc' => (float) $row['total_gc']
            ];
            $summary['total_gc'] += (float) $row['total_gc'];
            $summary['total_transactions'] += (int) $row['total_count'];
        }

        return $summary;
    }

    /**
     * Check if commission already given for a transaction
     */
    public function commissionExists(int $transactionId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE transaction_id = ? AND type = 'commission' LIMIT 1";
        $result = $this->query($sql, [$transactionId]);
        return !empty($result);
    }

    /**
     * Get recent history (for dashboard)
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = "SELECT rh.*, 
                       referrer.name as referrer_name,
                       referred.name as referred_name,
                       t.order_number
                FROM {$this->table} rh
                LEFT JOIN users referrer ON rh.referrer_id = referrer.id
                LEFT JOIN users referred ON rh.referred_id = referred.id
                LEFT JOIN transactions t ON rh.transaction_id = t.id
                ORDER BY rh.created_at DESC
                LIMIT ?";

        return $this->query($sql, [$limit]);
    }
}
