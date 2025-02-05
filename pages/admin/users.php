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

// Kullanıcı istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
        COUNT(DISTINCT package_type) as package_types
    FROM users
    WHERE role != 'admin'
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Paket tipine göre kullanıcı sayılarını al
$stmt = $db->prepare("
    SELECT 
        package_type,
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM users
    WHERE role != 'admin'
    GROUP BY package_type
");
$stmt->execute();
$packageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 7 günün yeni kullanıcı sayılarını al
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM users
    WHERE role != 'admin'
    AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Kullanıcı Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Kullanıcı İstatistikleri -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="title">Toplam Kullanıcı</div>
                    <div class="value"><?= number_format($stats['total_users']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="title">Aktif Kullanıcı</div>
                    <div class="value"><?= number_format($stats['active_users']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="title">Pasif Kullanıcı</div>
                    <div class="value"><?= number_format($stats['inactive_users']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="title">Paket Tipi</div>
                    <div class="value"><?= number_format($stats['package_types']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Günlük Kullanıcı Grafiği -->
            <div class="col-md-8">
                <div class="chart-card">
                    <h5 class="card-title mb-4">Son 7 Günün Yeni Kullanıcı Sayıları</h5>
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <!-- Paket İstatistikleri -->
            <div class="col-md-4">
                <?php foreach ($packageStats as $stat): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?= getPackageTypeName($stat['package_type']) ?></h5>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <div class="text-muted mb-1">Toplam</div>
                                <h4 class="mb-0"><?= number_format($stat['total']) ?></h4>
                            </div>
                            <div>
                                <div class="text-success mb-1">Aktif</div>
                                <h4 class="mb-0"><?= number_format($stat['active']) ?></h4>
                            </div>
                            <div>
                                <div class="text-danger mb-1">Pasif</div>
                                <h4 class="mb-0"><?= number_format($stat['inactive']) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kullanıcı Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Kullanıcı Listesi</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="addUser()">
                    <i class="fas fa-plus me-2"></i>Kullanıcı Ekle
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="userTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kullanıcı Adı</th>
                                <th>E-posta</th>
                                <th>Paket</th>
                                <th>Paket Bitiş</th>
                                <th>Token Sayısı</th>
                                <th>Toplam owo</th>
                                <th>Durum</th>
                                <th>Son Giriş</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm" novalidate>
                        <input type="hidden" id="userId" name="userId">
                        <div class="mb-3">
                            <label for="username" class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">Lütfen geçerli bir kullanıcı adı girin.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                            <small class="form-text text-muted">Düzenleme sırasında boş bırakırsanız şifre değişmez.</small>
                        </div>
                        <div class="mb-3">
                            <label for="packageType" class="form-label">Paket Tipi</label>
                            <select class="form-select" id="packageType" name="packageType" required>
                                <option value="">Seçiniz</option>
                                <option value="1">Temel Paket</option>
                                <option value="2">Premium Paket</option>
                                <option value="3">Kurumsal Paket</option>
                            </select>
                            <div class="invalid-feedback">Lütfen bir paket tipi seçin.</div>
                        </div>
                        <div class="mb-3">
                            <label for="packageExpires" class="form-label">Paket Bitiş Tarihi</label>
                            <input type="datetime-local" class="form-control" id="packageExpires" name="packageExpires" required>
                            <div class="invalid-feedback">Lütfen geçerli bir tarih seçin.</div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isActive" name="isActive" checked>
                                <label class="form-check-label" for="isActive">Aktif</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="saveUser()">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        let userTable;
        let dailyChart;
        const dailyStats = <?= json_encode($dailyStats) ?>;
        let userModal;

        // Günlük kullanıcı grafiğini güncelle
        function updateDailyChart() {
            const dates = dailyStats.map(stat => {
                const date = new Date(stat.date);
                return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
            });
            const counts = dailyStats.map(stat => stat.count);

            dailyChart.data.labels = dates;
            dailyChart.data.datasets = [{
                label: 'Yeni Kullanıcı',
                data: counts,
                borderColor: 'rgb(13, 110, 253)',
                tension: 0.1
            }];
            dailyChart.update();
        }

        // Kullanıcı listesini yükle
        async function loadUsers() {
            try {
                const response = await apiRequest('/api/users');
                
                if (response.success) {
                    if (userTable) {
                        userTable.clear();
                        userTable.rows.add(response.data.map(user => [
                            user.username,
                            user.email,
                            getPackageTypeName(user.package_type),
                            user.package_expires_at ? formatDate(user.package_expires_at) : '-',
                            formatNumber(user.total_tokens),
                            formatNumber(user.total_owo),
                            `<span class="badge ${user.is_active ? 'bg-success' : 'bg-danger'}">${user.is_active ? 'Aktif' : 'Pasif'}</span>`,
                            user.last_login ? formatDate(user.last_login) : '-',
                            `<div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-primary" onclick="editUser(${user.id})" data-bs-toggle="tooltip" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn ${user.is_active ? 'btn-danger' : 'btn-success'}" onclick="toggleUserStatus(${user.id})" data-bs-toggle="tooltip" title="${user.is_active ? 'Pasif Yap' : 'Aktif Yap'}">
                                    <i class="fas fa-${user.is_active ? 'ban' : 'check'}"></i>
                                </button>
                                <button type="button" class="btn btn-warning" onclick="extendPackage(${user.id})" data-bs-toggle="tooltip" title="Paketi Uzat">
                                    <i class="fas fa-clock"></i>
                                </button>
                                <button type="button" class="btn btn-danger" onclick="deleteUser(${user.id})" data-bs-toggle="tooltip" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`
                        ]));
                        userTable.draw();
                    }
                }
            } catch (error) {
                console.error('Kullanıcı listesi yüklenirken hata:', error);
            }
        }

        // Kullanıcı ekleme modalını aç
        function addUser() {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.querySelector('#userModal .modal-title').textContent = 'Kullanıcı Ekle';
            userModal.show();
        }

        // Kullanıcı düzenleme modalını aç
        async function editUser(id) {
            try {
                const response = await apiRequest(`/api/users/${id}`);
                
                if (response.success) {
                    const user = response.data;
                    document.getElementById('userId').value = user.id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('email').value = user.email;
                    document.getElementById('packageType').value = user.package_type;
                    document.getElementById('packageExpires').value = user.package_expires_at.slice(0, 16);
                    document.getElementById('isActive').checked = user.is_active;
                    document.getElementById('password').value = '';
                    
                    document.querySelector('#userModal .modal-title').textContent = 'Kullanıcı Düzenle';
                    userModal.show();
                }
            } catch (error) {
                console.error('Kullanıcı bilgileri yüklenirken hata:', error);
            }
        }

        // Kullanıcı kaydet
        async function saveUser() {
            const form = document.getElementById('userForm');
            
            const validations = {
                username: {
                    pattern: /^[a-zA-Z0-9_]{3,20}$/,
                    message: 'Kullanıcı adı 3-20 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.'
                },
                email: {
                    pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                    message: 'Lütfen geçerli bir e-posta adresi girin.'
                },
                packageType: {
                    pattern: /^[1-3]$/,
                    message: 'Lütfen bir paket tipi seçin.'
                },
                packageExpires: {
                    pattern: /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/,
                    message: 'Lütfen geçerli bir tarih seçin.'
                }
            };

            const userId = document.getElementById('userId').value;
            if (!userId && !document.getElementById('password').value) {
                document.getElementById('password').classList.add('is-invalid');
                return;
            }

            if (document.getElementById('password').value && !/^.{6,}$/.test(document.getElementById('password').value)) {
                document.getElementById('password').classList.add('is-invalid');
                return;
            }

            if (!validateForm(form, validations)) {
                return;
            }

            try {
                const formData = formDataToJson(form);
                if (!formData.password) {
                    delete formData.password;
                }

                const response = await apiRequest(
                    `/api/users${userId ? `/${userId}` : ''}`,
                    userId ? 'PUT' : 'POST',
                    formData
                );
                
                if (response.success) {
                    showToast(`Kullanıcı başarıyla ${userId ? 'güncellendi' : 'eklendi'}.`, 'success');
                    userModal.hide();
                    loadUsers();
                }
            } catch (error) {
                console.error('Kullanıcı kaydedilirken hata:', error);
            }
        }

        // Kullanıcı durumunu değiştir
        async function toggleUserStatus(id) {
            try {
                const response = await apiRequest(`/api/users/${id}/toggle-status`, 'POST');
                
                if (response.success) {
                    showToast('Kullanıcı durumu başarıyla güncellendi.', 'success');
                    loadUsers();
                }
            } catch (error) {
                console.error('Kullanıcı durumu güncellenirken hata:', error);
            }
        }

        // Kullanıcı paketini uzat
        async function extendPackage(id) {
            try {
                const response = await apiRequest(`/api/users/${id}/extend-package`, 'POST');
                
                if (response.success) {
                    showToast('Kullanıcı paketi başarıyla uzatıldı.', 'success');
                    loadUsers();
                }
            } catch (error) {
                console.error('Kullanıcı paketi uzatılırken hata:', error);
            }
        }

        // Kullanıcı sil
        async function deleteUser(id) {
            if (!confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest(`/api/users/${id}`, 'DELETE');
                
                if (response.success) {
                    showToast('Kullanıcı başarıyla silindi.', 'success');
                    loadUsers();
                }
            } catch (error) {
                console.error('Kullanıcı silinirken hata:', error);
            }
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Modal başlat
            userModal = new bootstrap.Modal(document.getElementById('userModal'));

            // DataTable başlat
            userTable = $('#userTable').DataTable({
                order: [[7, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { targets: [6, 8], orderable: false }
                ]
            });

            // Grafik başlat
            const ctx = document.getElementById('dailyChart').getContext('2d');
            dailyChart = new Chart(ctx, {
                type: 'line',
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Grafik verilerini yükle
            updateDailyChart();

            // Kullanıcı listesini yükle
            loadUsers();

            // Her 30 saniyede bir kullanıcı listesini güncelle
            setInterval(loadUsers, 30000);
        });
    </script>
</body>
</html> 