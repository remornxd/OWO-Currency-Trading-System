<?php
require_once '../config/config.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Oturum kontrolü
requireAuth();

// Admin kontrolü
requireAdmin();

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Geçersiz istek metodu.');
}

// İşlem türünü al
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        try {
            // Tüm ayarları al
            $stmt = $db->query("SELECT * FROM settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            jsonResponse(true, ['settings' => $settings]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Ayarlar alınırken bir hata oluştu.');
        }
        break;
        
    case 'update':
        try {
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings) || !is_array($settings)) {
                jsonResponse(false, null, 'Güncellenecek ayarlar gerekli.');
            }
            
            $db->beginTransaction();
            
            try {
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (`key`, value)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE value = ?
                    ");
                    
                    $stmt->execute([$key, $value, $value]);
                }
                
                $db->commit();
                
                // Log kaydı
                logAction(
                    $_SESSION['user_id'],
                    'update_settings',
                    'Sistem ayarları güncellendi: ' . implode(', ', array_keys($settings))
                );
                
                jsonResponse(true, null, 'Ayarlar başarıyla güncellendi.');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Ayarlar güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'maintenance':
        try {
            $mode = isset($_POST['mode']) ? (bool)$_POST['mode'] : false;
            $message = $_POST['message'] ?? '';
            
            if ($mode && empty($message)) {
                jsonResponse(false, null, 'Bakım modu için mesaj gerekli.');
            }
            
            $db->beginTransaction();
            
            try {
                // Bakım modunu güncelle
                $stmt = $db->prepare("
                    INSERT INTO settings (`key`, value)
                    VALUES ('maintenance_mode', ?)
                    ON DUPLICATE KEY UPDATE value = ?
                ");
                $stmt->execute([$mode ? '1' : '0', $mode ? '1' : '0']);
                
                // Bakım mesajını güncelle
                if ($mode) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (`key`, value)
                        VALUES ('maintenance_message', ?)
                        ON DUPLICATE KEY UPDATE value = ?
                    ");
                    $stmt->execute([$message, $message]);
                }
                
                $db->commit();
                
                // Log kaydı
                logAction(
                    $_SESSION['user_id'],
                    'maintenance_mode',
                    $mode ? 'Bakım modu aktifleştirildi' : 'Bakım modu kapatıldı'
                );
                
                jsonResponse(true, null, $mode ? 'Bakım modu aktifleştirildi.' : 'Bakım modu kapatıldı.');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bakım modu güncellenirken bir hata oluştu.');
        }
        break;
        
    case 'test_email':
        try {
            require_once '../includes/mailer.php';
            
            $email = $_POST['email'] ?? '';
            
            if (empty($email)) {
                jsonResponse(false, null, 'Test e-postası için e-posta adresi gerekli.');
            }
            
            $mailer = new Mailer();
            $mailer->sendWelcome($email, 'Test Kullanıcı');
            
            // Log kaydı
            logAction(
                $_SESSION['user_id'],
                'test_email',
                "Test e-postası gönderildi: $email"
            );
            
            jsonResponse(true, null, 'Test e-postası başarıyla gönderildi.');
        } catch (Exception $e) {
            jsonResponse(false, null, 'Test e-postası gönderilirken bir hata oluştu: ' . $e->getMessage());
        }
        break;
        
    case 'backup':
        try {
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $backup = [];
            
            foreach ($tables as $table) {
                // Tablo yapısı
                $stmt = $db->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $backup[] = $row['Create Table'] . ";\n\n";
                
                // Tablo verileri
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $values = [];
                    
                    foreach ($rows as $row) {
                        $rowValues = array_map(function($value) use ($db) {
                            if ($value === null) return 'NULL';
                            return $db->quote($value);
                        }, $row);
                        
                        $values[] = '(' . implode(', ', $rowValues) . ')';
                    }
                    
                    $backup[] = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n" 
                             . implode(",\n", $values) . ";\n\n";
                }
            }
            
            $backupContent = implode("\n", $backup);
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Log kaydı
            logAction(
                $_SESSION['user_id'],
                'backup_database',
                'Veritabanı yedeği alındı'
            );
            
            jsonResponse(true, [
                'filename' => $filename,
                'content' => base64_encode($backupContent)
            ], 'Veritabanı yedeği başarıyla oluşturuldu.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Veritabanı yedeği alınırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 