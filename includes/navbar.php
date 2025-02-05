<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_admin = $_SESSION['role'] === 'admin';
?>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= SITE_URL ?>">
            <i class="fas fa-robot me-2"></i><?= SITE_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <?php if ($is_admin): ?>
                <!-- Admin Menüsü -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/dashboard.php">
                        <i class="fas fa-chart-line me-2"></i>Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/users.php">
                        <i class="fas fa-users me-2"></i>Kullanıcılar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'tokens' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/tokens.php">
                        <i class="fas fa-robot me-2"></i>Tokenler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'keys' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/keys.php">
                        <i class="fas fa-key me-2"></i>Keyler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'logs' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/logs.php">
                        <i class="fas fa-clipboard-list me-2"></i>Loglar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/admin/settings.php">
                        <i class="fas fa-cog me-2"></i>Ayarlar
                    </a>
                </li>
                <?php else: ?>
                <!-- Kullanıcı Menüsü -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/dashboard.php">
                        <i class="fas fa-chart-line me-2"></i>Panel
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= SITE_URL ?>/pages/<?= $is_admin ? 'admin/' : '' ?>profile.php">
                                <i class="fas fa-user-cog me-2"></i>Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="#" onclick="logout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Çıkış
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 