<?php declare(strict_types=1);

/** Product Model */
require_once __DIR__ . '/../core/Model.php';

class Product extends Model
{
    protected string $table = 'products';

    /**
     * Get all products with category (with optional pagination)
     */
    public function getAllWithCategory(array $filters = [], ?int $page = null, int $perPage = 15): array
    {
        $baseSql = "FROM {$this->table} p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.is_active = 1";
        $params = [];

        if (!empty($filters['category'])) {
            $baseSql .= ' AND c.slug = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $baseSql .= ' AND (p.name LIKE :search OR p.description LIKE :search2)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }

        if ($page !== null) {
            $countStmt = $this->db->prepare("SELECT COUNT(p.id) {$baseSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            // Optimized: Select only specific columns instead of p.*
            $sql = "SELECT 
                        p.id, p.category_id, p.name, p.price, p.gc_bonus, 
                        p.stock, p.items_per_unit, p.image, p.is_active, p.is_featured, p.created_at,
                        c.name as category_name, c.slug as category_slug 
                    {$baseSql} ORDER BY p.is_featured DESC, p.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
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

        // Optimized: Select only specific columns instead of p.*
        $sql = "SELECT 
                    p.id, p.category_id, p.name, p.price, p.gc_bonus, 
                    p.stock, p.items_per_unit, p.image, p.is_active, p.is_featured, p.created_at,
                    c.name as category_name, c.slug as category_slug 
                {$baseSql} ORDER BY p.is_featured DESC, p.created_at DESC";
        return $this->query($sql, $params);
    }

    /**
     * Get product by ID with category
     */
    public function getByIdWithCategory(int|string $id): array|false
    {
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
                FROM {$this->table} p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = :id AND p.is_active = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get featured products
     */
    public function getFeatured(int $limit = 10): array
    {
        $sql = "SELECT p.*, c.name as category_name 
                FROM {$this->table} p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.is_active = 1 AND p.is_featured = 1 
                ORDER BY p.created_at DESC 
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Decrease stock based on items_per_unit
     * @param int|string $productId Product ID
     * @param int $quantity Quantity purchased (not items)
     * @return bool|array Returns false on failure, or array with stock info on success
     */
    public function decreaseStock(int|string $productId, int $quantity): bool|array
    {
        $product = $this->findById($productId);
        if (!$product) {
            return false;
        }

        // Calculate actual items to deduct based on items_per_unit
        $itemsPerUnit = (int) ($product['items_per_unit'] ?? 1);
        $totalItemsToDeduct = $quantity * $itemsPerUnit;

        if ((int) $product['stock'] < $totalItemsToDeduct) {
            return false;
        }

        $newStock = (int) $product['stock'] - $totalItemsToDeduct;

        $success = $this->update($productId, [
            'stock' => $newStock,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($success) {
            return [
                'items_per_unit' => $itemsPerUnit,
                'total_items_deducted' => $totalItemsToDeduct,
                'new_stock' => $newStock
            ];
        }

        return false;
    }

    /**
     * Check if stock is sufficient for purchase
     * @param int|string $productId Product ID
     * @param int $quantity Quantity to purchase
     * @return array Stock availability info
     */
    public function checkStockAvailability(int|string $productId, int $quantity): array
    {
        $product = $this->findById($productId);
        if (!$product) {
            return ['available' => false, 'message' => 'Produk tidak ditemukan'];
        }

        $itemsPerUnit = (int) ($product['items_per_unit'] ?? 1);
        $totalItemsNeeded = $quantity * $itemsPerUnit;
        $currentStock = (int) $product['stock'];

        return [
            'available' => $currentStock >= $totalItemsNeeded,
            'current_stock' => $currentStock,
            'items_per_unit' => $itemsPerUnit,
            'total_items_needed' => $totalItemsNeeded,
            'max_purchasable_qty' => $itemsPerUnit > 0 ? floor($currentStock / $itemsPerUnit) : 0
        ];
    }
}
