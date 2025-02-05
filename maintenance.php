<?php
require_once __DIR__ . '/config/config.php';

// Bakım modu kontrolü
if (!($settings['maintenance_mode'] ?? false) || ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . SITE_URL);
    exit();
}

$maintenance_message = $settings['maintenance_message'] ?? 'Sistemimiz bakımdadır. Lütfen daha sonra tekrar deneyiniz.';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Bakım Modu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
        }
        .maintenance-container {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 90%;
        }
        .maintenance-icon {
            font-size: 5rem;
            color: var(--warning-color);
            margin-bottom: 2rem;
            animation: wrench 2.5s ease infinite;
        }
        .maintenance-title {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .maintenance-message {
            font-size: 1.1rem;
            color: var(--secondary-color);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        @keyframes wrench {
            0% {
                transform: rotate(-12deg);
            }
            8% {
                transform: rotate(12deg);
            }
            10% {
                transform: rotate(24deg);
            }
            18% {
                transform: rotate(-24deg);
            }
            20% {
                transform: rotate(-24deg);
            }
            28% {
                transform: rotate(24deg);
            }
            30% {
                transform: rotate(24deg);
            }
            38% {
                transform: rotate(-24deg);
            }
            40% {
                transform: rotate(-24deg);
            }
            48% {
                transform: rotate(24deg);
            }
            50% {
                transform: rotate(24deg);
            }
            58% {
                transform: rotate(-24deg);
            }
            60% {
                transform: rotate(-24deg);
            }
            68% {
                transform: rotate(24deg);
            }
            75% {
                transform: rotate(0deg);
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container fade-in">
        <div class="maintenance-icon">
            <i class="fas fa-wrench"></i>
        </div>
        <div class="maintenance-title">Bakım Modu</div>
        <div class="maintenance-message"><?= nl2br(htmlspecialchars($maintenance_message)) ?></div>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= SITE_URL ?>" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i>Sayfayı Yenile
            </a>
            <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-outline-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
            </a>
        </div>
    </div>
</body>
</html> 