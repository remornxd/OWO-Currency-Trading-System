<?php
require_once __DIR__ . '/../config/config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit();
}

// Kullanıcı bilgilerini al
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Token istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_tokens,
        SUM(CASE WHEN is_running = 1 THEN 1 ELSE 0 END) as active_tokens,
        SUM(owo_balance) as total_owo,
        SUM(total_messages) as total_messages
    FROM tokens 
    WHERE current_user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Profil Ayarları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-6">
                <!-- Profil Bilgileri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profil Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                <div class="invalid-feedback">Lütfen kullanıcı adı girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Kaydet
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Şifre Değiştirme -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Şifre Değiştirme</h5>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm" novalidate>
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                <div class="invalid-feedback">Lütfen mevcut şifrenizi girin.</div>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Yeni Şifre Tekrar</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                <div class="invalid-feedback">Şifreler eşleşmiyor.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Şifreyi Değiştir
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Paket Bilgileri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Paket Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="icon bg-primary bg-opacity-10 text-primary">
                                        <i class="fas fa-box"></i>
                                    </div>
                                    <div class="title">Paket</div>
                                    <div class="value"><?= getPackageTypeName($user['package_type']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="icon bg-warning bg-opacity-10 text-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="title">Kalan Süre</div>
                                    <div class="value" id="remainingTime">-</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="icon bg-success bg-opacity-10 text-success">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div class="title">Token Limiti</div>
                                    <div class="value"><?= number_format($stats['total_tokens']) ?> / <?= number_format($settings[$user['package_type'] . '_token_limit']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="icon bg-info bg-opacity-10 text-info">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <div class="title">Aktif Token</div>
                                    <div class="value"><?= number_format($stats['active_tokens']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Aktiviteler -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Son Aktiviteler</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="activityList">
                            <!-- JavaScript ile doldurulacak -->
                        </div>
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
        // Profil bilgilerini güncelle
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const validations = {
                username: {
                    pattern: /^[a-zA-Z0-9_]{3,20}$/,
                    message: 'Kullanıcı adı 3-20 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.'
                },
                email: {
                    pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                    message: 'Lütfen geçerli bir e-posta adresi girin.'
                }
            };

            if (!validateForm(this, validations)) {
                return;
            }

            try {
                const response = await apiRequest('/api/users/profile', 'PUT', formDataToJson(this));
                
                if (response.success) {
                    showToast('Profil bilgileri başarıyla güncellendi.', 'success');
                }
            } catch (error) {
                console.error('Profil güncellenirken hata:', error);
            }
        });

        // Şifre değiştir
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword.length < 6) {
                document.getElementById('newPassword').classList.add('is-invalid');
                return;
            }

            if (newPassword !== confirmPassword) {
                document.getElementById('confirmPassword').classList.add('is-invalid');
                return;
            }

            try {
                const response = await apiRequest('/api/users/password', 'PUT', formDataToJson(this));
                
                if (response.success) {
                    showToast('Şifre başarıyla değiştirildi.', 'success');
                    this.reset();
                }
            } catch (error) {
                console.error('Şifre değiştirilirken hata:', error);
            }
        });

        // Kalan süreyi güncelle
        function updateRemainingTime() {
            const expiresAt = new Date('<?= $user['package_expires_at'] ?>');
            const now = new Date();
            const diff = expiresAt - now;

            if (diff <= 0) {
                document.getElementById('remainingTime').textContent = 'Süresi Doldu';
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            document.getElementById('remainingTime').textContent = `${days}g ${hours}s ${minutes}d`;
        }

        // Son aktiviteleri yükle
        async function loadActivities() {
            try {
                const response = await apiRequest('/api/logs/user');
                
                if (response.success) {
                    const activityList = document.getElementById('activityList');
                    activityList.innerHTML = response.data.map(log => `
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${log.action}</h6>
                                <small class="text-muted">${formatDate(log.created_at)}</small>
                            </div>
                            <p class="mb-1">${log.details}</p>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>${log.ip_address}
                                <i class="fas fa-desktop ms-3 me-1"></i>${log.user_agent}
                            </small>
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Aktiviteler yüklenirken hata:', error);
            }
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Kalan süreyi güncelle
            updateRemainingTime();
            setInterval(updateRemainingTime, 60000);

            // Son aktiviteleri yükle
            loadActivities();
            setInterval(loadActivities, 30000);
        });
    </script>
</body>
</html> 