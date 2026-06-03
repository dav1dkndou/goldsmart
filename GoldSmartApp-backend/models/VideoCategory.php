<?php declare(strict_types=1);

/** Video Category Model */
require_once __DIR__ . '/../core/Model.php';

class VideoCategory extends Model
{
    protected string $table = 'video_categories';

    /**
     * Get all active categories
     */
    public function getActive(): array
    {
        return $this->findAll(['is_active' => 1], 'name ASC');
    }
}
