<?php
require_once '../config/config.php';

// Güvenlik kontrolü
if (php_sapi_name() !== 'cli') {
    die('Bu script sadece CLI üzerinden çalıştırılabilir.');
}

try {
    // Süresi dolmak üzere olan paketler için bildirim gönder
    $stmt = $db->prepare("
        SELECT id, username, email, package_expires_at
        FROM users
        WHERE status = 1
        AND package_expires_at IS NOT NULL
        AND package_expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND package_expires_at > NOW()
        AND id NOT IN (
            SELECT user_id
            FROM notifications
            WHERE type = 'package_expiring'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        )
    ");
    $stmt->execute();
    $expiringPackages = $stmt->fetchAll();
    
    foreach ($expiringPackages as $user) {
        $daysLeft = ceil((strtotime($user['package_expires_at']) - time()) / (60 * 60 * 24));
        
        // Bildirim oluştur
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message)
            VALUES (?, 'package_expiring', 'Paket Süreniz Dolmak Üzere', ?)
        ");
        
        $message = "Sayın {$user['username']}, paket sürenizin dolmasına $daysLeft gün kaldı. "
                . "Paketinizi yenilemek için lütfen admin ile iletişime geçin.";
        
        $stmt->execute([$user['id'], $message]);
        
        // Log kaydı
        logAction($user['id'], 'package_expiring_notification', "Paket süresi dolmak üzere: {$user['username']}");
    }
    
    // Uzun süredir mesaj almayan tokenler için bildirim gönder
    $stmt = $db->prepare("
        SELECT t.id, t.name, t.user_id, t.last_message_at, u.username
        FROM tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 1
        AND t.last_message_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND t.id NOT IN (
            SELECT SUBSTRING_INDEX(details, '#', -1)
            FROM notifications
            WHERE type = 'token_inactive'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        )
    ");
    $stmt->execute();
    $inactiveTokens = $stmt->fetchAll();
    
    foreach ($inactiveTokens as $token) {
        // Bildirim oluştur
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, details)
            VALUES (?, 'token_inactive', 'Token Aktif Değil', ?, ?)
        ");
        
        $message = "'{$token['name']}' isimli tokeniniz son 24 saattir mesaj almadı. "
                . "Lütfen token durumunu kontrol edin.";
        
        $stmt->execute([
            $token['user_id'],
            $message,
            "token#{$token['id']}"
        ]);
        
        // Log kaydı
        logAction($token['user_id'], 'token_inactive_notification', "Aktif olmayan token: {$token['name']}");
    }
    
    // Süresi dolan paketleri pasife çek
    $stmt = $db->prepare("
        UPDATE users
        SET status = 0
        WHERE status = 1
        AND package_expires_at IS NOT NULL
        AND package_expires_at <= NOW()
    ");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    
    if ($expiredCount > 0) {
        // Log kaydı
        logAction(0, 'package_expired', "$expiredCount adet kullanıcının paketi sona erdi");
    }
    
    // Eski logları temizle (30 günden eski)
    $stmt = $db->prepare("
        DELETE FROM logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    
    if ($deletedLogs > 0) {
        // Log kaydı
        logAction(0, 'cleanup_logs', "$deletedLogs adet eski log kaydı temizlendi");
    }
    
    // Eski bildirimleri temizle (okunmuş ve 7 günden eski)
    $stmt = $db->prepare("
        DELETE FROM notifications
        WHERE is_read = 1
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $deletedNotifications = $stmt->rowCount();
    
    if ($deletedNotifications > 0) {
        // Log kaydı
        logAction(0, 'cleanup_notifications', "$deletedNotifications adet eski bildirim temizlendi");
    }
    
    echo "Cron görevleri başarıyla tamamlandı.\n";
    echo "- $expiredCount paket süresi doldu\n";
    echo "- " . count($expiringPackages) . " paket süresi dolmak üzere\n";
    echo "- " . count($inactiveTokens) . " token aktif değil\n";
    echo "- $deletedLogs log kaydı temizlendi\n";
    echo "- $deletedNotifications bildirim temizlendi\n";
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
    exit(1);
} 