<?php
require_once '../config/config.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Oturum kontrolü
requireAuth();

// Admin kontrolü (bazı işlemler için)
$isAdmin = $_SESSION['role'] === 'admin';

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Geçersiz istek metodu.');
}

// İşlem türünü al
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            // Kullanıcı listesini al
            $stmt = $db->prepare("
                SELECT u.*,
                    CASE 
                        WHEN u.package_type = 1 THEN 'Temel Paket'
                        WHEN u.package_type = 2 THEN 'Premium Paket'
                        WHEN u.package_type = 3 THEN 'Kurumsal Paket'
                        ELSE 'Bilinmeyen Paket'
                    END as package_name,
                    COUNT(t.id) as token_count,
                    SUM(t.total_owo) as total_owo,
                    SUM(t.total_messages) as total_messages
                FROM users u
                LEFT JOIN tokens t ON u.id = t.user_id
                WHERE u.role != 'admin'
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            jsonResponse(true, ['users' => $users]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı listesi alınırken bir hata oluştu.');
        }
        break;
        
    case 'add':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $packageType = $_POST['package_type'] ?? 1;
            $duration = $_POST['duration'] ?? 30;
            
            if (empty($username) || empty($email) || empty($password)) {
                jsonResponse(false, null, 'Kullanıcı adı, e-posta ve şifre gereklidir.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, null, 'Geçersiz e-posta adresi.');
            }
            
            if (strlen($password) < 6) {
                jsonResponse(false, null, 'Şifre en az 6 karakter olmalıdır.');
            }
            
            // Kullanıcı adı ve e-posta kontrolü
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, null, 'Bu kullanıcı adı veya e-posta adresi kullanılmaktadır.');
            }
            
            // Kullanıcıyı oluştur
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, package_type, package_expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
            ");
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $packageType,
                $duration
            ]);
            
            $userId = $db->lastInsertId();
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'add_user', "Yeni kullanıcı eklendi: $username");
            
            jsonResponse(true, null, 'Kullanıcı başarıyla oluşturuldu.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı oluşturulurken bir hata oluştu.');
        }
        break;
        
    case 'edit':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $userId = $_POST['user_id'] ?? '';
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($userId) || empty($username) || empty($email)) {
                jsonResponse(false, null, 'Kullanıcı ID, kullanıcı adı ve e-posta gereklidir.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, null, 'Geçersiz e-posta adresi.');
            }
            
            // Kullanıcıyı kontrol et
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            // Kullanıcı adı ve e-posta kontrolü (kendisi hariç)
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $userId]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, null, 'Bu kullanıcı adı veya e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.');
            }
            
            // Kullanıcıyı güncelle
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    jsonResponse(false, null, 'Şifre en az 6 karakter olmalıdır.');
                }
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, password = ?
                    WHERE id = ?
                ");
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $hashedPassword, $userId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET username = ?, email = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([$username, $email, $userId]);
            }
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'edit_user', "Kullanıcı düzenlendi: $username");
            
            jsonResponse(true, null, 'Kullanıcı başarıyla güncellendi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'delete':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $userId = $_POST['user_id'] ?? '';
            
            if (empty($userId)) {
                jsonResponse(false, null, 'Kullanıcı ID gereklidir.');
            }
            
            // Kullanıcıyı kontrol et
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            if ($user['role'] === 'admin') {
                jsonResponse(false, null, 'Admin kullanıcıları silinemez.');
            }
            
            // Kullanıcıyı sil (cascade ile ilişkili veriler de silinecek)
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'delete_user', "Kullanıcı silindi: {$user['username']}");
            
            jsonResponse(true, null, 'Kullanıcı başarıyla silindi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı silinirken bir hata oluştu.');
        }
        break;
        
    case 'toggle_status':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $userId = $_POST['user_id'] ?? '';
            
            if (empty($userId)) {
                jsonResponse(false, null, 'Kullanıcı ID gereklidir.');
            }
            
            // Kullanıcıyı kontrol et
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            if ($user['role'] === 'admin') {
                jsonResponse(false, null, 'Admin kullanıcıların durumu değiştirilemez.');
            }
            
            // Kullanıcı durumunu değiştir
            $newStatus = $user['status'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);
            
            // Log kaydı
            $action = $newStatus ? 'activate_user' : 'deactivate_user';
            $message = $newStatus ? 'Kullanıcı aktifleştirildi' : 'Kullanıcı deaktifleştirildi';
            logAction($_SESSION['user_id'], $action, "$message: {$user['username']}");
            
            jsonResponse(true, null, $message);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Kullanıcı durumu güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'extend_package':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $userId = $_POST['user_id'] ?? '';
            $duration = $_POST['duration'] ?? '';
            
            if (empty($userId) || empty($duration)) {
                jsonResponse(false, null, 'Kullanıcı ID ve süre gereklidir.');
            }
            
            if (!is_numeric($duration) || $duration < 1) {
                jsonResponse(false, null, 'Geçersiz süre.');
            }
            
            // Kullanıcıyı kontrol et
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            // Paketi uzat
            $stmt = $db->prepare("
                UPDATE users 
                SET package_expires_at = DATE_ADD(
                    CASE 
                        WHEN package_expires_at > NOW() 
                        THEN package_expires_at 
                        ELSE NOW() 
                    END, 
                    INTERVAL ? DAY
                )
                WHERE id = ?
            ");
            $stmt->execute([$duration, $userId]);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'extend_package', "Paket uzatıldı: {$user['username']} (+$duration gün)");
            
            jsonResponse(true, null, 'Paket süresi başarıyla uzatıldı.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Paket süresi uzatılırken bir hata oluştu.');
        }
        break;
        
    case 'change_package':
        if (!$isAdmin) {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        }
        
        try {
            $userId = $_POST['user_id'] ?? '';
            $packageType = $_POST['package_type'] ?? '';
            
            if (empty($userId) || empty($packageType)) {
                jsonResponse(false, null, 'Kullanıcı ID ve paket tipi gereklidir.');
            }
            
            if (!in_array($packageType, [1, 2, 3])) {
                jsonResponse(false, null, 'Geçersiz paket tipi.');
            }
            
            // Kullanıcıyı kontrol et
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Kullanıcı bulunamadı.');
            }
            
            // Paketi değiştir
            $stmt = $db->prepare("UPDATE users SET package_type = ? WHERE id = ?");
            $stmt->execute([$packageType, $userId]);
            
            // Log kaydı
            $packageNames = [1 => 'Temel', 2 => 'Premium', 3 => 'Kurumsal'];
            logAction(
                $_SESSION['user_id'],
                'change_package',
                "Paket değiştirildi: {$user['username']} ({$packageNames[$packageType]} Paket)"
            );
            
            jsonResponse(true, null, 'Paket tipi başarıyla değiştirildi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Paket tipi değiştirilirken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
}
?> 