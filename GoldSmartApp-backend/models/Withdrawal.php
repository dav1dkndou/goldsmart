<?php declare(strict_types=1);

/** Withdrawal Model */
require_once __DIR__ . '/../core/Model.php';

class Withdrawal extends Model
{
    protected string $table = 'withdrawals';

    /**
     * Get withdrawals by user (with optional pagination)
     */
    public function getByUser(int|string $userId, ?int $page = null, int $perPage = 15): array
    {
        $baseSql = "FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        if ($page !== null) {
            $countStmt = $this->db->prepare("SELECT COUNT(*) {$baseSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = "SELECT * {$baseSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
            $data = $this->query($sql, $params);

            return [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $perPage),
                ]
            ];
        }

        $sql = "SELECT * {$baseSql} ORDER BY created_at DESC";
        return $this->query($sql, $params);
    }

    /**
     * Create withdrawal
     */
    public function createWithdrawal(array $data): string
    {
        $data['status'] = 'pending';
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * Update status
     */
    public function updateStatus(int|string $id, string $status, array $additionalData = []): bool
    {
        $updateData = array_merge($additionalData, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($status === 'completed') {
            $updateData['processed_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $updateData);
    }

    /**
     * Check pending withdrawals
     */
    public function hasPendingWithdrawal(int|string $userId): bool
    {
        $count = $this->count([
            'user_id' => $userId,
            'status' => 'pending'
        ]);
        return $count > 0;
    }
}
