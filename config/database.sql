-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS owo_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE owo_system;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    package_type TINYINT NOT NULL DEFAULT 1,
    package_expires_at DATETIME,
    status TINYINT NOT NULL DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tokenler tablosu
CREATE TABLE IF NOT EXISTS tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    is_captcha TINYINT NOT NULL DEFAULT 0,
    total_owo INT NOT NULL DEFAULT 0,
    total_messages INT NOT NULL DEFAULT 0,
    last_message_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Anahtarlar tablosu
CREATE TABLE IF NOT EXISTS `keys` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    package_type TINYINT NOT NULL,
    duration INT NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    used_by INT,
    used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Loglar tablosu
CREATE TABLE IF NOT EXISTS logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Ayarlar tablosu
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Varsayılan admin kullanıcısı
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Varsayılan ayarlar
INSERT INTO settings (`key`, value) VALUES
('site_name', 'OWO System'),
('site_url', 'http://localhost/owo-system'),
('admin_email', 'admin@example.com'),
('message_interval', '1000'),
('captcha_timeout', '30'),
('max_tokens_per_user', '5'),
('basic_package_limit', '2'),
('premium_package_limit', '5'),
('enterprise_package_limit', '10'),
('basic_package_duration', '30'),
('premium_package_duration', '90'),
('enterprise_package_duration', '365'),
('maintenance_mode', '0'),
('maintenance_message', 'Sistem bakımda. Lütfen daha sonra tekrar deneyiniz.'); 