<?php declare(strict_types=1);

/**
 * Video Controller
 * Handles video operations (member feature)
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/VideoCategory.php';

class VideoController extends Controller
{
    private Video $videoModel;
    private VideoCategory $videoCategoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->videoModel = new Video();
        $this->videoCategoryModel = new VideoCategory();
    }

    /**
     * Get all videos
     * GET /api/videos
     */
    public function index(): void
    {
        $filters = [
            'category' => $_GET['category'] ?? null,
            'search' => $_GET['search'] ?? null,
            'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : null
        ];
        
        error_log('Video GET requested: ' . print_r($_GET, true) . ' Filters: ' . print_r($filters, true));

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: null;
        $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT) ?: 15;
        $perPage = min(max(1, $perPage), 50); // Enforce strict limit

        $result = $this->videoModel->getAllWithUser($filters, $page, $perPage);
        Response::success($result);
    }

    /**
     * Get single video
     * GET /api/videos/{id}
     */
    public function show($id): void
    {
        $video = $this->videoModel->getByIdWithDetails((int) $id);

        if (!$video) {
            Response::notFound('Video tidak ditemukan');
        }

        // Check if current user has claimed this video
        $video['is_claimed'] = false;

        // Get auth user if logged in (optional)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader !== '' && strpos($authHeader, 'Bearer ') === 0) {
            try {
                $token = substr($authHeader, 7);
                $decoded = JWT::decode($token);

                // Check if user has claimed (optimized with fetchColumn)
                $stmt = $this->db->prepare('SELECT 1 FROM video_rewards WHERE user_id = ? AND video_id = ? LIMIT 1');
                $stmt->execute([$decoded['user_id'], (int) $id]);
                $video['is_claimed'] = (bool) $stmt->fetchColumn();
            } catch (Exception $e) {
                // Ignore auth errors for public endpoint
            }
        }

        Response::success($video);
    }

    /**
     * Create new video (member only)
     * POST /api/videos
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();

        // Check if user is member
        if ($authUser['role'] !== 'member') {
            Response::forbidden('Hanya member yang dapat upload video');
        }

        $data = $this->getRequestBody();

        // Validate
        $errors = $this->validate($data, [
            'title' => 'required|min:3|max:200'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Handle thumbnail base64 if provided
        $thumbnailUrl = null;
        if (!empty($data['thumbnail_base64'])) {
            $imageData = $data['thumbnail_base64'];

            if (strpos($imageData, 'base64,') !== false) {
                $imageData = explode('base64,', $imageData)[1];
            }

            $imageContent = base64_decode($imageData, true);
            if ($imageContent !== false) {
                $uploadDir = UPLOAD_PATH . 'thumbnails/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = 'thumb_' . time() . '_' . uniqid() . '.jpg';
                $filepath = $uploadDir . $filename;

                if (file_put_contents($filepath, $imageContent) !== false) {
                    $thumbnailUrl = 'uploads/thumbnails/' . $filename;
                }
            }
        }

        // Create video
        try {
            $videoId = $this->videoModel->createVideo([
                'user_id' => $authUser['user_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
                'video_url' => $data['video_url'] ?? null,
                'thumbnail_url' => $thumbnailUrl,
                'duration' => isset($data['duration']) ? (int) $data['duration'] : 0
            ]);

            $video = $this->videoModel->getByIdWithDetails($videoId);
            Response::success($video, 'Video berhasil diupload', 201);
        } catch (Exception $e) {
            Response::error('Gagal upload video: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Increment video views (rate-limited per IP/user)
     * POST /api/videos/{id}/view
     */
    public function incrementView($id): void
    {
        $videoId = (int) $id;
        $video = $this->videoModel->findById($videoId);

        if (!$video) {
            Response::notFound('Video tidak ditemukan');
        }

        // Rate limit: 1 view per video per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cacheKey = "view_{$videoId}_{$ip}";
        $stmt = $this->db->prepare(
            'SELECT 1 FROM video_views_log WHERE video_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1'
        );

        try {
            $stmt->execute([$videoId, $ip]);
            if (!$stmt->fetchColumn()) {
                // Log view and increment
                $logStmt = $this->db->prepare(
                    'INSERT IGNORE INTO video_views_log (video_id, ip_address, created_at) VALUES (?, ?, NOW())'
                );
                $logStmt->execute([$videoId, $ip]);
                $this->videoModel->incrementViews($videoId);
            }
        } catch (Exception $e) {
            // If view log table doesn't exist, just increment (backward compatible)
            $this->videoModel->incrementViews($videoId);
        }

        Response::success(['views' => (int) $video['views'] + 1], 'View recorded');
    }

    /**
     * Toggle like video (prevents duplicate likes)
     * POST /api/videos/{id}/like
     */
    public function like($id): void
    {
        $authUser = $this->requireAuth();

        $videoId = (int) $id;
        $video = $this->videoModel->findById($videoId);

        if (!$video) {
            Response::notFound('Video tidak ditemukan');
        }

        $userId = $authUser['user_id'];

        // Check if user already liked this video
        $stmt = $this->db->prepare(
            'SELECT id FROM video_likes WHERE video_id = ? AND user_id = ? LIMIT 1'
        );

        try {
            $stmt->execute([$videoId, $userId]);
            $existingLike = $stmt->fetchColumn();

            if ($existingLike) {
                // Unlike: remove like and decrement
                $deleteStmt = $this->db->prepare('DELETE FROM video_likes WHERE video_id = ? AND user_id = ? LIMIT 1');
                $deleteStmt->execute([$videoId, $userId]);
                $this->videoModel->decrementLikes($videoId);
                $newLikes = max(0, (int) $video['likes'] - 1);
                Response::success(['likes' => $newLikes, 'liked' => false], 'Like dihapus');
            } else {
                // Like: add like and increment
                $insertStmt = $this->db->prepare('INSERT INTO video_likes (video_id, user_id, created_at) VALUES (?, ?, NOW())');
                $insertStmt->execute([$videoId, $userId]);
                $this->videoModel->incrementLikes($videoId);
                $newLikes = (int) $video['likes'] + 1;
                Response::success(['likes' => $newLikes, 'liked' => true], 'Video disukai');
            }
        } catch (Exception $e) {
            // Fallback if video_likes table doesn't exist yet (backward compatible)
            $this->videoModel->incrementLikes($videoId);
            Response::success(['likes' => (int) $video['likes'] + 1, 'liked' => true], 'Video liked');
        }
    }

    /**
     * Get video categories
     * GET /api/videos/categories
     */
    public function categories(): void
    {
        $categories = $this->videoCategoryModel->getActive();
        Response::success($categories, 'Success', 200, 86400); // Cache categories for 1 day
    }

    /**
     * Claim video reward
     * POST /api/videos/{id}/claim-reward
     * DISABLED - Reward feature is currently disabled
     */
    public function claimReward($id): void
    {
        // Feature disabled
        Response::error('Fitur reward video sementara dinonaktifkan', 400);
    }

    /**
     * Add comment to video
     * POST /api/videos/{id}/comments
     */
    public function addComment($id): void
    {
        $authUser = $this->requireAuth();
        $data = $this->getRequestBody();

        $videoId = (int) $id;
        $video = $this->videoModel->findById($videoId);

        if (!$video) {
            Response::notFound('Video tidak ditemukan');
        }

        // Better validation
        if (empty($data['content']) || mb_strlen(trim($data['content'])) === 0) {
            Response::error('Komentar tidak boleh kosong', 400);
        }

        try {
            // Insert comment to database
            $stmt = $this->db->prepare('
                INSERT INTO video_comments (video_id, user_id, comment) 
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$videoId, $authUser['user_id'], trim($data['content'])]);
            $commentId = (int) $this->db->lastInsertId();

            // Get user info for response
            $stmt = $this->db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$authUser['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'id' => $commentId,
                'video_id' => $videoId,
                'user_id' => $authUser['user_id'],
                'user_name' => $user['name'],
                'content' => trim($data['content']),
                'created_at' => date('Y-m-d H:i:s')
            ], 'Komentar berhasil ditambahkan', 201);
        } catch (Exception $e) {
            error_log('Add comment error: ' . $e->getMessage());
            Response::error('Gagal menambahkan komentar', 500);
        }
    }

    /**
     * Get video comments
     * GET /api/videos/{id}/comments
     */
    public function getComments($id): void
    {
        $videoId = (int) $id;
        $video = $this->videoModel->findById($videoId);

        if (!$video) {
            Response::notFound('Video tidak ditemukan');
        }

        try {
            $stmt = $this->db->prepare('
                SELECT 
                    vc.id,
                    vc.video_id,
                    vc.user_id,
                    u.name as user_name,
                    vc.comment as content,
                    vc.created_at
                FROM video_comments vc
                JOIN users u ON vc.user_id = u.id
                WHERE vc.video_id = ? AND vc.is_active = 1
                ORDER BY vc.created_at DESC
            ');
            $stmt->execute([$videoId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success($comments);
        } catch (Exception $e) {
            error_log('Get comments error: ' . $e->getMessage());
            Response::error('Gagal mengambil komentar', 500);
        }
    }

    /**
     * Delete comment (owner or admin only)
     * DELETE /api/videos/comments/{commentId}
     */
    public function deleteComment($commentId): void
    {
        $authUser = $this->requireAuth();
        $commentIdInt = (int) $commentId;

        try {
            // Get comment to verify ownership
            $stmt = $this->db->prepare('SELECT user_id FROM video_comments WHERE id = ? LIMIT 1');
            $stmt->execute([$commentIdInt]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$comment) {
                Response::notFound('Komentar tidak ditemukan');
            }

            // Check if user is owner or admin (strict comparison)
            if ((int) $comment['user_id'] !== $authUser['user_id'] && $authUser['role'] !== 'admin') {
                Response::forbidden('Anda tidak berhak menghapus komentar ini');
            }

            // Delete comment with LIMIT for safety
            $stmt = $this->db->prepare('DELETE FROM video_comments WHERE id = ? LIMIT 1');
            $stmt->execute([$commentIdInt]);

            Response::success(null, 'Komentar berhasil dihapus');
        } catch (Exception $e) {
            error_log('Delete comment error: ' . $e->getMessage());
            Response::error('Gagal menghapus komentar', 500);
        }
    }
}
