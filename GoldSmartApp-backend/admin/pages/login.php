<?php declare(strict_types=1);

/** Admin Login Page */

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/?page=dashboard');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi';
    } else {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user) {
            $error = 'Email atau password salah';
        } elseif (!$userModel->verifyPassword($password, $user['password'])) {
            $error = 'Email atau password salah';
        } elseif ($user['role'] !== 'admin') {
            $error = 'Anda tidak memiliki akses ke Admin Panel';
        } elseif ((int) $user['is_active'] !== 1) {
            $error = 'Akun Anda tidak aktif';
        } else {
            // Login success
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_name'] = $user['name'];
            $_SESSION['admin_role'] = $user['role'];

            header('Location: ' . BASE_URL . '/admin/?page=dashboard');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GoldSmart Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/admin/assets/images/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #0f0f1e 100%);
            padding: 40px 30px 30px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 15px;
        }
        .login-header-text {
            text-align: left;
        }
        .login-header h1 {
            color: #FFD700;
            font-weight: bold;
            margin: 0;
            font-size: 28px;
        }
        .login-header p {
            color: #999;
            margin: 5px 0 0;
        }
        .login-body {
            padding: 30px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            border: none;
            color: #000;
            font-weight: bold;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #FFA500 0%, #FF8C00 100%);
            color: #000;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="<?= BASE_URL ?>/admin/assets/images/logo-goldsmart.png" alt="GoldSmart Logo" class="login-logo">
            <div class="login-header-text">
                <h1>GoldSmart</h1>
                <p>Admin Panel</p>
            </div>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="@email.com" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-gold w-100 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>
