<?php
require_once __DIR__ . '/../../config/config.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit();
}

// Mevcut ayarları al
$stmt = $db->prepare("SELECT * FROM settings");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Sistem Ayarları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-6">
                <!-- Genel Ayarlar -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Genel Ayarlar</h5>
                    </div>
                    <div class="card-body">
                        <form id="generalSettingsForm" novalidate>
                            <div class="mb-3">
                                <label for="siteName" class="form-label">Site Adı</label>
                                <input type="text" class="form-control" id="siteName" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
                                <div class="invalid-feedback">Lütfen site adını girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="siteUrl" class="form-label">Site URL</label>
                                <input type="url" class="form-control" id="siteUrl" name="site_url" value="<?= htmlspecialchars($settings['site_url'] ?? '') ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir URL girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="adminEmail" class="form-label">Yönetici E-posta</label>
                                <input type="email" class="form-control" id="adminEmail" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Token Ayarları -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Token Ayarları</h5>
                    </div>
                    <div class="card-body">
                        <form id="tokenSettingsForm" novalidate>
                            <div class="mb-3">
                                <label for="messageInterval" class="form-label">Mesaj Gönderme Aralığı (saniye)</label>
                                <input type="number" class="form-control" id="messageInterval" name="message_interval" value="<?= htmlspecialchars($settings['message_interval'] ?? '60') ?>" min="30" required>
                                <div class="invalid-feedback">Lütfen en az 30 saniye girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="captchaTimeout" class="form-label">Captcha Bekleme Süresi (dakika)</label>
                                <input type="number" class="form-control" id="captchaTimeout" name="captcha_timeout" value="<?= htmlspecialchars($settings['captcha_timeout'] ?? '30') ?>" min="5" required>
                                <div class="invalid-feedback">Lütfen en az 5 dakika girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="maxTokensPerUser" class="form-label">Kullanıcı Başına Maksimum Token</label>
                                <input type="number" class="form-control" id="maxTokensPerUser" name="max_tokens_per_user" value="<?= htmlspecialchars($settings['max_tokens_per_user'] ?? '10') ?>" min="1" required>
                                <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Paket Ayarları -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Paket Ayarları</h5>
                    </div>
                    <div class="card-body">
                        <form id="packageSettingsForm" novalidate>
                            <!-- Temel Paket -->
                            <div class="mb-4">
                                <h6 class="mb-3">Temel Paket</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="basicTokenLimit" class="form-label">Token Limiti</label>
                                        <input type="number" class="form-control" id="basicTokenLimit" name="basic_token_limit" value="<?= htmlspecialchars($settings['basic_token_limit'] ?? '3') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="basicDuration" class="form-label">Süre (gün)</label>
                                        <input type="number" class="form-control" id="basicDuration" name="basic_duration" value="<?= htmlspecialchars($settings['basic_duration'] ?? '30') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Premium Paket -->
                            <div class="mb-4">
                                <h6 class="mb-3">Premium Paket</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="premiumTokenLimit" class="form-label">Token Limiti</label>
                                        <input type="number" class="form-control" id="premiumTokenLimit" name="premium_token_limit" value="<?= htmlspecialchars($settings['premium_token_limit'] ?? '5') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="premiumDuration" class="form-label">Süre (gün)</label>
                                        <input type="number" class="form-control" id="premiumDuration" name="premium_duration" value="<?= htmlspecialchars($settings['premium_duration'] ?? '30') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kurumsal Paket -->
                            <div class="mb-4">
                                <h6 class="mb-3">Kurumsal Paket</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="enterpriseTokenLimit" class="form-label">Token Limiti</label>
                                        <input type="number" class="form-control" id="enterpriseTokenLimit" name="enterprise_token_limit" value="<?= htmlspecialchars($settings['enterprise_token_limit'] ?? '10') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="enterpriseDuration" class="form-label">Süre (gün)</label>
                                        <input type="number" class="form-control" id="enterpriseDuration" name="enterprise_duration" value="<?= htmlspecialchars($settings['enterprise_duration'] ?? '30') ?>" min="1" required>
                                        <div class="invalid-feedback">Lütfen en az 1 girin.</div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Bakım Modu -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bakım Modu</h5>
                    </div>
                    <div class="card-body">
                        <form id="maintenanceSettingsForm" novalidate>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="maintenanceMode" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="maintenanceMode">Bakım Modu</label>
                                </div>
                                <small class="text-muted">Bakım modu aktif olduğunda sadece yöneticiler giriş yapabilir.</small>
                            </div>
                            <div class="mb-3">
                                <label for="maintenanceMessage" class="form-label">Bakım Mesajı</label>
                                <textarea class="form-control" id="maintenanceMessage" name="maintenance_message" rows="3"><?= htmlspecialchars($settings['maintenance_message'] ?? 'Sistemimiz bakımdadır. Lütfen daha sonra tekrar deneyiniz.') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        // Form validasyonu ve gönderimi
        function handleFormSubmit(formId, endpoint) {
            const form = document.getElementById(formId);
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Form validasyonu
                if (!form.checkValidity()) {
                    e.stopPropagation();
                    form.classList.add('was-validated');
                    return;
                }

                try {
                    const response = await apiRequest(endpoint, 'POST', formDataToJson(form));
                    
                    if (response.success) {
                        showToast('Ayarlar başarıyla kaydedildi.', 'success');
                    }
                } catch (error) {
                    console.error('Ayarlar kaydedilirken hata:', error);
                }
            });
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Form işleyicilerini başlat
            handleFormSubmit('generalSettingsForm', '/api/settings/general');
            handleFormSubmit('tokenSettingsForm', '/api/settings/token');
            handleFormSubmit('packageSettingsForm', '/api/settings/package');
            handleFormSubmit('maintenanceSettingsForm', '/api/settings/maintenance');
        });
    </script>
</body>
</html> 