<?php declare(strict_types=1);

/** Users Management */
require_once __DIR__ . '/../../models/User.php';

$userModel = new User();
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'index';
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    if ($postAction === 'create') {
        $data = [
            'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS),
            'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
            'phone' => filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS),
            'password' => filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS),
            'role' => filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS),
            'gc_balance' => (float) (filter_input(INPUT_POST, 'gc_balance', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0),
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0
        ];

        // Check email exists
        if ($userModel->findByEmail($data['email'])) {
            $error = 'Email sudah terdaftar';
        } else {
            $userModel->createUser($data);
            $message = 'User berhasil ditambahkan';
        }
    }

    if ($postAction === 'update') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $data = [
            'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS),
            'phone' => filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS),
            'role' => filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS),
            'gc_balance' => (float) filter_input(INPUT_POST, 'gc_balance', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'is_active' => filter_input(INPUT_POST, 'is_active') !== null ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $userModel->update($id, $data);
        $message = 'User berhasil diupdate';
    }

    if ($postAction === 'delete') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

        // Don't allow deleting yourself
        if ($id != $_SESSION['admin_id']) {
            try {
                // Get database connection
                require_once __DIR__ . '/../../config/database.php';
                $db = Database::getInstance()->getConnection();

                // Delete related records first
                $relatedTables = [
                    'cart' => 'user_id',
                    'transactions' => 'user_id',
                    'withdrawals' => 'user_id',
                    'membership_requests' => 'user_id',
                    'video_rewards' => 'user_id',
                    'video_comments' => 'user_id',
                    'video_likes' => 'user_id',
                    'referral_history' => 'referrer_id',
                ];

                foreach ($relatedTables as $table => $column) {
                    try {
                        $stmt = $db->prepare("DELETE FROM $table WHERE $column = ?");
                        $stmt->execute([$id]);
                    } catch (PDOException $e) {
                        // Table might not exist, ignore
                    }
                }

                // Also clean referral_history where user is referred
                try {
                    $stmt = $db->prepare('DELETE FROM referral_history WHERE referred_id = ?');
                    $stmt->execute([$id]);
                } catch (PDOException $e) {
                    // Ignore
                }

                // Update users referred by this user to NULL
                try {
                    $stmt = $db->prepare('UPDATE users SET referred_by = NULL WHERE referred_by = ?');
                    $stmt->execute([$id]);
                } catch (PDOException $e) {
                    // Ignore
                }

                $userModel->delete($id);
                $message = 'User berhasil dihapus';
            } catch (PDOException $e) {
                $error = 'Gagal menghapus user: ' . $e->getMessage();
            } catch (Exception $e) {
                $error = 'Gagal menghapus user: ' . $e->getMessage();
            }
        } else {
            $error = 'Tidak bisa menghapus akun sendiri';
        }
    }

    if ($postAction === 'add_gc') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $amount = (float) filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $userModel->updateGCBalance($id, $amount, 'add');
        $message = 'Berhasil menambahkan ' . number_format($amount, 2) . ' GC';
    }
}

// Get users
$users = $userModel->findAll([], 'created_at DESC');

$pageTitle = 'Users Management';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-2"></i>Daftar Users</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah User
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>GC Balance</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php elseif ($user['role'] === 'member'): ?>
                            <span class="badge bg-warning text-dark">Member</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">User</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format((float) $user['gc_balance'], 2) ?> GC</td>
                    <td>
                        <?php if ((int) $user['is_active'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="addGC(<?= (int) $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <?php if ((int) $user['id'] !== (int) $_SESSION['admin_id']): ?>
                            <button class="btn btn-outline-danger" onclick="deleteUser(<?= (int) $user['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GC Balance</label>
                        <input type="number" name="gc_balance" class="form-control" value="0" step="0.01">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="addIsActive" checked>
                        <label class="form-check-label" for="addIsActive">Active</label>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" id="editEmail" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="editRole" class="form-select" required>
                            <option value="user">User</option>
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GC Balance</label>
                        <input type="number" name="gc_balance" id="editGcBalance" class="form-control" step="0.01">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                        <label class="form-check-label" for="editIsActive">Active</label>
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

<!-- Add GC Modal -->
<div class="modal fade" id="addGCModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_gc">
                <input type="hidden" name="id" id="addGCUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah GC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tambah GC untuk: <strong id="addGCUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Jumlah GC</label>
                        <input type="number" name="amount" class="form-control" required step="0.01" min="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteUserId">
</form>

<script>
'use strict';

const editUser = (user) => {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editPhone').value = user.phone || '';
    document.getElementById('editRole').value = user.role;
    document.getElementById('editGcBalance').value = user.gc_balance;
    document.getElementById('editIsActive').checked = user.is_active == 1;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
};

const addGC = (userId, userName) => {
    document.getElementById('addGCUserId').value = userId;
    document.getElementById('addGCUserName').textContent = userName;
    new bootstrap.Modal(document.getElementById('addGCModal')).show();
};

const deleteUser = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus user ini? Semua data terkait user akan dihapus.')) {
        const deleteForm = document.getElementById('deleteForm');
        const deleteIdField = document.getElementById('deleteUserId');
        
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
