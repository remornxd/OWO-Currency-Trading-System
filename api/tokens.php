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
            
            // Token listesini al
            $query = "
                SELECT t.*, u.username
                FROM tokens t
                LEFT JOIN users u ON t.user_id = u.id
            ";
            
            if (!$isAdmin) {
                $query .= " WHERE t.user_id = ?";
            }
            
            $query .= " ORDER BY t.created_at DESC";
            
            $stmt = $db->prepare($query);
            
            if (!$isAdmin) {
                $stmt->execute([$userId]);
            } else {
                $stmt->execute();
            }
            
            $tokens = $stmt->fetchAll();
            
            jsonResponse(true, ['tokens' => $tokens]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Token listesi alınırken bir hata oluştu.');
        }
        break;
        
    case 'create':
        try {
            $userId = $_SESSION['user_id'];
            $name = $_POST['name'] ?? '';
            $isCaptcha = isset($_POST['is_captcha']) ? 1 : 0;
            
            if (empty($name)) {
                jsonResponse(false, null, 'Token adı gereklidir.');
            }
            
            // Kullanıcının paket limitini kontrol et
            $stmt = $db->prepare("
                SELECT u.package_type, COUNT(t.id) as token_count,
                    s1.value as basic_limit,
                    s2.value as premium_limit,
                    s3.value as enterprise_limit
                FROM users u
                LEFT JOIN tokens t ON u.id = t.user_id
                LEFT JOIN settings s1 ON s1.key = 'basic_package_limit'
                LEFT JOIN settings s2 ON s2.key = 'premium_package_limit'
                LEFT JOIN settings s3 ON s3.key = 'enterprise_package_limit'
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$userId]);
            $packageInfo = $stmt->fetch();
            
            $limits = [
                1 => $packageInfo['basic_limit'],
                2 => $packageInfo['premium_limit'],
                3 => $packageInfo['enterprise_limit']
            ];
            
            if ($packageInfo['token_count'] >= $limits[$packageInfo['package_type']]) {
                jsonResponse(false, null, 'Token limitinize ulaştınız.');
            }
            
            // Token oluştur
            $token = bin2hex(random_bytes(32));
            
            $stmt = $db->prepare("
                INSERT INTO tokens (user_id, token, name, is_captcha)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$userId, $token, $name, $isCaptcha]);
            
            // Log kaydı
            logAction($userId, 'create_token', "Yeni token oluşturuldu: $name");
            
            jsonResponse(true, null, 'Token başarıyla oluşturuldu.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Token oluşturulurken bir hata oluştu.');
        }
        break;
        
    case 'delete':
        try {
            $userId = $_SESSION['user_id'];
            $tokenId = $_POST['token_id'] ?? '';
            $isAdmin = $_SESSION['role'] === 'admin';
            
            if (empty($tokenId)) {
                jsonResponse(false, null, 'Token ID gereklidir.');
            }
            
            // Token'ı kontrol et
            $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
            $token = $stmt->fetch();
            
            if (!$token) {
                jsonResponse(false, null, 'Token bulunamadı.');
            }
            
            // Yetki kontrolü
            if (!$isAdmin && $token['user_id'] !== $userId) {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            // Token'ı sil
            $stmt = $db->prepare("DELETE FROM tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
            
            // Log kaydı
            logAction($userId, 'delete_token', "Token silindi: {$token['name']}");
            
            jsonResponse(true, null, 'Token başarıyla silindi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Token silinirken bir hata oluştu.');
        }
        break;
        
    case 'toggle_status':
        try {
            $userId = $_SESSION['user_id'];
            $tokenId = $_POST['token_id'] ?? '';
            $isAdmin = $_SESSION['role'] === 'admin';
            
            if (empty($tokenId)) {
                jsonResponse(false, null, 'Token ID gereklidir.');
            }
            
            // Token'ı kontrol et
            $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
            $token = $stmt->fetch();
            
            if (!$token) {
                jsonResponse(false, null, 'Token bulunamadı.');
            }
            
            // Yetki kontrolü
            if (!$isAdmin && $token['user_id'] !== $userId) {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            // Token durumunu değiştir
            $newStatus = $token['status'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE tokens SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $tokenId]);
            
            // Log kaydı
            $action = $newStatus ? 'start_token' : 'stop_token';
            $message = $newStatus ? 'Token başlatıldı' : 'Token durduruldu';
            logAction($userId, $action, "$message: {$token['name']}");
            
            jsonResponse(true, null, $message);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Token durumu güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'reset_captcha':
        try {
            $userId = $_SESSION['user_id'];
            $tokenId = $_POST['token_id'] ?? '';
            $isAdmin = $_SESSION['role'] === 'admin';
            
            if (empty($tokenId)) {
                jsonResponse(false, null, 'Token ID gereklidir.');
            }
            
            // Token'ı kontrol et
            $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
            $token = $stmt->fetch();
            
            if (!$token) {
                jsonResponse(false, null, 'Token bulunamadı.');
            }
            
            // Yetki kontrolü
            if (!$isAdmin && $token['user_id'] !== $userId) {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            if (!$token['is_captcha']) {
                jsonResponse(false, null, 'Bu token captcha özelliğine sahip değil.');
            }
            
            // Captcha durumunu sıfırla
            $stmt = $db->prepare("UPDATE tokens SET last_message_at = NULL WHERE id = ?");
            $stmt->execute([$tokenId]);
            
            // Log kaydı
            logAction($userId, 'reset_captcha', "Captcha sıfırlandı: {$token['name']}");
            
            jsonResponse(true, null, 'Captcha başarıyla sıfırlandı.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Captcha sıfırlanırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
}

// Token işlemleri için özel fonksiyonlar
function startToken($tokenId) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Token'ı kontrol et
        $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ? FOR UPDATE");
        $stmt->execute([$tokenId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            throw new Exception('Token bulunamadı!');
        }
        
        if ($token['status'] !== 'available') {
            throw new Exception('Token şu anda kullanılamaz!');
        }
        
        // Token'ı başlat
        $stmt = $db->prepare("
            UPDATE tokens 
            SET status = 'busy', 
                current_user_id = ?, 
                is_running = 1, 
                last_used = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $tokenId]);
        
        $db->commit();
        
        // Discord bot'una bilgi gönder
        sendDiscordMessage($token['channel_id'], "owo balance");
        
        // Log ekle
        addLog($_SESSION['user_id'], 'token_start', "Token başlatıldı: $tokenId");
        
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function stopToken($tokenId) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Token'ı kontrol et
        $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ? FOR UPDATE");
        $stmt->execute([$tokenId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            throw new Exception('Token bulunamadı!');
        }
        
        if ($token['status'] !== 'busy') {
            throw new Exception('Token zaten çalışmıyor!');
        }
        
        // Yetki kontrolü
        if ($_SESSION['role'] !== 'admin' && $token['current_user_id'] != $_SESSION['user_id']) {
            throw new Exception('Bu token üzerinde işlem yapma yetkiniz yok!');
        }
        
        // Token'ı durdur
        $stmt = $db->prepare("
            UPDATE tokens 
            SET status = 'available', 
                is_running = 0, 
                last_used = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
        
        $db->commit();
        
        // Log ekle
        addLog($_SESSION['user_id'], 'token_stop', "Token durduruldu: $tokenId");
        
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?> 