<?php declare(strict_types=1);

/** Transaction Model */
require_once __DIR__ . '/../core/Model.php';

class Transaction extends Model
{
    protected string $table = 'transactions';

    /**
     * Get transactions by user (with optional pagination)
     */
    public function getByUser(int|string $userId, ?int $page = null, int $perPage = 15): array
    {
        $baseSql = "FROM {$this->table} t 
                LEFT JOIN products p ON t.product_id = p.id 
                WHERE t.user_id = :user_id";
        $params = ['user_id' => $userId];

        if ($page !== null) {
            // Count total
            $countStmt = $this->db->prepare("SELECT COUNT(*) {$baseSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = "SELECT t.*, p.name as product_name, p.price as product_price, p.image as product_image 
                    {$baseSql} ORDER BY t.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
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

        $sql = "SELECT t.*, p.name as product_name, p.price as product_price, p.image as product_image 
                {$baseSql} ORDER BY t.created_at DESC";
        return $this->query($sql, $params);
    }

    /**
     * Get transaction by ID with details
     */
    public function getByIdWithDetails(int|string $id, int|string|null $userId = null): array|false
    {
        $sql = "SELECT t.*, p.name as product_name, p.price as product_price, p.image as product_image 
                FROM {$this->table} t 
                LEFT JOIN products p ON t.product_id = p.id 
                WHERE t.id = :id";
        $params = ['id' => $id];

        if ($userId !== null) {
            $sql .= ' AND t.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Create transaction
     */
    public function createTransaction(array $data): string
    {
        // Generate order number
        $data['order_number'] = $this->generateOrderNumber();
        $data['status'] = 'pending';
        $data['price'] = $data['price'] ?? 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * Generate unique order number with proper locking inside transaction
     * Note: Must be called within a database transaction for the lock to be effective
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = date('Ymd');
        $maxRetries = 5;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            // Get today's transaction count (FOR UPDATE is effective inside the caller's transaction)
            $sql = "SELECT COUNT(*) FROM {$this->table} 
                    WHERE DATE(created_at) = CURDATE()
                    FOR UPDATE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $count = (int) $stmt->fetchColumn() + 1 + $attempt;

            $orderNumber = sprintf('%s-%s-%03d', $prefix, $date, $count);

            // Verify this order number doesn't exist
            $checkSql = "SELECT COUNT(*) FROM {$this->table} WHERE order_number = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$orderNumber]);

            if ((int) $checkStmt->fetchColumn() === 0) {
                return $orderNumber;
            }
        }

        // Fallback: use timestamp-based unique order number
        return sprintf('%s-%s-%s', $prefix, $date, substr(uniqid(), -5));
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

        if ($status === 'verified') {
            $updateData['verified_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $updateData);
    }

    /**
     * Upload payment proof
     */
    public function uploadPaymentProof(int|string $id, string $imagePath): bool
    {
        return $this->update($id, [
            'payment_proof' => $imagePath,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Count today's orders for a user
     */
    public function countTodayOrders(int|string $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE user_id = :user_id 
                AND DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get the last order timestamp for a user
     */
    public function getLastOrderTime(int|string $userId): ?string
    {
        $sql = "SELECT created_at FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : null;
    }
}
