<?php
require_once '../config/config.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Geçersiz istek metodu.');
}

// İşlem türünü al
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            jsonResponse(false, null, 'Kullanıcı adı ve şifre gereklidir.');
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                jsonResponse(false, null, 'Geçersiz kullanıcı adı veya şifre.');
            }
            
            if ($user['status'] !== 1) {
                jsonResponse(false, null, 'Hesabınız aktif değil.');
            }
            
            // Oturum bilgilerini güncelle
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Son giriş tarihini güncelle
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log kaydı
            logAction($user['id'], 'login', 'Kullanıcı girişi yapıldı');
            
            jsonResponse(true, [
                'redirect' => SITE_URL . '/pages/' . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php')
            ], 'Giriş başarılı. Yönlendiriliyorsunuz...');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bir hata oluştu. Lütfen tekrar deneyin.');
        }
        break;
        
    case 'register':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $key = $_POST['key'] ?? '';
        
        if (empty($username) || empty($email) || empty($password) || empty($key)) {
            jsonResponse(false, null, 'Tüm alanları doldurunuz.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, null, 'Geçersiz e-posta adresi.');
        }
        
        if (strlen($password) < 6) {
            jsonResponse(false, null, 'Şifre en az 6 karakter olmalıdır.');
        }
        
        try {
            // Kullanıcı adı ve e-posta kontrolü
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, null, 'Bu kullanıcı adı veya e-posta adresi kullanılmaktadır.');
            }
            
            // Anahtar kontrolü
            $stmt = $db->prepare("SELECT * FROM `keys` WHERE `key` = ? AND status = 1 AND used_by IS NULL");
            $stmt->execute([$key]);
            $keyData = $stmt->fetch();
            
            if (!$keyData) {
                jsonResponse(false, null, 'Geçersiz veya kullanılmış anahtar.');
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
                $keyData['package_type'],
                $keyData['duration']
            ]);
            
            $userId = $db->lastInsertId();
            
            // Anahtarı kullanıldı olarak işaretle
            $stmt = $db->prepare("UPDATE `keys` SET used_by = ?, used_at = NOW(), status = 0 WHERE id = ?");
            $stmt->execute([$userId, $keyData['id']]);
            
            // Log kaydı
            logAction($userId, 'register', 'Yeni kullanıcı kaydı yapıldı');
            
            jsonResponse(true, [
                'redirect' => SITE_URL . '/pages/login.php'
            ], 'Kayıt başarılı. Giriş yapabilirsiniz.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bir hata oluştu. Lütfen tekrar deneyin.');
        }
        break;
        
    case 'forgot_password':
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            jsonResponse(false, null, 'E-posta adresi gereklidir.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, null, 'Geçersiz e-posta adresi.');
        }
        
        try {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, null, 'Bu e-posta adresi ile kayıtlı aktif kullanıcı bulunamadı.');
            }
            
            // Şifre sıfırlama token'ı oluştur
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $token, $expires]);
            
            // E-posta gönderimi burada yapılacak
            // TODO: Implement email sending
            
            jsonResponse(true, null, 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bir hata oluştu. Lütfen tekrar deneyin.');
        }
        break;
        
    case 'reset_password':
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($token) || empty($password)) {
            jsonResponse(false, null, 'Geçersiz istek.');
        }
        
        if (strlen($password) < 6) {
            jsonResponse(false, null, 'Şifre en az 6 karakter olmalıdır.');
        }
        
        try {
            $stmt = $db->prepare("
                SELECT user_id
                FROM password_resets
                WHERE token = ? AND expires_at > NOW() AND used = 0
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                jsonResponse(false, null, 'Geçersiz veya süresi dolmuş token.');
            }
            
            // Şifreyi güncelle
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            // Token'ı kullanıldı olarak işaretle
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log kaydı
            logAction($reset['user_id'], 'reset_password', 'Şifre sıfırlama işlemi yapıldı');
            
            jsonResponse(true, [
                'redirect' => SITE_URL . '/pages/login.php'
            ], 'Şifreniz başarıyla güncellendi. Giriş yapabilirsiniz.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bir hata oluştu. Lütfen tekrar deneyin.');
        }
        break;
        
    case 'logout':
        $userId = $_SESSION['user_id'] ?? null;
        
        session_destroy();
        
        if ($userId) {
            try {
                logAction($userId, 'logout', 'Kullanıcı çıkışı yapıldı');
            } catch (PDOException $e) {
                // Çıkış log hatası önemsiz
            }
        }
        
        jsonResponse(true, [
            'redirect' => SITE_URL . '/pages/login.php'
        ], 'Çıkış yapıldı.');
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
}
?> 