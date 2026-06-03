<?php declare(strict_types=1);

/** Products Management */
require_once __DIR__ . '/../../models/Product.php';
require_once __DIR__ . '/../../models/Category.php';

$productModel = new Product();
$categoryModel = new Category();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    if ($postAction === 'create') {
        // Handle image upload
        $imageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($fileExtension, $allowedExtensions, true)) {
                $filename = 'product_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                    $imageUrl = 'uploads/products/' . $filename;
                }
            }
        }

        $data = [
            'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS),
            'description' => filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS),
            'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT) ?: null,
            'price' => filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'gc_bonus' => filter_input(INPUT_POST, 'gc_bonus', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'stock' => filter_input(INPUT_POST, 'stock', FILTER_SANITIZE_NUMBER_INT),
            'items_per_unit' => filter_input(INPUT_POST, 'items_per_unit', FILTER_SANITIZE_NUMBER_INT) ?: 1,
            'image' => $imageUrl,
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
            'is_featured' => filter_input(INPUT_POST, 'is_featured') !== null ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $productModel->create($data);
        $message = 'Produk berhasil ditambahkan';
    }

    if ($postAction === 'update') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

        // Get existing product data
        $existingProduct = $productModel->findById($id);
        $imageUrl = $existingProduct['image'];

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $filename = 'product_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                    // Delete old image if exists
                    if ($imageUrl && file_exists(__DIR__ . '/../../' . $imageUrl)) {
                        unlink(__DIR__ . '/../../' . $imageUrl);
                    }
                    $imageUrl = 'uploads/products/' . $filename;
                }
            }
        }

        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'category_id' => $_POST['category_id'] ?: null,
            'price' => $_POST['price'],
            'gc_bonus' => $_POST['gc_bonus'],
            'stock' => $_POST['stock'],
            'items_per_unit' => (int) ($_POST['items_per_unit'] ?? 1) ?: 1,
            'image' => $imageUrl,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $productModel->update($id, $data);
        $message = 'Produk berhasil diupdate';
    }

    if ($postAction === 'delete') {
        $productId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

        try {
            // Get database connection
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance()->getConnection();

            // Get product data for image cleanup
            $product = $productModel->findById($productId);

            if ($product) {
                // Delete related records first (to avoid foreign key constraint errors)

                // Delete from cart
                try {
                    $stmt = $db->prepare('DELETE FROM cart WHERE product_id = ?');
                    $stmt->execute([$productId]);
                } catch (PDOException $e) {
                    // Table might not exist, ignore
                }

                // Update transactions to set product_id to NULL or keep reference
                // Note: We don't delete transactions, just unlink product
                try {
                    $stmt = $db->prepare('UPDATE transactions SET product_id = NULL WHERE product_id = ?');
                    $stmt->execute([$productId]);
                } catch (PDOException $e) {
                    // If foreign key doesn't allow NULL, ignore
                }

                // Delete product image if exists
                if (!empty($product['image'])) {
                    $imagePath = __DIR__ . '/../../' . $product['image'];
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }

                // Delete the product
                $productModel->delete($productId);
                $message = 'Produk berhasil dihapus';
            } else {
                $message = 'Produk tidak ditemukan';
            }
        } catch (PDOException $e) {
            $message = 'Gagal menghapus produk: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = 'Gagal menghapus produk: ' . $e->getMessage();
        }
    }
}

// Get data
$products = $productModel->getAllWithCategory();
$categories = $categoryModel->getActive();

$pageTitle = 'Products Management';
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
        <span><i class="bi bi-box-seam me-2"></i>Daftar Produk</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Produk
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gambar</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>GC Bonus</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= (int) $product['id'] ?></td>
                    <td>
                        <?php if ($product['image']): ?>
                            <img src="/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                                <?= strtoupper(substr($product['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($product['name']) ?>
                        <?php if ((int) $product['is_featured'] === 1): ?>
                            <span class="badge bg-warning text-dark ms-1">Featured</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                    <td>Rp <?= number_format((float) $product['price']) ?></td>
                    <td><?= number_format((float) $product['gc_bonus'], 2) ?> GC</td>
                    <td><?= number_format((int) $product['stock']) ?></td>
                    <td>
                        <?php if ((int) $product['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteProduct(<?= (int) $product['id'] ?>)">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Gambar Produk</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF, WEBP (Max 2MB)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga (Rp)</label>
                            <input type="number" name="price" class="form-control" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GC Bonus</label>
                            <input type="number" name="gc_bonus" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" required min="0" value="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item per Unit</label>
                            <input type="number" name="items_per_unit" class="form-control" required min="1" value="1">
                            <small class="text-muted">Eceran=1, Slop/Lusin=10, dll</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="addIsActive" checked>
                                <label class="form-check-label" for="addIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" name="is_featured" class="form-check-input" id="addIsFeatured">
                                <label class="form-check-label" for="addIsFeatured">Featured</label>
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

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editProductId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Gambar Produk</label>
                        <div id="editCurrentImage" class="mb-2"></div>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah gambar</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" id="editCategoryId" class="form-select">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga (Rp)</label>
                            <input type="number" name="price" id="editPrice" class="form-control" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GC Bonus</label>
                            <input type="number" name="gc_bonus" id="editGcBonus" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" id="editStock" class="form-control" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item per Unit</label>
                            <input type="number" name="items_per_unit" id="editItemsPerUnit" class="form-control" required min="1">
                            <small class="text-muted">Eceran=1, Slop/Lusin=10, dll</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input type="checkbox" name="is_featured" class="form-check-input" id="editIsFeatured">
                                <label class="form-check-label" for="editIsFeatured">Featured</label>
                            </div>
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

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteProductId">
</form>

<script>
'use strict';

const editProduct = (product) => {
    document.getElementById('editProductId').value = product.id;
    document.getElementById('editName').value = product.name;
    document.getElementById('editDescription').value = product.description || '';
    document.getElementById('editCategoryId').value = product.category_id || '';
    document.getElementById('editPrice').value = product.price;
    document.getElementById('editGcBonus').value = product.gc_bonus;
    document.getElementById('editStock').value = product.stock;
    document.getElementById('editItemsPerUnit').value = product.items_per_unit || 1;
    document.getElementById('editIsActive').checked = product.is_active == 1;
    document.getElementById('editIsFeatured').checked = product.is_featured == 1;
    
    // Show current image if exists
    const imageContainer = document.getElementById('editCurrentImage');
    if (product.image) {
        imageContainer.innerHTML = '<img src="/' + product.image + '" alt="Current" style="max-width: 200px; height: auto; border-radius: 4px;">';
    } else {
        imageContainer.innerHTML = '<p class="text-muted">Belum ada gambar</p>';
    }
    
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
};

const deleteProduct = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
        const deleteForm = document.getElementById('deleteForm');
        const deleteIdField = document.getElementById('deleteProductId');
        
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
