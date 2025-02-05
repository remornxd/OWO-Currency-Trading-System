<?php
require_once '../config/config.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Oturum kontrolü
requireAuth();

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Geçersiz istek metodu.');
}

// İşlem türünü al
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        try {
            $userId = $_SESSION['user_id'];
            $isAdmin = $_SESSION['role'] === 'admin';
            
            // Bildirimleri al
            $query = "
                SELECT n.*,
                    CASE
                        WHEN n.type = 'package_expiring' THEN 'Paket Süresi'
                        WHEN n.type = 'token_inactive' THEN 'Token Durumu'
                        WHEN n.type = 'system' THEN 'Sistem'
                        ELSE 'Genel'
                    END as type_name
                FROM notifications n
                WHERE (n.user_id = ? OR n.user_id IS NULL)
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                ORDER BY n.created_at DESC
                LIMIT 50
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();
            
            // Okunmamış bildirim sayısını al
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM notifications n
                WHERE (n.user_id = ? OR n.user_id IS NULL)
                AND n.is_read = 0
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
            ");
            $stmt->execute([$userId]);
            $unreadCount = $stmt->fetchColumn();
            
            jsonResponse(true, [
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bildirimler alınırken bir hata oluştu.');
        }
        break;
        
    case 'mark_read':
        try {
            $userId = $_SESSION['user_id'];
            $notificationId = $_POST['notification_id'] ?? null;
            
            if ($notificationId) {
                // Tek bildirimi okundu olarak işaretle
                $stmt = $db->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE id = ?
                    AND (user_id = ? OR user_id IS NULL)
                ");
                $stmt->execute([$notificationId, $userId]);
            } else {
                // Tüm bildirimleri okundu olarak işaretle
                $stmt = $db->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE (user_id = ? OR user_id IS NULL)
                    AND is_read = 0
                ");
                $stmt->execute([$userId]);
            }
            
            jsonResponse(true, null, 'Bildirimler okundu olarak işaretlendi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bildirimler işaretlenirken bir hata oluştu.');
        }
        break;
        
    case 'create':
        try {
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'system';
            $userId = $_POST['user_id'] ?? null;
            $expiresAt = $_POST['expires_at'] ?? null;
            
            if (empty($title) || empty($message)) {
                jsonResponse(false, null, 'Başlık ve mesaj gereklidir.');
            }
            
            // Bildirimi oluştur
            $stmt = $db->prepare("
                INSERT INTO notifications (title, message, type, user_id, expires_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $message,
                $type,
                $userId,
                $expiresAt
            ]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'create_notification', "Yeni bildirim oluşturuldu: $title");
            
            jsonResponse(true, null, 'Bildirim başarıyla oluşturuldu.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bildirim oluşturulurken bir hata oluştu.');
        }
        break;
        
    case 'delete':
        try {
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            $notificationId = $_POST['notification_id'] ?? null;
            
            if (empty($notificationId)) {
                jsonResponse(false, null, 'Bildirim ID gereklidir.');
            }
            
            // Bildirimi sil
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'delete_notification', "Bildirim silindi: #$notificationId");
            
            jsonResponse(true, null, 'Bildirim başarıyla silindi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bildirim silinirken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 