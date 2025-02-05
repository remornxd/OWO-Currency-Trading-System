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

// Genel istatistikleri al
$stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users,
        (SELECT COUNT(*) FROM users WHERE role != 'admin' AND is_active = 1) as active_users,
        (SELECT COUNT(*) FROM tokens) as total_tokens,
        (SELECT COUNT(*) FROM tokens WHERE is_running = 1) as active_tokens,
        (SELECT SUM(owo_balance) FROM tokens) as total_owo,
        (SELECT SUM(total_messages) FROM tokens) as total_messages,
        (SELECT COUNT(*) FROM `keys` WHERE is_used = 0) as available_keys
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Son 7 günün istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        date,
        total_users,
        active_users,
        total_tokens,
        active_tokens,
        total_owo,
        total_messages
    FROM statistics
    WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY date ASC
");
$stmt->execute();
$weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Yönetici Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Genel İstatistikler -->
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
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="title">Toplam Token</div>
                    <div class="value"><?= number_format($stats['total_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="title">Aktif Token</div>
                    <div class="value"><?= number_format($stats['active_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="title">Toplam owo</div>
                    <div class="value"><?= number_format($stats['total_owo']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="fas fa-message"></i>
                    </div>
                    <div class="title">Toplam Mesaj</div>
                    <div class="value"><?= number_format($stats['total_messages']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-dark bg-opacity-10 text-dark">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="title">Kullanılabilir Key</div>
                    <div class="value"><?= number_format($stats['available_keys']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Grafik -->
            <div class="col-md-12">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Son 7 Günün İstatistikleri</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm active" onclick="updateChart('users')">Kullanıcılar</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateChart('tokens')">Tokenler</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateChart('owo')">owo</button>
                        </div>
                    </div>
                    <canvas id="statsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Kullanıcı Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Kullanıcı Listesi</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="generateKeys()">
                    <i class="fas fa-key me-2"></i>Key Oluştur
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

    <!-- Key Oluşturma Modal -->
    <div class="modal fade" id="generateKeysModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Key Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="generateKeysForm" novalidate>
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
                            <label for="count" class="form-label">Adet</label>
                            <input type="number" class="form-control" id="count" name="count" min="1" max="100" value="1" required>
                            <div class="invalid-feedback">Lütfen 1-100 arası bir sayı girin.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="createKeys()">Oluştur</button>
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
        let statsChart;
        const weeklyStats = <?= json_encode($weeklyStats) ?>;
        let currentChartType = 'users';

        // Grafik güncelleme
        function updateChart(type) {
            currentChartType = type;
            const dates = weeklyStats.map(stat => {
                const date = new Date(stat.date);
                return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
            });

            let datasets = [];
            if (type === 'users') {
                datasets = [
                    {
                        label: 'Toplam Kullanıcı',
                        data: weeklyStats.map(stat => stat.total_users),
                        borderColor: 'rgb(13, 110, 253)',
                        tension: 0.1
                    },
                    {
                        label: 'Aktif Kullanıcı',
                        data: weeklyStats.map(stat => stat.active_users),
                        borderColor: 'rgb(25, 135, 84)',
                        tension: 0.1
                    }
                ];
            } else if (type === 'tokens') {
                datasets = [
                    {
                        label: 'Toplam Token',
                        data: weeklyStats.map(stat => stat.total_tokens),
                        borderColor: 'rgb(13, 202, 240)',
                        tension: 0.1
                    },
                    {
                        label: 'Aktif Token',
                        data: weeklyStats.map(stat => stat.active_tokens),
                        borderColor: 'rgb(255, 193, 7)',
                        tension: 0.1
                    }
                ];
            } else if (type === 'owo') {
                datasets = [
                    {
                        label: 'Toplam owo',
                        data: weeklyStats.map(stat => stat.total_owo),
                        borderColor: 'rgb(220, 53, 69)',
                        tension: 0.1
                    }
                ];
            }

            statsChart.data.labels = dates;
            statsChart.data.datasets = datasets;
            statsChart.update();

            // Buton stillerini güncelle
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.btn-group .btn[onclick="updateChart('${type}')"]`).classList.add('active');
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
                            formatNumber(user.total_tokens),
                            formatNumber(user.total_owo),
                            `<span class="badge ${user.is_active ? 'bg-success' : 'bg-danger'}">${user.is_active ? 'Aktif' : 'Pasif'}</span>`,
                            user.last_login ? formatDate(user.last_login) : '-',
                            `<div class="btn-group btn-group-sm">
                                <button type="button" class="btn ${user.is_active ? 'btn-danger' : 'btn-success'}" onclick="toggleUserStatus('${user.id}')" data-bs-toggle="tooltip" title="${user.is_active ? 'Pasif Yap' : 'Aktif Yap'}">
                                    <i class="fas fa-${user.is_active ? 'ban' : 'check'}"></i>
                                </button>
                                <button type="button" class="btn btn-warning" onclick="extendPackage('${user.id}')" data-bs-toggle="tooltip" title="Paketi Uzat">
                                    <i class="fas fa-clock"></i>
                                </button>
                                <button type="button" class="btn btn-danger" onclick="deleteUser('${user.id}')" data-bs-toggle="tooltip" title="Sil">
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

        // Key oluşturma modalını aç
        function generateKeys() {
            $('#generateKeysModal').modal('show');
        }

        // Key oluştur
        async function createKeys() {
            const form = document.getElementById('generateKeysForm');
            
            const validations = {
                packageType: {
                    pattern: /^[1-3]$/,
                    message: 'Lütfen bir paket tipi seçin.'
                },
                count: {
                    pattern: /^([1-9]|[1-9][0-9]|100)$/,
                    message: 'Lütfen 1-100 arası bir sayı girin.'
                }
            };

            if (!validateForm(form, validations)) {
                return;
            }

            try {
                const response = await apiRequest('/api/packages/keys', 'POST', formDataToJson(form));
                
                if (response.success) {
                    showToast(`${response.data.length} adet key başarıyla oluşturuldu.`, 'success');
                    $('#generateKeysModal').modal('hide');
                    form.reset();
                }
            } catch (error) {
                console.error('Key oluşturulurken hata:', error);
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
            // DataTable başlat
            userTable = $('#userTable').DataTable({
                order: [[6, 'desc']],
                pageLength: 10,
                columnDefs: [
                    { targets: [5, 7], orderable: false }
                ]
            });

            // Grafik başlat
            const ctx = document.getElementById('statsChart').getContext('2d');
            statsChart = new Chart(ctx, {
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
                            beginAtZero: true
                        }
                    }
                }
            });

            // İlk grafik verilerini yükle
            updateChart('users');

            // Kullanıcı listesini yükle
            loadUsers();

            // Her 30 saniyede bir kullanıcı listesini güncelle
            setInterval(loadUsers, 30000);
        });
    </script>
</body>
</html> 