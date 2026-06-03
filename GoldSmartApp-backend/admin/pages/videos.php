<?php declare(strict_types=1);

/** Videos Management */
require_once __DIR__ . '/../../models/Video.php';
require_once __DIR__ . '/../../models/VideoCategory.php';
require_once __DIR__ . '/../../config/database.php';

$videoModel = new Video();
$videoCatModel = new VideoCategory();
$db = Database::getInstance()->getConnection();

$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    // Handle delete comment
    if ($postAction === 'delete_comment') {
        $commentId = (int) filter_input(INPUT_POST, 'comment_id', FILTER_SANITIZE_NUMBER_INT);
        $videoId = (int) filter_input(INPUT_POST, 'video_id', FILTER_SANITIZE_NUMBER_INT);

        try {
            $stmt = $db->prepare('DELETE FROM video_comments WHERE id = ? LIMIT 1');
            $stmt->execute([$commentId]);

            $_SESSION['success_message'] = 'Komentar berhasil dihapus';
        } catch (Exception $e) {
            $_SESSION['success_message'] = 'Gagal menghapus komentar: ' . $e->getMessage();
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'create') {
        $videoUrl = null;
        $thumbnailUrl = null;
        $uploadErrors = [];

        // Handle video file upload
        if (isset($_FILES['video_file'])) {
            $videoFileError = $_FILES['video_file']['error'];

            if ($videoFileError === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/videos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $videoExt = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
                $allowedVideoExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', '3gp'];

                if (in_array($videoExt, $allowedVideoExt, true)) {
                    $videoFileName = 'video_' . time() . '_' . uniqid() . '.' . $videoExt;
                    $uploadResult = move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $videoFileName);
                    if ($uploadResult) {
                        $videoUrl = 'uploads/videos/' . $videoFileName;
                    } else {
                        $uploadErrors[] = 'Gagal memindahkan file video';
                    }
                } else {
                    $uploadErrors[] = 'Format video tidak diizinkan: ' . $videoExt;
                }
            } else {
                // Map upload error codes to messages
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                    UPLOAD_ERR_PARTIAL => 'File hanya sebagian terupload',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                    UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
                ];
                if ($videoFileError !== UPLOAD_ERR_NO_FILE) {
                    $uploadErrors[] = $errorMessages[$videoFileError] ?? 'Unknown upload error: ' . $videoFileError;
                }
            }
        }

        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/thumbnails/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($thumbExt, $allowedImageExt)) {
                $thumbFileName = 'thumb_' . time() . '_' . uniqid() . '.' . $thumbExt;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbFileName);
                $thumbnailUrl = 'uploads/thumbnails/' . $thumbFileName;
            }
        }

        // Check if there were upload errors (but allow no video file)
        if (!empty($uploadErrors)) {
            $_SESSION['success_message'] = 'Error upload: ' . implode(', ', $uploadErrors);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
            exit;
        }

        try {
            $insertData = [
                'user_id' => (int) ($_SESSION['admin_id'] ?? 1),
                'title' => trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
                'description' => trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
                'video_url' => $videoUrl,
                'thumbnail_url' => $thumbnailUrl,
                'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT) ? (int) filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT) : null,
                'gc_reward' => (float) (filter_input(INPUT_POST, 'gc_reward', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0),
                'required_watch_time' => (int) (filter_input(INPUT_POST, 'required_watch_time', FILTER_SANITIZE_NUMBER_INT) ?? 30),
                'duration' => 0,
                'views' => 0,
                'likes' => 0,
                'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Log the insert data for debugging
            error_log('Video insert data: ' . json_encode($insertData));

            $insertedId = $videoModel->create($insertData);

            if ($insertedId) {
                $_SESSION['success_message'] = 'Video berhasil ditambahkan dengan ID: ' . $insertedId;
            } else {
                $_SESSION['success_message'] = 'Gagal menambahkan video (no ID returned)';
            }
        } catch (PDOException $e) {
            $_SESSION['success_message'] = 'Database Error: ' . $e->getMessage();
            error_log('Video create PDO error: ' . $e->getMessage() . ' - Code: ' . $e->getCode());
        } catch (Exception $e) {
            $_SESSION['success_message'] = 'Gagal menambahkan video: ' . $e->getMessage();
            error_log('Video create error: ' . $e->getMessage());
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'update') {
        $updateData = [
            'title' => filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS),
            'description' => filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS),
            'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT) ? (int) filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT) : null,
            'gc_reward' => (float) filter_input(INPUT_POST, 'gc_reward', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'required_watch_time' => (int) filter_input(INPUT_POST, 'required_watch_time', FILTER_SANITIZE_NUMBER_INT),
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Handle video file upload if provided
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/videos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $videoExt = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            $allowedVideoExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', '3gp'];

            if (in_array($videoExt, $allowedVideoExt, true)) {
                $videoFileName = 'video_' . time() . '_' . uniqid() . '.' . $videoExt;
                move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $videoFileName);
                $updateData['video_url'] = 'uploads/videos/' . $videoFileName;
            }
        }

        // Handle thumbnail upload if provided
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/thumbnails/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($thumbExt, $allowedImageExt)) {
                $thumbFileName = 'thumb_' . time() . '_' . uniqid() . '.' . $thumbExt;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbFileName);
                $updateData['thumbnail_url'] = 'uploads/thumbnails/' . $thumbFileName;
            }
        }

        $videoModel->update((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT), $updateData);

        // Redirect to prevent form resubmission
        $_SESSION['success_message'] = 'Video berhasil diupdate';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'delete') {
        $videoId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

        try {
            // Get video data first for file cleanup
            $video = $videoModel->find($videoId);

            if ($video) {
                // Delete related records first (to avoid foreign key constraint errors)
                // Use try-catch for each table in case it doesn't exist

                // Delete video_comments (if table exists)
                try {
                    $stmt = $db->prepare('DELETE FROM video_comments WHERE video_id = ?');
                    $stmt->execute([$videoId]);
                } catch (PDOException $e) {
                    // Table might not exist, ignore
                }

                // Delete video_rewards (if table exists)
                try {
                    $stmt = $db->prepare('DELETE FROM video_rewards WHERE video_id = ?');
                    $stmt->execute([$videoId]);
                } catch (PDOException $e) {
                    // Table might not exist, ignore
                }

                // Delete video_likes (if table exists)
                try {
                    $stmt = $db->prepare('DELETE FROM video_likes WHERE video_id = ?');
                    $stmt->execute([$videoId]);
                } catch (PDOException $e) {
                    // Table might not exist, ignore
                }

                // Delete physical files
                if (!empty($video['video_url'])) {
                    $filePath = __DIR__ . '/../../' . $video['video_url'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
                if (!empty($video['thumbnail_url'])) {
                    $filePath = __DIR__ . '/../../' . $video['thumbnail_url'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                // Delete video record
                $videoModel->delete($videoId);

                $_SESSION['success_message'] = 'Video berhasil dihapus';
            } else {
                $_SESSION['success_message'] = 'Video tidak ditemukan';
            }
        } catch (PDOException $e) {
            // Log error for debugging
            error_log('Delete video error: ' . $e->getMessage());
            $_SESSION['success_message'] = 'Gagal menghapus video: ' . $e->getMessage();
        } catch (Exception $e) {
            error_log('Delete video general error: ' . $e->getMessage());
            $_SESSION['success_message'] = 'Gagal menghapus video: ' . $e->getMessage();
        }

        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'delete_video_file') {
        $video = $videoModel->find((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));
        if ($video && !empty($video['video_url'])) {
            // Delete physical file
            $filePath = __DIR__ . '/../../' . $video['video_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Update database
            $videoModel->update((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT), ['video_url' => null]);
        }

        // Redirect to prevent form resubmission
        $_SESSION['success_message'] = 'File video berhasil dihapus';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'delete_thumbnail_file') {
        $video = $videoModel->find((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));
        if ($video && !empty($video['thumbnail_url'])) {
            // Delete physical file
            $filePath = __DIR__ . '/../../' . $video['thumbnail_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Update database
            $videoModel->update((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT), ['thumbnail_url' => null]);
        }

        // Redirect to prevent form resubmission
        $_SESSION['success_message'] = 'Thumbnail berhasil dihapus';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    // Category actions
    if ($postAction === 'create_category') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        $videoCatModel->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Redirect to prevent form resubmission
        $_SESSION['success_message'] = 'Kategori video berhasil ditambahkan';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }

    if ($postAction === 'delete_category') {
        $videoCatModel->delete((int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));

        // Redirect to prevent form resubmission
        $_SESSION['success_message'] = 'Kategori video berhasil dihapus';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=videos');
        exit;
    }
}

// Get message from session (after redirect)
$message = $_SESSION['success_message'] ?? '';
if ($message) {
    unset($_SESSION['success_message']);
}

$videos = $videoModel->getAllWithCategory();
$videoCategories = $videoCatModel->getActive();

$pageTitle = 'Videos';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Categories -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder me-2"></i>Kategori Video</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
        </button>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($videoCategories as $cat): ?>
                <span class="badge bg-secondary d-inline-flex align-items-center gap-2" style="font-size:13px; padding: 8px 12px;">
                    <?= htmlspecialchars($cat['name']) ?>
                    <form method="POST" style="display:inline; margin: 0;">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                        <button type="submit" class="btn-close btn-close-white" style="font-size:10px; padding: 0; margin: 0;" onclick="return confirm('Hapus kategori?')"></button>
                    </form>
                </span>
            <?php endforeach; ?>
            <?php if (empty($videoCategories)): ?>
                <span class="text-muted">Belum ada kategori</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-play-circle me-2"></i>Daftar Video</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addVideoModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Video
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Video File</th>
                    <th>GC Reward</th>
                    <th>Views</th>
                    <th>Comments</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($videos as $video):
                    // Get comment count for this video
                    $commentStmt = $db->prepare('SELECT COUNT(*) as count FROM video_comments WHERE video_id = ? AND is_active = 1');
                    $commentStmt->execute([(int) $video['id']]);
                    $commentCount = (int) ($commentStmt->fetch()['count'] ?? 0);
                    ?>
                <tr>
                    <td><?= (int) $video['id'] ?></td>
                    <td><?= htmlspecialchars($video['title']) ?></td>
                    <td><?= htmlspecialchars($video['category_name'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($video['video_url'])): ?>
                            <small class="text-muted"><?= htmlspecialchars(basename($video['video_url'])) ?></small>
                        <?php else: ?>
                            <span class="text-danger">No video</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format((float) $video['gc_reward'], 2) ?> GC</td>
                    <td><?= number_format((int) ($video['views'] ?? 0)) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" onclick="viewComments(<?= (int) $video['id'] ?>, '<?= htmlspecialchars(addslashes($video['title'])) ?>')">
                            <i class="bi bi-chat-dots"></i> <?= $commentCount ?>
                        </button>
                    </td>
                    <td>
                        <?php if ((int) $video['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if (!empty($video['video_url'])): ?>
                            <a href="/<?= $video['video_url'] ?>" target="_blank" class="btn btn-outline-primary" title="Play Video">
                                <i class="bi bi-play-circle"></i>
                            </a>
                            <?php endif; ?>
                            <button class="btn btn-outline-info" onclick="viewVideoDetail(<?= htmlspecialchars(json_encode($video)) ?>)" title="Detail">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-primary" onclick="editVideo(<?= htmlspecialchars(json_encode($video)) ?>)" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteVideo(<?= (int) $video['id'] ?>)" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_category">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kategori Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Video Modal -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Video File</label>
                        <input type="file" name="video_file" class="form-control" required accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv,.webm,.m4v,.3gp">
                        <small class="text-muted">Upload video (MP4, AVI, MOV, WMV, FLV, MKV, WEBM, M4V, 3GP)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thumbnail</label>
                        <input type="file" name="thumbnail" class="form-control" accept="image/*">
                        <small class="text-muted">Upload gambar thumbnail (JPG, PNG, GIF)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($videoCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GC Reward</label>
                            <input type="number" name="gc_reward" class="form-control" required min="0" step="0.01" value="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Watch Time (detik)</label>
                            <input type="number" name="required_watch_time" class="form-control" required min="1" value="30">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Video Modal -->
<div class="modal fade" id="editVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editVideoId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="editTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Video File</label>
                        <div id="currentVideoInfo" class="alert alert-info p-2 mb-2" style="display:none;">
                            <small>
                                <strong>Video saat ini:</strong> 
                                <span id="currentVideoName"></span>
                                <a href="#" id="currentVideoLink" target="_blank" class="ms-2">
                                    <i class="bi bi-play-circle"></i> Lihat
                                </a>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="deleteCurrentVideo()" title="Hapus video ini">
                                    <i class="bi bi-x-circle"></i> Hapus
                                </button>
                            </small>
                        </div>
                        <input type="file" name="video_file" id="editVideoFile" class="form-control" accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv,.webm,.m4v,.3gp">
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah video. Support: MP4, AVI, MOV, WMV, FLV, MKV, WEBM, M4V, 3GP</small>
                        <div id="newVideoPreview" class="mt-2" style="display:none;">
                            <div class="alert alert-success p-2">
                                <small>
                                    <strong>Video baru:</strong> 
                                    <span id="newVideoName"></span>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="clearVideoFile()">
                                        <i class="bi bi-x-circle"></i> Hapus
                                    </button>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thumbnail</label>
                        <div id="currentThumbnailInfo" class="alert alert-info p-2 mb-2" style="display:none;">
                            <small class="d-flex align-items-center">
                                <strong>Thumbnail saat ini:</strong>
                                <img id="currentThumbnailImg" src="" style="max-height: 50px; margin-left: 10px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                <span class="text-danger ms-2" style="display:none;">Gambar tidak ditemukan</span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="deleteCurrentThumbnail()" title="Hapus thumbnail ini">
                                    <i class="bi bi-x-circle"></i> Hapus
                                </button>
                            </small>
                        </div>
                        <input type="file" name="thumbnail" id="editThumbnail" class="form-control" accept="image/*">
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah thumbnail</small>
                        <div id="newThumbnailPreview" class="mt-2" style="display:none;">
                            <div class="alert alert-success p-2">
                                <small>
                                    <strong>Thumbnail baru:</strong>
                                    <img id="newThumbnailImg" src="" style="max-height: 50px; margin-left: 10px;">
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="clearThumbnailFile()">
                                        <i class="bi bi-x-circle"></i> Hapus
                                    </button>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" id="editCategoryId" class="form-select">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($videoCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GC Reward</label>
                            <input type="number" name="gc_reward" id="editGcReward" class="form-control" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Watch Time (detik)</label>
                            <input type="number" name="required_watch_time" id="editWatchTime" class="form-control" required min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteVideoId">
</form>

<form id="deleteCommentForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_comment">
    <input type="hidden" name="comment_id" id="deleteCommentId">
    <input type="hidden" name="video_id" id="deleteCommentVideoId">
</form>

<!-- Video Detail Modal -->
<div class="modal fade" id="videoDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div id="detailVideoPreview" class="mb-3">
                            <video id="detailVideoPlayer" controls style="width:100%; max-height:300px; background:#000;">
                                <source src="" type="video/mp4">
                            </video>
                        </div>
                        <div id="detailThumbnailPreview" class="mb-3">
                            <img id="detailThumbnailImg" src="" style="max-width:100%; max-height:200px;" class="rounded">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h4 id="detailTitle"></h4>
                        <p id="detailDescription" class="text-muted"></p>
                        <table class="table table-sm">
                            <tr><th>Category</th><td id="detailCategory"></td></tr>
                            <tr><th>GC Reward</th><td id="detailGcReward"></td></tr>
                            <tr><th>Watch Time</th><td id="detailWatchTime"></td></tr>
                            <tr><th>Views</th><td id="detailViews"></td></tr>
                            <tr><th>Likes</th><td id="detailLikes"></td></tr>
                            <tr><th>Status</th><td id="detailStatus"></td></tr>
                            <tr><th>Created</th><td id="detailCreated"></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div class="modal fade" id="commentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Komentar Video: <span id="commentsVideoTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="commentsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Memuat komentar...</p>
                </div>
                <div id="commentsEmpty" class="text-center py-4" style="display:none;">
                    <i class="bi bi-chat-dots" style="font-size:3rem; color:#666;"></i>
                    <p class="mt-2 text-muted">Belum ada komentar</p>
                </div>
                <div id="commentsList" class="list-group" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
'use strict';

const editVideo = (video) => {
    document.getElementById('editVideoId').value = video.id;
    document.getElementById('editTitle').value = video.title;
    document.getElementById('editDescription').value = video.description || '';
    document.getElementById('editCategoryId').value = video.category_id || '';
    document.getElementById('editGcReward').value = video.gc_reward;
    document.getElementById('editWatchTime').value = video.required_watch_time;
    document.getElementById('editIsActive').checked = video.is_active == 1;
    
    // Show current video info
    if (video.video_url) {
        document.getElementById('currentVideoInfo').style.display = 'block';
        document.getElementById('currentVideoName').textContent = video.video_url.split('/').pop();
        document.getElementById('currentVideoLink').href = '/' + video.video_url;
    } else {
        document.getElementById('currentVideoInfo').style.display = 'none';
    }
    
    // Show current thumbnail
    if (video.thumbnail_url) {
        document.getElementById('currentThumbnailInfo').style.display = 'block';
        // Try multiple path variations for thumbnail
        const thumbnailPath = video.thumbnail_url.startsWith('uploads/') 
            ? '/' + video.thumbnail_url 
            : '/uploads/thumbnails/' + video.thumbnail_url;
        document.getElementById('currentThumbnailImg').src = thumbnailPath;
        console.log('Thumbnail path:', thumbnailPath, 'Original:', video.thumbnail_url);
    } else {
        document.getElementById('currentThumbnailInfo').style.display = 'none';
    }
    
    // Reset file inputs
    document.getElementById('editVideoFile').value = '';
    document.getElementById('editThumbnail').value = '';
    document.getElementById('newVideoPreview').style.display = 'none';
    document.getElementById('newThumbnailPreview').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
}

// Preview video file when selected
document.getElementById('editVideoFile').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        document.getElementById('newVideoName').textContent = this.files[0].name;
        document.getElementById('newVideoPreview').style.display = 'block';
    } else {
        document.getElementById('newVideoPreview').style.display = 'none';
    }
});

// Preview thumbnail when selected
document.getElementById('editThumbnail').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newThumbnailImg').src = e.target.result;
            document.getElementById('newThumbnailPreview').style.display = 'block';
        }
        reader.readAsDataURL(this.files[0]);
    } else {
        document.getElementById('newThumbnailPreview').style.display = 'none';
    }
});

const clearVideoFile = () => {
    document.getElementById('editVideoFile').value = '';
    document.getElementById('newVideoPreview').style.display = 'none';
};

const clearThumbnailFile = () => {
    document.getElementById('editThumbnail').value = '';
    document.getElementById('newThumbnailPreview').style.display = 'none';
};

const deleteVideo = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus video ini?')) {
        const deleteForm = document.getElementById('deleteForm');
        const deleteIdField = document.getElementById('deleteVideoId');
        
        if (!deleteForm || !deleteIdField) {
            alert('Error: Form delete tidak ditemukan!');
            console.error('deleteForm:', deleteForm, 'deleteVideoId:', deleteIdField);
            return;
        }
        
        deleteIdField.value = id;
        deleteForm.submit();
    }
};

