<?php declare(strict_types=1);

/** 404 Page */
$pageTitle = '404 - Page Not Found';
include __DIR__ . '/../includes/header.php';
?>

<div class="text-center py-5">
    <h1 class="display-1 text-muted">404</h1>
    <h2 class="mb-4">Page Not Found</h2>
    <p class="text-muted mb-4">Halaman yang Anda cari tidak ditemukan.</p>
    <a href="?page=dashboard" class="btn btn-gold">
        <i class="bi bi-house me-2"></i>Kembali ke Dashboard
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
