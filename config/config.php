<?php
// Oturum başlat
session_start();

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../error.log');

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'owo_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site bilgileri
define('SITE_NAME', 'OWO System');
define('SITE_URL', 'http://localhost/8080');

// Veritabanı bağlantısı
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    die("Veritabanı bağlantı hatası. Detaylar için error.log dosyasını kontrol edin.");
}

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../includes/functions.php';

// Hata işleme fonksiyonu
function handleError($errno, $errstr, $errfile, $errline) {
    $message = date('Y-m-d H:i:s') . " - Error [$errno]: $errstr in $errfile on line $errline\n";
    error_log($message);
    
    if (ini_get('display_errors')) {
        echo "<div style='color:red;'><pre>$message</pre></div>";
    }
    
    return true;
}

// Hata işleyiciyi ayarla
set_error_handler('handleError');

// Fatal hataları yakala
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        handleError($error['type'], $error['message'], $error['file'], $error['line']);
    }
});

// Ayarları veritabanından yükle
try {
    $stmt = $db->query("SELECT * FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $settings = [];
}

// Bakım modu kontrolü
if (
    ($settings['maintenance_mode'] ?? false) &&
    (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') &&
    !in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'maintenance.php', 'error.php'])
) {
    header('Location: ' . SITE_URL . '/maintenance.php');
    exit();
}

// Yardımcı fonksiyonlar
function getPackageTypeName($type) {
    $types = [
        1 => 'Temel Paket',
        2 => 'Premium Paket',
        3 => 'Kurumsal Paket'
    ];
    return $types[$type] ?? 'Bilinmeyen Paket';
}

function logAction($user_id, $action, $details = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO logs (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function generateKey($length = 16) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    
    for ($i = 0; $i < $length; $i++) {
        $key .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $key;
}

function jsonResponse($success = true, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit();
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            jsonResponse(false, null, 'Oturum süresi doldu. Lütfen tekrar giriş yapın.');
        } else {
            header('Location: ' . SITE_URL . '/pages/login.php');
            exit();
        }
    }
}

function requireAdmin() {
    requireAuth();
    
    if ($_SESSION['role'] !== 'admin') {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            jsonResponse(false, null, 'Bu işlem için yetkiniz bulunmuyor.');
        } else {
            header('Location: ' . SITE_URL . '/pages/dashboard.php');
            exit();
        }
    }
}

// JWT ayarları
define('JWT_SECRET', 'your-secret-key-here');
define('JWT_EXPIRE', 3600); // 1 saat

// Discord API ayarları
define('DISCORD_TOKEN', 'your-discord-token');
define('DISCORD_CLIENT_ID', 'your-client-id');

// Paket ayarları
define('PACKAGES', [
    1 => [
        'name' => 'Başlangıç Paketi',
        'amount' => '2M OWO',
        'price' => '50 TL',
        'color' => '#4e73df'
    ],
    2 => [
        'name' => 'Orta Paket',
        'amount' => '5M OWO',
        'price' => '100 TL',
        'color' => '#1cc88a'
    ],
    3 => [
        'name' => 'Pro Paket',
        'amount' => '10M OWO',
        'price' => '180 TL',
        'color' => '#e74a3b'
    ]
]);
?> 