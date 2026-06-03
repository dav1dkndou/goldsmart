<?php declare(strict_types=1);

/** Video Model */
require_once __DIR__ . '/../core/Model.php';

class Video extends Model
{
    protected string $table = 'videos';

    /**
     * Get all videos with category (for admin)
     */
    public function getAllWithCategory(): array
    {
        $sql = "SELECT v.*, c.name as category_name 
                FROM {$this->table} v 
                LEFT JOIN video_categories c ON v.category_id = c.id 
                ORDER BY v.created_at DESC";
        return $this->query($sql);
    }

    /**
     * Get all videos with user info (with optional pagination)
     */
    public function getAllWithUser(array $filters = [], ?int $page = null, int $perPage = 15): array
    {
        $baseSql = "FROM {$this->table} v 
                LEFT JOIN users u ON v.user_id = u.id 
                LEFT JOIN video_categories c ON v.category_id = c.id 
                WHERE v.is_active = 1";
        $params = [];

        if (!empty($filters['category'])) {
            $baseSql .= ' AND c.slug = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $baseSql .= ' AND (v.title LIKE :search OR v.description LIKE :search2)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['user_id'])) {
            $baseSql .= ' AND v.user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }

        if ($page !== null) {
            $countStmt = $this->db->prepare("SELECT COUNT(v.id) {$baseSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = "SELECT 
                        v.id, v.user_id, v.category_id, v.title, v.video_url, v.thumbnail_url, 
                        v.duration, v.gc_reward, v.views, v.likes, v.created_at,
                        u.name as user_name, u.avatar as user_avatar, c.name as category_name 
                    {$baseSql} ORDER BY v.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
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

        $sql = "SELECT 
                    v.id, v.user_id, v.category_id, v.title, v.video_url, v.thumbnail_url, 
                    v.duration, v.gc_reward, v.views, v.likes, v.created_at,
                    u.name as user_name, u.avatar as user_avatar, c.name as category_name 
                {$baseSql} ORDER BY v.created_at DESC";
        return $this->query($sql, $params);
    }

    /**
     * Get video by ID with details
     */
    public function getByIdWithDetails(int|string $id): array|false
    {
        $sql = "SELECT v.*, u.name as user_name, u.avatar as user_avatar, c.name as category_name 
                FROM {$this->table} v 
                LEFT JOIN users u ON v.user_id = u.id 
                LEFT JOIN video_categories c ON v.category_id = c.id 
                WHERE v.id = :id AND v.is_active = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Create video
     */
    public function createVideo(array $data): string
    {
        $data['views'] = 0;
        $data['likes'] = 0;
        $data['is_active'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * Increment views
     */
    public function incrementViews(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET views = views + 1 WHERE id = :id LIMIT 1";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Increment likes
     */
    public function incrementLikes(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET likes = likes + 1 WHERE id = :id LIMIT 1";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Decrement likes (minimum 0)
     */
    public function decrementLikes(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET likes = GREATEST(likes - 1, 0) WHERE id = :id LIMIT 1";
        return $this->execute($sql, ['id' => $id]);
    }
}
