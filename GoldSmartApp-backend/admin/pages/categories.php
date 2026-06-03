<?php declare(strict_types=1);

/** Categories Management */
require_once __DIR__ . '/../../models/Category.php';

$categoryModel = new Category();
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    if ($postAction === 'create') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));

        $categoryModel->create([
            'name' => $name,
            'slug' => $slug,
            'icon' => filter_input(INPUT_POST, 'icon', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $message = 'Kategori berhasil ditambahkan';
    }

    if ($postAction === 'update') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));

        $categoryModel->update($id, [
            'name' => $name,
            'slug' => $slug,
            'icon' => filter_input(INPUT_POST, 'icon', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $message = 'Kategori berhasil diupdate';
    }

    if ($postAction === 'delete') {
        $categoryId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

        try {
            // Get database connection
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance()->getConnection();

            // Set products in this category to NULL (don't delete products)
            try {
                $stmt = $db->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?');
                $stmt->execute([$categoryId]);
            } catch (PDOException $e) {
                // Ignore if fails
            }

            // Delete the category
            $categoryModel->delete($categoryId);
            $message = 'Kategori berhasil dihapus';
        } catch (PDOException $e) {
            $message = 'Gagal menghapus kategori: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = 'Gagal menghapus kategori: ' . $e->getMessage();
        }
    }
}

$categories = $categoryModel->findAll([], 'name ASC');

$pageTitle = 'Categories';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tags me-2"></i>Daftar Kategori</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
        </button>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Icon</th>
                    <th>Nama</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= (int) $cat['id'] ?></td>
                    <td style="font-size:24px"><?= $cat['icon'] ?: '-' ?></td>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                    <td>
                        <?php if ((int) $cat['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteCategory(<?= (int) $cat['id'] ?>)">
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
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="icon" class="form-control" placeholder="Icon">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" checked>
                        <label class="form-check-label">Active</label>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon (Emoji)</label>
                        <input type="text" name="icon" id="editCategoryIcon" class="form-control">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editCategoryIsActive">
                        <label class="form-check-label">Active</label>
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
    <input type="hidden" name="id" id="deleteCategoryId">
</form>

<script>
'use strict';

const editCategory = (cat) => {
    document.getElementById('editCategoryId').value = cat.id;
    document.getElementById('editCategoryName').value = cat.name;
    document.getElementById('editCategoryIcon').value = cat.icon || '';
    document.getElementById('editCategoryIsActive').checked = cat.is_active == 1;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
};

const deleteCategory = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus kategori ini?')) {
        const deleteForm = document.getElementById('deleteForm');
        const deleteIdField = document.getElementById('deleteCategoryId');
        
        if (!deleteForm || !deleteIdField) {
            alert('Error: Form delete tidak ditemukan!');
            return;
        }
        
        deleteIdField.value = id;
        deleteForm.submit();
    }
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
