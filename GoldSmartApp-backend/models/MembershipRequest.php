<?php declare(strict_types=1);

/**
 * MembershipRequest Model
 * Handles membership upgrade/downgrade requests
 */
require_once __DIR__ . '/../core/Model.php';

class MembershipRequest extends Model
{
    protected string $table = 'membership_requests';

    /**
     * Get all requests with user details
     */
    public function getAllWithUser(?string $status = null): array
    {
        $sql = "SELECT mr.*, 
                       u.name as user_name, 
                       u.email as user_email, 
                       u.role as user_role,
                       u.gc_balance,
                       a.name as processed_by_name
                FROM {$this->table} mr
                LEFT JOIN users u ON mr.user_id = u.id
                LEFT JOIN users a ON mr.processed_by = a.id";

        if ($status !== null) {
            $sql .= ' WHERE mr.status = ?';
            $sql .= ' ORDER BY mr.created_at DESC';
            return $this->query($sql, [$status]);
        }

        $sql .= ' ORDER BY mr.created_at DESC';
        return $this->query($sql);
    }

    /**
     * Get pending requests for a user
     */
    public function getPendingByUser(int|string $userId): array|false
    {
        return $this->findOne([
            'user_id' => $userId,
            'status' => 'pending'
        ]);
    }

    /**
     * Create membership request
     */
    public function createRequest(int|string $userId, ?string $reason = null, string $type = 'upgrade'): string|false
    {
        // Check if user already has pending request
        $existing = $this->getPendingByUser($userId);
        if ($existing) {
            return false;  // Already has pending request
        }

        return $this->create([
            'user_id' => $userId,
            'request_type' => $type,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Approve request and upgrade user to member
     * Also gives referral bonus if user was referred
     */
    public function approve(int|string $requestId, int|string $adminId, ?string $notes = null): bool
    {
        $request = $this->find($requestId);
        if (!$request || $request['status'] !== 'pending') {
            return false;
        }

        // Get user model
        require_once __DIR__ . '/User.php';
        require_once __DIR__ . '/Config.php';
        require_once __DIR__ . '/ReferralHistory.php';

        $userModel = new User();
        $configModel = new Config();
        $referralHistoryModel = new ReferralHistory();

        // Get user being upgraded
        $user = $userModel->find($request['user_id']);
        if (!$user) {
            return false;
        }

        // Update user role based on request type
        $newRole = $request['request_type'] === 'upgrade' ? 'member' : 'user';
        $userModel->update($request['user_id'], ['role' => $newRole]);

        // === REFERRAL BONUS WHEN UPGRADING TO MEMBER ===
        if ($request['request_type'] === 'upgrade' && !empty($user['referred_by'])) {
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
                        (int) $request['user_id'],
                        $referralBonus,
                        'Bonus referral: ' . $user['name'] . ' upgrade ke Member'
                    );
                }
            }
        }

        // Update request status
        return $this->update($requestId, [
            'status' => 'approved',
            'admin_notes' => $notes,
            'processed_by' => $adminId,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reject request
     */
    public function reject(int|string $requestId, int|string $adminId, ?string $reason = null): bool
    {
        $request = $this->find($requestId);
        if (!$request || $request['status'] !== 'pending') {
            return false;
        }

        return $this->update($requestId, [
            'status' => 'rejected',
            'admin_notes' => $reason,
            'processed_by' => $adminId,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get user's request history
     */
    public function getUserHistory(int|string $userId): array
    {
        return $this->query(
            "SELECT * FROM {$this->table} 
             WHERE user_id = ? 
             ORDER BY created_at DESC",
            [$userId]
        );
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        $stats = $this->query(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN request_type = 'upgrade' THEN 1 ELSE 0 END) as upgrades,
                SUM(CASE WHEN request_type = 'downgrade' THEN 1 ELSE 0 END) as downgrades
             FROM {$this->table}
             LIMIT 1"
        );

        return $stats[0] ?? [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'upgrades' => 0,
            'downgrades' => 0
        ];
    }
}
