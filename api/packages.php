<?php
require_once '../config/config.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    apiResponse(false, 'Oturum açmanız gerekiyor!');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        // Paket listesi
        $packages = [];
        foreach (PACKAGES as $id => $package) {
            $packages[] = array_merge(['id' => $id], $package);
        }
        
        if (isset($_GET['id'])) {
            // Tek paket detayı
            $packageId = (int)$_GET['id'];
            $package = $packages[$packageId - 1] ?? null;
            
            if (!$package) {
                apiResponse(false, 'Paket bulunamadı!');
            }
            
            // Paket istatistikleri
            if ($_SESSION['role'] === 'admin') {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total_users,
                           SUM(total_owo) as total_owo,
                           SUM(total_messages) as total_messages
                    FROM users
                    WHERE package_type = ?
                ");
                $stmt->execute([$packageId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $package['stats'] = $stats;
            }
            
            apiResponse(true, 'Paket detayları', $package);
        } else {
            // Tüm paketler
            if ($_SESSION['role'] === 'admin') {
                // Admin için istatistikler
                $stmt = $db->query("
                    SELECT package_type,
                           COUNT(*) as total_users,
                           SUM(total_owo) as total_owo,
                           SUM(total_messages) as total_messages
                    FROM users
                    WHERE package_type IS NOT NULL
                    GROUP BY package_type
                ");
                $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($packages as &$package) {
                    foreach ($stats as $stat) {
                        if ($stat['package_type'] == $package['id']) {
                            $package['stats'] = $stat;
                            break;
                        }
                    }
                }
            }
            
            apiResponse(true, 'Paket listesi', $packages);
        }
        break;
        
    case 'POST':
        // Key oluştur
        if ($_SESSION['role'] !== 'admin') {
            apiResponse(false, 'Bu işlem için yetkiniz yok!');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['package_type'])) {
            apiResponse(false, 'Paket tipi gerekli!');
        }
        
        $packageType = (int)$data['package_type'];
        $count = isset($data['count']) ? (int)$data['count'] : 1;
        
        if (!isset(PACKAGES[$packageType])) {
            apiResponse(false, 'Geçersiz paket tipi!');
        }
        
        if ($count < 1 || $count > 10) {
            apiResponse(false, 'Geçersiz key sayısı! (1-10 arası olmalı)');
        }
        
        try {
            $keys = [];
            $db->beginTransaction();
            
            for ($i = 0; $i < $count; $i++) {
                $key = generateKey();
                
                $stmt = $db->prepare("
                    INSERT INTO `keys` (`key`, package_type, created_by) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$key, $packageType, $_SESSION['user_id']]);
                
                $keys[] = $key;
            }
            
            $db->commit();
            
            // Log ekle
            addLog(
                $_SESSION['user_id'], 
                'key_create', 
                "Yeni key'ler oluşturuldu: " . implode(', ', $keys)
            );
            
            apiResponse(true, 'Key\'ler başarıyla oluşturuldu!', [
                'keys' => $keys,
                'package_type' => $packageType,
                'package_name' => PACKAGES[$packageType]['name']
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            apiResponse(false, 'Key oluşturulurken bir hata oluştu: ' . $e->getMessage());
        }
        break;
        
    default:
        apiResponse(false, 'Geçersiz istek metodu!');
}
?> 