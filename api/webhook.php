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

try {
    // JSON verisini al
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonResponse(false, null, 'Geçersiz JSON verisi.');
    }
    
    // Gerekli alanları kontrol et
    $requiredFields = ['token', 'message', 'channel_id', 'user_id', 'owo_amount'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            jsonResponse(false, null, "Eksik alan: $field");
        }
    }
    
    // Token'ı kontrol et
    $stmt = $db->prepare("
        SELECT t.*, u.package_expires_at, u.status as user_status
        FROM tokens t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.token = ?
    ");
    $stmt->execute([$data['token']]);
    $token = $stmt->fetch();
    
    if (!$token) {
        jsonResponse(false, null, 'Geçersiz token.');
    }
    
    // Token durumunu kontrol et
    if (!$token['status']) {
        jsonResponse(false, null, 'Token pasif durumda.');
    }
    
    // Kullanıcı durumunu kontrol et
    if (!$token['user_status']) {
        jsonResponse(false, null, 'Kullanıcı hesabı pasif durumda.');
    }
    
    // Paket süresini kontrol et
    if (strtotime($token['package_expires_at']) < time()) {
        jsonResponse(false, null, 'Paket süresi dolmuş.');
    }
    
    // Captcha kontrolü
    if ($token['is_captcha']) {
        $settings = $db->query("SELECT value FROM settings WHERE `key` = 'captcha_timeout'")->fetch();
        $timeout = $settings ? intval($settings['value']) : 30;
        
        if ($token['last_message_at'] && (time() - strtotime($token['last_message_at'])) < $timeout) {
            jsonResponse(false, null, 'Captcha bekleme süresi dolmadı.');
        }
    }
    
    // Message interval kontrolü
    $settings = $db->query("SELECT value FROM settings WHERE `key` = 'message_interval'")->fetch();
    $interval = $settings ? intval($settings['value']) : 1000;
    
    if ($token['last_message_at'] && (time() - strtotime($token['last_message_at'])) < ($interval / 1000)) {
        jsonResponse(false, null, 'Mesaj gönderme aralığı çok kısa.');
    }
    
    // Token istatistiklerini güncelle
    $stmt = $db->prepare("
        UPDATE tokens 
        SET total_owo = total_owo + ?,
            total_messages = total_messages + 1,
            last_message_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$data['owo_amount'], $token['id']]);
    
    // Log kaydı
    logAction($token['user_id'], 'owo_message', "Token: {$token['name']}, OWO: {$data['owo_amount']}");
    
    jsonResponse(true, null, 'Mesaj başarıyla işlendi.');
} catch (PDOException $e) {
    jsonResponse(false, null, 'Mesaj işlenirken bir hata oluştu.');
} 