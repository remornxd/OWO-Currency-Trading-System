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
    case 'dashboard':
        try {
            // Admin kontrolü
            $isAdmin = $_SESSION['role'] === 'admin';
            
            if ($isAdmin) {
                // Genel istatistikler
                $stats = [
                    'users' => [
                        'total' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                        'active' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn()
                    ],
                    'tokens' => [
                        'total' => $db->query("SELECT COUNT(*) FROM tokens")->fetchColumn(),
                        'active' => $db->query("SELECT COUNT(*) FROM tokens WHERE status = 'available'")->fetchColumn(),
                        'busy' => $db->query("SELECT COUNT(*) FROM tokens WHERE status = 'busy'")->fetchColumn(),
                        'banned' => $db->query("SELECT COUNT(*) FROM tokens WHERE status = 'banned'")->fetchColumn()
                    ],
                    'keys' => [
                        'total' => $db->query("SELECT COUNT(*) FROM `keys`")->fetchColumn(),
                        'used' => $db->query("SELECT COUNT(*) FROM `keys` WHERE is_used = 1")->fetchColumn()
                    ],
                    'owo' => [
                        'total' => $db->query("SELECT SUM(total_owo) FROM tokens")->fetchColumn() ?? 0,
                        'today' => $db->query("
                            SELECT SUM(total_owo) 
                            FROM tokens 
                            WHERE DATE(last_message_at) = CURDATE()
                        ")->fetchColumn() ?? 0
                    ],
                    'messages' => [
                        'total' => $db->query("SELECT SUM(total_messages) FROM tokens")->fetchColumn() ?? 0,
                        'today' => $db->query("
                            SELECT COUNT(*) 
                            FROM logs 
                            WHERE action = 'owo_message'
                            AND DATE(created_at) = CURDATE()
                        ")->fetchColumn()
                    ]
                ];
            } else {
                // Kullanıcıya özel istatistikler
                $userId = $_SESSION['user_id'];
                
                $stats = [
                    'tokens' => [
                        'total' => $db->prepare("SELECT COUNT(*) FROM tokens WHERE user_id = ?")->execute([$userId])->fetchColumn(),
                        'active' => $db->prepare("SELECT COUNT(*) FROM tokens WHERE user_id = ? AND status = 'available'")->execute([$userId])->fetchColumn(),
                        'busy' => $db->prepare("SELECT COUNT(*) FROM tokens WHERE user_id = ? AND status = 'busy'")->execute([$userId])->fetchColumn(),
                        'banned' => $db->prepare("SELECT COUNT(*) FROM tokens WHERE user_id = ? AND status = 'banned'")->execute([$userId])->fetchColumn()
                    ],
                    'owo' => [
                        'total' => $db->prepare("SELECT SUM(total_owo) FROM tokens WHERE user_id = ?")->execute([$userId])->fetchColumn() ?? 0,
                        'today' => $db->prepare("
                            SELECT SUM(total_owo) 
                            FROM tokens 
                            WHERE user_id = ?
                            AND DATE(last_message_at) = CURDATE()
                        ")->execute([$userId])->fetchColumn() ?? 0
                    ],
                    'messages' => [
                        'total' => $db->prepare("SELECT SUM(total_messages) FROM tokens WHERE user_id = ?")->execute([$userId])->fetchColumn() ?? 0,
                        'today' => $db->prepare("
                            SELECT COUNT(*) 
                            FROM logs 
                            WHERE user_id = ?
                            AND action = 'owo_message'
                            AND DATE(created_at) = CURDATE()
                        ")->execute([$userId])->fetchColumn()
                    ]
                ];
            }
            
            jsonResponse(true, ['stats' => $stats]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'İstatistikler alınırken bir hata oluştu.');
        }
        break;
        
    case 'chart':
        try {
            $type = $_POST['type'] ?? 'owo';
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
            
            if ($days < 1 || $days > 30) {
                jsonResponse(false, null, 'Geçersiz gün sayısı (1-30 arası olmalı).');
            }
            
            $userId = $_SESSION['role'] === 'admin' ? null : $_SESSION['user_id'];
            
            switch ($type) {
                case 'owo':
                    $query = "
                        SELECT 
                            DATE(created_at) as date,
                            SUM(CAST(REGEXP_REPLACE(details, '[^0-9]', '') AS UNSIGNED)) as value
                        FROM logs
                        WHERE action = 'owo_message'
                        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    ";
                    break;
                    
                case 'messages':
                    $query = "
                        SELECT 
                            DATE(created_at) as date,
                            COUNT(*) as value
                        FROM logs
                        WHERE action = 'owo_message'
                        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    ";
                    break;
                    
                case 'tokens':
                    $query = "
                        SELECT 
                            DATE(created_at) as date,
                            COUNT(*) as value
                        FROM tokens
                        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    ";
                    break;
                    
                default:
                    jsonResponse(false, null, 'Geçersiz grafik tipi.');
            }
            
            if ($userId) {
                $query .= " AND user_id = ?";
            }
            
            $query .= " GROUP BY DATE(created_at) ORDER BY date ASC";
            
            $stmt = $db->prepare($query);
            $params = $userId ? [$days, $userId] : [$days];
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Eksik günleri doldur
            $chartData = [];
            $currentDate = new DateTime();
            $currentDate->modify("-$days days");
            
            while ($currentDate <= new DateTime()) {
                $date = $currentDate->format('Y-m-d');
                $value = 0;
                
                foreach ($data as $row) {
                    if ($row['date'] === $date) {
                        $value = (int)$row['value'];
                        break;
                    }
                }
                
                $chartData[] = [
                    'date' => $date,
                    'value' => $value
                ];
                
                $currentDate->modify('+1 day');
            }
            
            jsonResponse(true, [
                'labels' => array_column($chartData, 'date'),
                'data' => array_column($chartData, 'value')
            ]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Grafik verileri alınırken bir hata oluştu.');
        }
        break;
        
    default:
        jsonResponse(false, null, 'Geçersiz işlem.');
} 