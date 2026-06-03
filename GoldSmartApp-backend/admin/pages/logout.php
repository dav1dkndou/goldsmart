<?php declare(strict_types=1);

/** Admin Logout */
session_destroy();
header('Location: ' . BASE_URL . '/admin/?page=login');
exit;
