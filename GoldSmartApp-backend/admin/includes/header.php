<?php declare(strict_types=1);

$admin = getCurrentAdmin();
$currentPage = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'dashboard';
$adminBaseUrl = BASE_URL . '/admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - GoldSmart Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $adminBaseUrl ?>/assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $adminBaseUrl ?>/assets/images/favicon-16.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <link href="<?= $adminBaseUrl ?>/assets/css/admin.css" rel="stylesheet">
    <style>
        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="<?= $adminBaseUrl ?>/assets/images/logo-goldsmart.png" alt="GoldSmart Logo" class="sidebar-logo" style="width: 60px; height: 60px; margin-bottom: 10px; border-radius: 12px;">
            <h4>GoldSmart</h4>
            <p>Admin Panel</p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=users" class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Kelola User</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=membership" class="nav-link <?= $currentPage === 'membership' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Membership</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=products" class="nav-link <?= $currentPage === 'products' ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i>
                    <span>Kelola Produk</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=categories" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
                    <i class="bi bi-bookmark"></i>
                    <span>Kategori</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=transactions" class="nav-link <?= $currentPage === 'transactions' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i>
                    <span>Transaksi</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=withdrawals" class="nav-link <?= $currentPage === 'withdrawals' ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Withdrawal</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=mining" class="nav-link <?= $currentPage === 'mining' ? 'active' : '' ?>">
                    <i class="bi bi-gem"></i>
                    <span>Mining</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=videos" class="nav-link <?= $currentPage === 'videos' ? 'active' : '' ?>">
                    <i class="bi bi-play-circle"></i>
                    <span>Video</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="?page=config" class="nav-link <?= $currentPage === 'config' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    <span>Pengaturan</span>
                </a>
            </div>
            
            <div class="nav-item" style="margin-top: 20px;">
                <a href="?page=logout" class="nav-link logout">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-title d-flex align-items-center">
                <button class="sidebar-toggle d-lg-none me-3" onclick="toggleSidebar()" style="background:none;border:none;font-size:24px;cursor:pointer;">
                    <i class="bi bi-list"></i>
                </button>
                <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
            </div>
            
            <div class="header-user dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                    <span class="user-name me-2 d-none d-sm-inline"><?= htmlspecialchars($admin['name'] ?? 'Administrator') ?></span>
                    <div class="user-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($admin['email'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?page=logout"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </header>
        
        <div class="admin-content">
