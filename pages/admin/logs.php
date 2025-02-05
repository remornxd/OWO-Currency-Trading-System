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

// Log istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT action) as unique_actions,
        COUNT(DISTINCT DATE(created_at)) as unique_days
    FROM logs
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Son 7 günün log sayılarını al
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM logs
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En çok yapılan işlemleri al
$stmt = $db->prepare("
    SELECT 
        action,
        COUNT(*) as count
    FROM logs
    GROUP BY action
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute();
$topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En aktif kullanıcıları al
$stmt = $db->prepare("
    SELECT 
        l.user_id,
        u.username,
        COUNT(*) as count
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    GROUP BY l.user_id
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute();
$topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Log Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Log İstatistikleri -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="title">Toplam Log</div>
                    <div class="value"><?= number_format($stats['total_logs']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="title">Benzersiz Kullanıcı</div>
                    <div class="value"><?= number_format($stats['unique_users']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="title">Benzersiz İşlem</div>
                    <div class="value"><?= number_format($stats['unique_actions']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="title">Benzersiz Gün</div>
                    <div class="value"><?= number_format($stats['unique_days']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Günlük Log Grafiği -->
            <div class="col-md-8">
                <div class="chart-card">
                    <h5 class="card-title mb-4">Son 7 Günün Log Sayıları</h5>
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <!-- En Çok Yapılan İşlemler ve En Aktif Kullanıcılar -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">En Çok Yapılan İşlemler</h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topActions as $action): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($action['action']) ?></span>
                                <span class="badge bg-primary rounded-pill"><?= number_format($action['count']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">En Aktif Kullanıcılar</h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topUsers as $user): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($user['username'] ?? 'Silinmiş Kullanıcı') ?></span>
                                <span class="badge bg-primary rounded-pill"><?= number_format($user['count']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Log Listesi</h5>
                <div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="clearLogs()">
                        <i class="fas fa-trash me-2"></i>Tüm Logları Temizle
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="exportLogs()">
                        <i class="fas fa-download me-2"></i>Dışa Aktar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="logTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>İşlem</th>
                                <th>Detay</th>
                                <th>IP Adresi</th>
                                <th>Tarayıcı</th>
                                <th>Tarih</th>
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

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        let logTable;
        let dailyChart;
        const dailyStats = <?= json_encode($dailyStats) ?>;

        // Günlük log grafiğini güncelle
        function updateDailyChart() {
            const dates = dailyStats.map(stat => {
                const date = new Date(stat.date);
                return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
            });
            const counts = dailyStats.map(stat => stat.count);

            dailyChart.data.labels = dates;
            dailyChart.data.datasets = [{
                label: 'Log Sayısı',
                data: counts,
                borderColor: 'rgb(13, 110, 253)',
                tension: 0.1
            }];
            dailyChart.update();
        }

        // Log listesini yükle
        async function loadLogs() {
            try {
                const response = await apiRequest('/api/logs');
                
                if (response.success) {
                    if (logTable) {
                        logTable.clear();
                        logTable.rows.add(response.data.map(log => [
                            log.username || 'Silinmiş Kullanıcı',
                            log.action,
                            log.details,
                            log.ip_address,
                            log.user_agent,
                            formatDate(log.created_at)
                        ]));
                        logTable.draw();
                    }
                }
            } catch (error) {
                console.error('Log listesi yüklenirken hata:', error);
            }
        }

        // Tüm logları temizle
        async function clearLogs() {
            if (!confirm('Tüm logları silmek istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest('/api/logs/clear', 'POST');
                
                if (response.success) {
                    showToast('Tüm loglar başarıyla temizlendi.', 'success');
                    loadLogs();
                }
            } catch (error) {
                console.error('Loglar temizlenirken hata:', error);
            }
        }

        // Logları dışa aktar
        async function exportLogs() {
            try {
                const response = await apiRequest('/api/logs/export');
                
                if (response.success) {
                    const blob = new Blob([response.data], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'logs.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            } catch (error) {
                console.error('Loglar dışa aktarılırken hata:', error);
            }
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // DataTable başlat
            logTable = $('#logTable').DataTable({
                order: [[5, 'desc']],
                pageLength: 25
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

            // Log listesini yükle
            loadLogs();

            // Her 30 saniyede bir log listesini güncelle
            setInterval(loadLogs, 30000);
        });
    </script>
</body>
</html> 