const deleteCurrentVideo = () => {
    const videoId = document.getElementById('editVideoId').value;
    if (confirm('Apakah Anda yakin ingin menghapus file video ini? Data video lainnya tetap tersimpan.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_video_file">
            <input type="hidden" name="id" value="${videoId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
};

const deleteCurrentThumbnail = () => {
    const videoId = document.getElementById('editVideoId').value;
    if (confirm('Apakah Anda yakin ingin menghapus thumbnail ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_thumbnail_file">
            <input type="hidden" name="id" value="${videoId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// View Video Detail
const viewVideoDetail = (video) => {
    document.getElementById('detailTitle').textContent = video.title;
    document.getElementById('detailDescription').textContent = video.description || 'Tidak ada deskripsi';
    document.getElementById('detailCategory').textContent = video.category_name || '-';
    document.getElementById('detailGcReward').textContent = parseFloat(video.gc_reward).toFixed(2) + ' GC';
    document.getElementById('detailWatchTime').textContent = video.required_watch_time + ' detik';
    document.getElementById('detailViews').textContent = video.views || 0;
    document.getElementById('detailLikes').textContent = video.likes || 0;
    document.getElementById('detailStatus').innerHTML = video.is_active == 1 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-danger">Inactive</span>';
    document.getElementById('detailCreated').textContent = video.created_at || '-';
    
    // Video preview
    if (video.video_url) {
        document.getElementById('detailVideoPreview').style.display = 'block';
        document.getElementById('detailVideoPlayer').querySelector('source').src = '/' + video.video_url;
        document.getElementById('detailVideoPlayer').load();
    } else {
        document.getElementById('detailVideoPreview').style.display = 'none';
    }
    
    // Thumbnail preview
    if (video.thumbnail_url) {
        document.getElementById('detailThumbnailPreview').style.display = 'block';
        document.getElementById('detailThumbnailImg').src = '/' + video.thumbnail_url;
    } else {
        document.getElementById('detailThumbnailPreview').style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('videoDetailModal')).show();
}

// View Comments
let currentVideoIdForComments = 0;

const viewComments = (videoId, videoTitle) => {
    currentVideoIdForComments = videoId;
    document.getElementById('commentsVideoTitle').textContent = videoTitle;
    document.getElementById('commentsLoading').style.display = 'block';
    document.getElementById('commentsEmpty').style.display = 'none';
    document.getElementById('commentsList').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('commentsModal')).show();
    
    // Fetch comments via API
    fetch('/api/videos/' + videoId + '/comments')
        .then(res => res.json())
        .then(data => {
            document.getElementById('commentsLoading').style.display = 'none';
            
            if (data.success && data.data && data.data.length > 0) {
                const commentsList = document.getElementById('commentsList');
                commentsList.innerHTML = '';
                
                data.data.forEach(comment => {
                    const date = new Date(comment.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });
                    
                    commentsList.innerHTML += `
                        <div class="list-group-item" id="comment-${comment.id}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="text-primary">${comment.user_name}</strong>
                                    <small class="text-muted ms-2">${date}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteComment(${comment.id}, ${videoId})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <p class="mb-0 mt-2">${comment.content}</p>
                        </div>
                    `;
                });
                
                commentsList.style.display = 'block';
            } else {
                document.getElementById('commentsEmpty').style.display = 'block';
            }
        })
        .catch(err => {
            document.getElementById('commentsLoading').style.display = 'none';
            document.getElementById('commentsEmpty').style.display = 'block';
            console.error('Error fetching comments:', err);
        });
};

const deleteComment = (commentId, videoId) => {
    if (confirm('Apakah Anda yakin ingin menghapus komentar ini?')) {
        document.getElementById('deleteCommentId').value = commentId;
        document.getElementById('deleteCommentVideoId').value = videoId;
        document.getElementById('deleteCommentForm').submit();
    }
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
