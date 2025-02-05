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
    case 'get':
        try {
            // Kullanıcı bilgilerini al
            $stmt = $db->prepare("
                SELECT id, username, email, role, package_type, package_expires_at,
                    total_owo, total_messages, is_active, last_login, created_at
                FROM users
                WHERE id = ?
            ");
            
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            // Hassas bilgileri temizle
            unset($user['password']);
            
            // Paket bilgisini ekle
            $user['package_name'] = getPackageTypeName($user['package_type']);
            
            jsonResponse(true, ['user' => $user]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı bilgileri alınırken bir hata oluştu.');
        }
        break;
        
    case 'update':
        try {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            
            if (empty($username) || empty($email)) {
                jsonResponse(false, null, 'Kullanıcı adı ve e-posta gerekli.');
            }
            
            // Kullanıcı adı ve e-posta kontrolü
            $stmt = $db->prepare("
                SELECT id 
                FROM users 
                WHERE (username = ? OR email = ?) 
                AND id != ?
            ");
            
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, null, 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.');
            }
            
            // Profili güncelle
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'update_profile', 'Profil bilgileri güncellendi');
            
            jsonResponse(true, null, 'Profil başarıyla güncellendi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Profil güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'change_password':
        try {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword)) {
                jsonResponse(false, null, 'Mevcut şifre ve yeni şifre gerekli.');
            }
            
            // Minimum şifre uzunluğu kontrolü
            if (strlen($newPassword) < 6) {
                jsonResponse(false, null, 'Yeni şifre en az 6 karakter olmalıdır.');
            }
            
            // Mevcut şifreyi kontrol et
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                jsonResponse(false, null, 'Mevcut şifre hatalı.');
            }
            
            // Yeni şifreyi güncelle
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'change_password', 'Şifre değiştirildi');
            
            jsonResponse(true, null, 'Şifre başarıyla değiştirildi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Şifre değiştirilirken bir hata oluştu.');
        }
        break;
        
    case 'activity':
        try {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // Toplam kayıt sayısı
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM logs 
                WHERE user_id = ?
            ");
            
            $stmt->execute([$_SESSION['user_id']]);
            $total = $stmt->fetchColumn();
            
            // Aktivite loglarını al
            $stmt = $db->prepare("
                SELECT *
                FROM logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
            $logs = $stmt->fetchAll();
            
            jsonResponse(true, [
                'logs' => $logs,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Aktivite geçmişi alınırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 