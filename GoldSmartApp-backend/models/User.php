<?php declare(strict_types=1);

/** User Model */
require_once __DIR__ . '/../core/Model.php';

class User extends Model
{
    protected string $table = 'users';

    /**
     * Find user by email
     */
    public function findByEmail(string $email): array|false
    {
        return $this->findOne(['email' => $email]);
    }

    /**
     * Create new user
     */
    public function createUser(array $data): string
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!isset($data['role'])) {
            $data['role'] = 'user';
        }
        if (!isset($data['gc_balance'])) {
            $data['gc_balance'] = 0;
        }
        if (!isset($data['total_gc_earned'])) {
            $data['total_gc_earned'] = 0;
        }
        if (!isset($data['total_gc_withdrawn'])) {
            $data['total_gc_withdrawn'] = 0;
        }
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }

        return $this->create($data);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Update GC balance (atomic - prevents race conditions)
     */
    public function updateGCBalance(int|string $userId, float|int $amount, string $type = 'add'): bool
    {
        $db = $this->getDb();

        if ($type === 'add') {
            $sql = 'UPDATE users SET gc_balance = gc_balance + :amount, total_gc_earned = total_gc_earned + :amount2, updated_at = :updated_at WHERE id = :id';
        } else {
            // For subtract, ensure balance doesn't go negative
            $sql = 'UPDATE users SET gc_balance = gc_balance - :amount, total_gc_withdrawn = total_gc_withdrawn + :amount2, updated_at = :updated_at WHERE id = :id AND gc_balance >= :check_amount';
        }

        $params = [
            ':amount' => $amount,
            ':amount2' => $amount,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ];

        if ($type !== 'add') {
            $params[':check_amount'] = $amount;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user profile (without password)
     */
    public function getProfile(int|string $userId): array|false
    {
        $user = $this->findById($userId);
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }

    /**
     * Update profile
     */
    public function updateProfile(int|string $userId, array $data): bool
    {
        $allowed = ['name', 'phone', 'avatar'];
        $updateData = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return false;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');
        return $this->update($userId, $updateData);
    }

    /**
     * Change password
     */
    public function changePassword(int|string $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
