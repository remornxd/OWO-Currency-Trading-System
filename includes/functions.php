<?php
// Sayı formatla
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

// Tarih formatla
function formatDate($date) {
    return date('d.m.Y H:i:s', strtotime($date));
}

// Güvenli input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Şifre hash'le
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Şifre doğrula
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Rastgele key oluştur
function generateKey($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $key;
}

// Token durumu için badge oluştur
function getStatusBadge($status) {
    $badges = [
        'available' => '<span class="badge bg-success">Aktif</span>',
        'busy' => '<span class="badge bg-warning">Meşgul</span>',
        'banned' => '<span class="badge bg-danger">Banlı</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">Bilinmiyor</span>';
}

// API yanıtı oluştur
function apiResponse($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// JWT token oluştur
function createJWT($user_id, $username, $role) {
    $payload = [
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role,
        'exp' => time() + JWT_EXPIRE
    ];
    
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $header = base64_encode($header);
    
    $payload = json_encode($payload);
    $payload = base64_encode($payload);
    
    $signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
    $signature = base64_encode($signature);
    
    return "$header.$payload.$signature";
}

// JWT token doğrula
function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = base64_decode($parts[0]);
    $payload = base64_decode($parts[1]);
    $signature = base64_decode($parts[2]);
    
    $valid = hash_hmac('sha256', "$parts[0].$parts[1]", JWT_SECRET, true);
    
    if ($signature !== $valid) {
        return false;
    }
    
    $payload = json_decode($payload, true);
    if ($payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

// Log kaydet
function addLog($user_id, $action, $details = '') {
    global $db;
    
    $stmt = $db->prepare("INSERT INTO logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $details]);
}

// Kullanıcı bilgilerini getir
function getUserInfo($user_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Token bilgilerini getir
function getTokenInfo($token_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM tokens WHERE id = ?");
    $stmt->execute([$token_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Paket bilgilerini getir
function getPackageInfo($package_id) {
    return PACKAGES[$package_id] ?? null;
}

// Discord mesajı gönder
function sendDiscordMessage($channel_id, $message) {
    $url = "https://discord.com/api/v9/channels/{$channel_id}/messages";
    
    $headers = [
        'Authorization: Bot ' . DISCORD_TOKEN,
        'Content-Type: application/json'
    ];
    
    $data = json_encode(['content' => $message]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?> 