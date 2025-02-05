<?php
require_once 'config/config.php';

// Oturum kontrolü
if (isset($_SESSION['user_id'])) {
    // Kullanıcı rolüne göre yönlendirme
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . SITE_URL . '/pages/admin/dashboard.php');
    } else {
        header('Location: ' . SITE_URL . '/pages/dashboard.php');
    }
} else {
    header('Location: ' . SITE_URL . '/pages/login.php');
}
exit();
?> 