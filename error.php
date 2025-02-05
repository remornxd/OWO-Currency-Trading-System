<?php
require_once __DIR__ . '/config/config.php';

$error_code = $_SERVER['REDIRECT_STATUS'] ?? 404;
$error_messages = [
    400 => 'Hatalı İstek',
    401 => 'Yetkisiz Erişim',
    403 => 'Erişim Yasak',
    404 => 'Sayfa Bulunamadı',
    500 => 'Sunucu Hatası'
];

$error_message = $error_messages[$error_code] ?? 'Bilinmeyen Hata';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Hata <?= $error_code ?></title>
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
        .error-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 90%;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: var(--danger-color);
            margin-bottom: 1rem;
            line-height: 1;
        }
        .error-message {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 2rem;
        }
        .error-description {
            color: var(--secondary-color);
            margin-bottom: 2rem;
        }
        .back-button {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="error-container fade-in">
        <div class="error-code"><?= $error_code ?></div>
        <div class="error-message"><?= $error_message ?></div>
        <div class="error-description">
            <?php if ($error_code === 404): ?>
                Aradığınız sayfa bulunamadı. Sayfa kaldırılmış veya taşınmış olabilir.
            <?php elseif ($error_code === 403): ?>
                Bu sayfaya erişim yetkiniz bulunmuyor.
            <?php elseif ($error_code === 401): ?>
                Bu sayfaya erişmek için giriş yapmanız gerekiyor.
            <?php elseif ($error_code === 500): ?>
                Sunucuda bir hata oluştu. Lütfen daha sonra tekrar deneyin.
            <?php else: ?>
                Bir hata oluştu. Lütfen daha sonra tekrar deneyin.
            <?php endif; ?>
        </div>
        <a href="<?= SITE_URL ?>" class="btn btn-primary back-button">
            <i class="fas fa-home me-2"></i>Ana Sayfaya Dön
        </a>
    </div>
</body>
</html> 