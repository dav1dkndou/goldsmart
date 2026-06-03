<?php declare(strict_types=1);

/** Category Model */
require_once __DIR__ . '/../core/Model.php';

class Category extends Model
{
    protected string $table = 'categories';

    /**
     * Get all active categories
     */
    public function getActive(): array
    {
        return $this->findAll(['is_active' => 1], 'name ASC');
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): array|false
    {
        return $this->findOne(['slug' => $slug]);
    }
}
