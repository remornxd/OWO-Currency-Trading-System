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
    case 'list':
        try {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            $filters = [];
            $params = [];
            
            // Filtreler
            if (!empty($_POST['user_id'])) {
                $filters[] = "user_id = ?";
                $params[] = $_POST['user_id'];
            }
            
            if (!empty($_POST['action_type'])) {
                $filters[] = "action = ?";
                $params[] = $_POST['action_type'];
            }
            
            if (!empty($_POST['start_date'])) {
                $filters[] = "created_at >= ?";
                $params[] = $_POST['start_date'] . ' 00:00:00';
            }
            
            if (!empty($_POST['end_date'])) {
                $filters[] = "created_at <= ?";
                $params[] = $_POST['end_date'] . ' 23:59:59';
            }
            
            // Toplam kayıt sayısı
            $countQuery = "SELECT COUNT(*) FROM logs";
            if (!empty($filters)) {
                $countQuery .= " WHERE " . implode(" AND ", $filters);
            }
            
            $stmt = $db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Log kayıtları
            $query = "
                SELECT l.*, u.username
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.id
            ";
            
            if (!empty($filters)) {
                $query .= " WHERE " . implode(" AND ", $filters);
            }
            
            $query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            
            jsonResponse(true, [
                'logs' => $logs,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Loglar alınırken bir hata oluştu.');
        }
        break;
        
    case 'clear':
        try {
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            
            if ($days < 1) {
                jsonResponse(false, null, 'Geçersiz gün sayısı.');
            }
            
            $stmt = $db->prepare("
                DELETE FROM logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$days]);
            $deletedCount = $stmt->rowCount();
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'clear_logs', "$days günden eski $deletedCount log silindi");
            
            jsonResponse(true, [
                'deleted_count' => $deletedCount
            ], 'Eski loglar başarıyla temizlendi.');
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Loglar temizlenirken bir hata oluştu.');
        }
        break;
        
    case 'export':
        try {
            $filters = [];
            $params = [];
            
            // Filtreler
            if (!empty($_POST['user_id'])) {
                $filters[] = "l.user_id = ?";
                $params[] = $_POST['user_id'];
            }
            
            if (!empty($_POST['action_type'])) {
                $filters[] = "l.action = ?";
                $params[] = $_POST['action_type'];
            }
            
            if (!empty($_POST['start_date'])) {
                $filters[] = "l.created_at >= ?";
                $params[] = $_POST['start_date'] . ' 00:00:00';
            }
            
            if (!empty($_POST['end_date'])) {
                $filters[] = "l.created_at <= ?";
                $params[] = $_POST['end_date'] . ' 23:59:59';
            }
            
            $query = "
                SELECT l.*, u.username
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.id
            ";
            
            if (!empty($filters)) {
                $query .= " WHERE " . implode(" AND ", $filters);
            }
            
            $query .= " ORDER BY l.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            
            // CSV formatına dönüştür
            $csv = "Tarih,Kullanıcı,İşlem,Detay,IP Adresi\n";
            foreach ($logs as $log) {
                $csv .= implode(',', [
                    $log['created_at'],
                    $log['username'] ?? 'Sistem',
                    $log['action'],
                    str_replace(',', ';', $log['details']),
                    $log['ip_address']
                ]) . "\n";
            }
            
            // Base64 encode
            $base64 = base64_encode($csv);
            
            // Log kaydı
            logAction($_SESSION['user_id'], 'export_logs', count($logs) . " log dışa aktarıldı");
            
            jsonResponse(true, [
                'csv_base64' => $base64,
                'filename' => 'logs_' . date('Y-m-d_H-i-s') . '.csv'
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Loglar dışa aktarılırken bir hata oluştu.');
        }
        break;
        
    case 'stats':
        try {
            // Son 7 günün istatistikleri
            $stmt = $db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT action) as unique_actions
                FROM logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $dailyStats = $stmt->fetchAll();
            
            // En çok yapılan işlemler
            $stmt = $db->query("
                SELECT action, COUNT(*) as count
                FROM logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY action
                ORDER BY count DESC
                LIMIT 5
            ");
            $topActions = $stmt->fetchAll();
            
            // En aktif kullanıcılar
            $stmt = $db->query("
                SELECT u.username, COUNT(*) as count
                FROM logs l
                JOIN users u ON l.user_id = u.id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY l.user_id
                ORDER BY count DESC
                LIMIT 5
            ");
            $topUsers = $stmt->fetchAll();
            
            jsonResponse(true, [
                'daily_stats' => $dailyStats,
                'top_actions' => $topActions,
                'top_users' => $topUsers
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'İstatistikler alınırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 