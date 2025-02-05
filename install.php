<?php
// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fonksiyonlar
function showError($message) {
    die("<div style='color: red; margin: 10px;'>Hata: $message</div>");
}

function showSuccess($message) {
    echo "<div style='color: green; margin: 10px;'>$message</div>";
}

// Veritabanı bağlantı bilgileri
$host = 'localhost';
$dbname = 'owo_system';
$username = 'root';
$password = '';

try {
    // MySQL bağlantısı
    $db = new PDO("mysql:host=$host", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Veritabanını oluştur
    $db->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->exec("USE $dbname");
    
    showSuccess("Veritabanı oluşturuldu: $dbname");
    
    // Kullanıcılar tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        role ENUM('user', 'admin') DEFAULT 'user',
        package_type INT,
        package_expires_at DATETIME,
        total_owo BIGINT DEFAULT 0,
        total_messages INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    showSuccess("Kullanıcılar tablosu oluşturuldu");
    
    // Tokenler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(255) NOT NULL UNIQUE,
        channel_id VARCHAR(20) NOT NULL,
        status ENUM('available', 'busy', 'banned') DEFAULT 'available',
        owo_balance BIGINT DEFAULT 0,
        total_messages INT DEFAULT 0,
        current_user_id INT,
        is_running BOOLEAN DEFAULT FALSE,
        captcha_detected BOOLEAN DEFAULT FALSE,
        last_used DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (current_user_id) REFERENCES users(id)
    )");
    showSuccess("Tokenler tablosu oluşturuldu");
    
    // Keyler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS `keys` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(50) NOT NULL UNIQUE,
        package_type INT NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        used_by INT,
        used_at DATETIME,
        created_by INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (used_by) REFERENCES users(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    showSuccess("Keyler tablosu oluşturuldu");
    
    // Loglar tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    showSuccess("Loglar tablosu oluşturuldu");
    
    // İstatistikler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        total_users INT DEFAULT 0,
        active_users INT DEFAULT 0,
        total_tokens INT DEFAULT 0,
        active_tokens INT DEFAULT 0,
        total_owo BIGINT DEFAULT 0,
        total_messages INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    showSuccess("İstatistikler tablosu oluşturuldu");
    
    // Bildirimler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    showSuccess("Bildirimler tablosu oluşturuldu");
    
    // Varsayılan admin kullanıcısı
    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $password, 'admin@example.com', 'admin']);
        showSuccess("Varsayılan admin kullanıcısı oluşturuldu (admin/admin123)");
    }
    
    echo "<div style='margin: 10px;'><strong>Kurulum tamamlandı!</strong></div>";
    
} catch(PDOException $e) {
    showError($e->getMessage());
}
?> 