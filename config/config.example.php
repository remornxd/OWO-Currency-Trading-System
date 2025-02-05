<?php
// Oturum başlat
session_start();

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Gerekli dosyaları dahil et
require_once __DIR__ . '/../includes/functions.php';
?> 