<?php declare(strict_types=1);

/** Cart Model */
require_once __DIR__ . '/../core/Model.php';

class Cart extends Model
{
    protected string $table = 'cart';

    /**
     * Get user's cart items with product details
     */
    public function getUserCartWithProducts(int $userId): array
    {
        $sql = "SELECT 
                    c.id,
                    c.user_id,
                    c.product_id,
                    c.quantity,
                    c.created_at,
                    c.updated_at,
                    p.name as product_name,
                    p.description as product_description,
                    p.price,
                    p.gc_bonus as gc_reward,
                    p.stock,
                    p.image,
                    p.is_active as product_is_active,
                    p.items_per_unit,
                    (p.price * c.quantity) as subtotal,
                    (p.gc_bonus * c.quantity) as total_gc_reward
                FROM {$this->table} c
                INNER JOIN products p ON c.product_id = p.id
                WHERE c.user_id = :user_id
                ORDER BY c.created_at DESC";

        return $this->query($sql, ['user_id' => $userId]);
    }

    /**
     * Check if product already in cart for user
     */
    public function findByUserAndProduct(int $userId, int $productId): array|false
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND product_id = :product_id 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
        return $stmt->fetch();
    }

    /**
     * Add item to cart or update quantity if exists
     */
    public function addOrUpdate(int $userId, int $productId, int $quantity): bool
    {
        // Check if item already exists
        $existing = $this->findByUserAndProduct($userId, $productId);

        if ($existing) {
            // Update quantity
            $newQuantity = (int) $existing['quantity'] + $quantity;
            return $this->update($existing['id'], ['quantity' => $newQuantity]);
        }

        // Insert new item - cast to bool for consistent return type
        $result = $this->create([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
        return (bool) $result;
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(int $cartId, int $userId, int $quantity): bool
    {
        if (!$this->verifyOwnership($cartId, $userId)) {
            return false;
        }

        return $this->update($cartId, ['quantity' => $quantity]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(int $cartId, int $userId): bool
    {
        if (!$this->verifyOwnership($cartId, $userId)) {
            return false;
        }

        return $this->delete($cartId);
    }

    /**
     * Clear all items from user's cart
     */
    public function clearUserCart(int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Get cart item count for user
     */
    public function getUserCartCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get cart summary (total items, total price, total GC)
     */
    public function getUserCartSummary(int $userId): array
    {
        $sql = "SELECT 
                    COUNT(c.id) as total_items,
                    COALESCE(SUM(c.quantity), 0) as total_quantity,
                    COALESCE(SUM(p.price * c.quantity), 0) as total_price,
                    COALESCE(SUM(p.gc_bonus * c.quantity), 0) as total_gc_reward
                FROM {$this->table} c
                INNER JOIN products p ON c.product_id = p.id
                WHERE c.user_id = :user_id AND p.is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        return [
            'total_items' => (int) ($result['total_items'] ?? 0),
            'total_quantity' => (int) ($result['total_quantity'] ?? 0),
            'total_price' => (float) ($result['total_price'] ?? 0),
            'total_gc_reward' => (float) ($result['total_gc_reward'] ?? 0)
        ];
    }

    /**
     * Verify cart item ownership (helper method)
     */
    private function verifyOwnership(int $cartId, int $userId): bool
    {
        $item = $this->findById($cartId);
        return $item && (int) $item['user_id'] === $userId;
    }

    /**
     * Update cart item quantity directly (without ownership check - internal use)
     */
    public function setQuantity(int $cartId, int $quantity): bool
    {
        return $this->update($cartId, ['quantity' => $quantity]);
    }

    /**
     * Get total quantity for a specific product in cart
     */
    public function getProductQuantityInCart(int $userId, int $productId): int
    {
        $item = $this->findByUserAndProduct($userId, $productId);
        return $item ? (int) $item['quantity'] : 0;
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(int $userId): bool
    {
        return $this->getUserCartCount($userId) === 0;
    }

    /**
     * Remove multiple items from cart (bulk operation)
     */
    public function removeMultipleItems(int $userId, array $cartIds): bool
    {
        if (empty($cartIds)) {
            return true;
        }

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

        $sql = "DELETE FROM {$this->table} 
                WHERE user_id = ? AND id IN ({$placeholders})";

        $params = array_merge([$userId], $cartIds);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get cart items by product IDs (for validation)
     */
    public function getItemsByProductIds(int $userId, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? AND product_id IN ({$placeholders})";

        $params = array_merge([$userId], $productIds);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
