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
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            $filters = [];
            $params = [];
            
            // Filtreler
            if (!empty($_POST['package_type'])) {
                $filters[] = "k.package_type = ?";
                $params[] = $_POST['package_type'];
            }
            
            if (isset($_POST['is_used'])) {
                $filters[] = "k.is_used = ?";
                $params[] = $_POST['is_used'];
            }
            
            // Toplam kayıt sayısı
            $countQuery = "SELECT COUNT(*) FROM `keys` k";
            if (!empty($filters)) {
                $countQuery .= " WHERE " . implode(" AND ", $filters);
            }
            
            $stmt = $db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Key listesi
            $query = "
                SELECT k.*, 
                    c.username as created_by_username,
                    u.username as used_by_username
                FROM `keys` k
                LEFT JOIN users c ON k.created_by = c.id
                LEFT JOIN users u ON k.used_by = u.id
            ";
            
            if (!empty($filters)) {
                $query .= " WHERE " . implode(" AND ", $filters);
            }
            
            $query .= " ORDER BY k.created_at DESC LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $keys = $stmt->fetchAll();
            
            jsonResponse(true, [
                'keys' => $keys,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Keyler alınırken bir hata oluştu.');
        }
        break;
        
    case 'generate':
        try {
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            $packageType = $_POST['package_type'] ?? null;
            $count = isset($_POST['count']) ? (int)$_POST['count'] : 1;
            
            if (!$packageType || !isset(PACKAGES[$packageType])) {
                jsonResponse(false, null, 'Geçersiz paket tipi.');
            }
            
            if ($count < 1 || $count > 10) {
                jsonResponse(false, null, 'Geçersiz key sayısı (1-10 arası olmalı).');
            }
            
            $keys = [];
            $db->beginTransaction();
            
            try {
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
                
                // Log kaydı
                logAction(
                    $_SESSION['user_id'],
                    'generate_keys',
                    "Paket tipi: $packageType, Adet: $count"
                );
                
                jsonResponse(true, [
                    'keys' => $keys,
                    'package_type' => $packageType,
                    'package_name' => PACKAGES[$packageType]['name']
                ], 'Keyler başarıyla oluşturuldu.');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Keyler oluşturulurken bir hata oluştu.');
        }
        break;
        
    case 'validate':
        try {
            $key = $_POST['key'] ?? '';
            
            if (empty($key)) {
                jsonResponse(false, null, 'Key gerekli.');
            }
            
            $stmt = $db->prepare("
                SELECT k.*, p.name as package_name
                FROM `keys` k
                LEFT JOIN packages p ON k.package_type = p.id
                WHERE k.key = ?
            ");
            
            $stmt->execute([$key]);
            $keyData = $stmt->fetch();
            
            if (!$keyData) {
                jsonResponse(false, null, 'Geçersiz key.');
            }
            
            if ($keyData['is_used']) {
                jsonResponse(false, null, 'Bu key daha önce kullanılmış.');
            }
            
            jsonResponse(true, [
                'package_type' => $keyData['package_type'],
                'package_name' => $keyData['package_name']
            ], 'Key geçerli.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Key doğrulanırken bir hata oluştu.');
        }
        break;
        
    case 'delete':
        try {
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            $keyId = $_POST['key_id'] ?? null;
            
            if (!$keyId) {
                jsonResponse(false, null, 'Key ID gerekli.');
            }
            
            // Key'i kontrol et
            $stmt = $db->prepare("SELECT * FROM `keys` WHERE id = ?");
            $stmt->execute([$keyId]);
            $key = $stmt->fetch();
            
            if (!$key) {
                jsonResponse(false, null, 'Key bulunamadı.');
            }
            
            if ($key['is_used']) {
                jsonResponse(false, null, 'Kullanılmış key silinemez.');
            }
            
            // Key'i sil
            $stmt = $db->prepare("DELETE FROM `keys` WHERE id = ?");
            $stmt->execute([$keyId]);
            
            // Log kaydı
            logAction(
                $_SESSION['user_id'],
                'delete_key',
                "Key silindi: {$key['key']}"
            );
            
            jsonResponse(true, null, 'Key başarıyla silindi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Key silinirken bir hata oluştu.');
        }
        break;
        
    case 'stats':
        try {
            // Admin kontrolü
            if ($_SESSION['role'] !== 'admin') {
                jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
            }
            
            // Paket tipine göre key istatistikleri
            $stmt = $db->query("
                SELECT 
                    k.package_type,
                    COUNT(*) as total,
                    SUM(k.is_used) as used,
                    COUNT(*) - SUM(k.is_used) as available
                FROM `keys` k
                GROUP BY k.package_type
            ");
            $packageStats = $stmt->fetchAll();
            
            // Son 7 günün key kullanım istatistikleri
            $stmt = $db->query("
                SELECT 
                    DATE(used_at) as date,
                    COUNT(*) as count,
                    package_type
                FROM `keys`
                WHERE is_used = 1
                AND used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(used_at), package_type
                ORDER BY date DESC
            ");
            $usageStats = $stmt->fetchAll();
            
            jsonResponse(true, [
                'package_stats' => $packageStats,
                'usage_stats' => $usageStats
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'İstatistikler alınırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 