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

// Token istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_tokens,
        SUM(CASE WHEN is_running = 1 THEN 1 ELSE 0 END) as active_tokens,
        SUM(CASE WHEN is_running = 0 THEN 1 ELSE 0 END) as inactive_tokens,
        SUM(CASE WHEN captcha_detected = 1 THEN 1 ELSE 0 END) as captcha_tokens,
        SUM(owo_balance) as total_owo,
        SUM(total_messages) as total_messages
    FROM tokens
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Son 7 günün token istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_tokens,
        SUM(CASE WHEN is_running = 1 THEN 1 ELSE 0 END) as active_tokens,
        SUM(owo_balance) as total_owo,
        SUM(total_messages) as total_messages
    FROM tokens
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En çok owo kazanan tokenler
$stmt = $db->prepare("
    SELECT 
        t.*,
        u.username as current_user_username
    FROM tokens t
    LEFT JOIN users u ON t.current_user_id = u.id
    ORDER BY t.owo_balance DESC
    LIMIT 5
");
$stmt->execute();
$topTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Token Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Token İstatistikleri -->
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="title">Toplam Token</div>
                    <div class="value"><?= number_format($stats['total_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="title">Aktif Token</div>
                    <div class="value"><?= number_format($stats['active_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-stop"></i>
                    </div>
                    <div class="title">Pasif Token</div>
                    <div class="value"><?= number_format($stats['inactive_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="title">Captcha</div>
                    <div class="value"><?= number_format($stats['captcha_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="title">Toplam owo</div>
                    <div class="value"><?= number_format($stats['total_owo']) ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="fas fa-message"></i>
                    </div>
                    <div class="title">Toplam Mesaj</div>
                    <div class="value"><?= number_format($stats['total_messages']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Token Grafiği -->
            <div class="col-md-8">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Son 7 Günün İstatistikleri</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm active" onclick="updateChart('tokens')">Tokenler</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateChart('owo')">owo</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="updateChart('messages')">Mesajlar</button>
                        </div>
                    </div>
                    <canvas id="statsChart"></canvas>
                </div>
            </div>
            <!-- En Çok owo Kazanan Tokenler -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">En Çok owo Kazanan Tokenler</h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($topTokens as $token): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-truncate">
                                        <small class="d-block text-muted">Token</small>
                                        <span class="text-truncate"><?= substr($token['token'], 0, 20) ?>...</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="d-block text-muted">owo</small>
                                        <span class="badge bg-success"><?= number_format($token['owo_balance']) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div>
                                        <small class="text-muted">Kullanıcı</small>
                                        <span class="ms-1"><?= $token['current_user_username'] ?? 'Yok' ?></span>
                                    </div>
                                    <div>
                                        <small class="text-muted">Mesaj</small>
                                        <span class="badge bg-info ms-1"><?= number_format($token['total_messages']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Token Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Token Listesi</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="startAllTokens()">
                        <i class="fas fa-play me-2"></i>Tümünü Başlat
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="stopAllTokens()">
                        <i class="fas fa-stop me-2"></i>Tümünü Durdur
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tokenTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Kanal ID</th>
                                <th>Kullanıcı</th>
                                <th>Durum</th>
                                <th>Captcha</th>
                                <th>owo Bakiye</th>
                                <th>Toplam Mesaj</th>
                                <th>Son Kullanım</th>
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

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        let tokenTable;
        let statsChart;
        const dailyStats = <?= json_encode($dailyStats) ?>;
        let currentChartType = 'tokens';

        // Grafik güncelleme
        function updateChart(type) {
            currentChartType = type;
            const dates = dailyStats.map(stat => {
                const date = new Date(stat.date);
                return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
            });

            let datasets = [];
            if (type === 'tokens') {
                datasets = [
                    {
                        label: 'Toplam Token',
                        data: dailyStats.map(stat => stat.total_tokens),
                        borderColor: 'rgb(13, 110, 253)',
                        tension: 0.1
                    },
                    {
                        label: 'Aktif Token',
                        data: dailyStats.map(stat => stat.active_tokens),
                        borderColor: 'rgb(25, 135, 84)',
                        tension: 0.1
                    }
                ];
            } else if (type === 'owo') {
                datasets = [{
                    label: 'Toplam owo',
                    data: dailyStats.map(stat => stat.total_owo),
                    borderColor: 'rgb(13, 202, 240)',
                    tension: 0.1
                }];
            } else if (type === 'messages') {
                datasets = [{
                    label: 'Toplam Mesaj',
                    data: dailyStats.map(stat => stat.total_messages),
                    borderColor: 'rgb(102, 16, 242)',
                    tension: 0.1
                }];
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

        // Token listesini yükle
        async function loadTokens() {
            try {
                const response = await apiRequest('/api/tokens');
                
                if (response.success) {
                    if (tokenTable) {
                        tokenTable.clear();
                        tokenTable.rows.add(response.data.map(token => [
                            token.token,
                            token.channel_id,
                            token.current_user_username || '-',
                            `<span class="badge ${token.is_running ? 'bg-success' : 'bg-danger'}">${token.is_running ? 'Çalışıyor' : 'Durdu'}</span>`,
                            `<span class="badge ${token.captcha_detected ? 'bg-warning' : 'bg-success'}">${token.captcha_detected ? 'Var' : 'Yok'}</span>`,
                            formatNumber(token.owo_balance),
                            formatNumber(token.total_messages),
                            token.last_used ? formatDate(token.last_used) : '-',
                            `<div class="btn-group btn-group-sm">
                                ${token.is_running ? 
                                    `<button type="button" class="btn btn-danger" onclick="stopToken(${token.id})" data-bs-toggle="tooltip" title="Durdur">
                                        <i class="fas fa-stop"></i>
                                    </button>` :
                                    `<button type="button" class="btn btn-success" onclick="startToken(${token.id})" data-bs-toggle="tooltip" title="Başlat">
                                        <i class="fas fa-play"></i>
                                    </button>`
                                }
                                <button type="button" class="btn btn-warning" onclick="resetCaptcha(${token.id})" data-bs-toggle="tooltip" title="Captcha Sıfırla">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <button type="button" class="btn btn-danger" onclick="deleteToken(${token.id})" data-bs-toggle="tooltip" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`
                        ]));
                        tokenTable.draw();
                    }
                }
            } catch (error) {
                console.error('Token listesi yüklenirken hata:', error);
            }
        }

        // Token başlat
        async function startToken(id) {
            try {
                const response = await apiRequest(`/api/tokens/${id}/start`, 'POST');
                
                if (response.success) {
                    showToast('Token başarıyla başlatıldı.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Token başlatılırken hata:', error);
            }
        }

        // Token durdur
        async function stopToken(id) {
            try {
                const response = await apiRequest(`/api/tokens/${id}/stop`, 'POST');
                
                if (response.success) {
                    showToast('Token başarıyla durduruldu.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Token durdurulurken hata:', error);
            }
        }

        // Captcha sıfırla
        async function resetCaptcha(id) {
            try {
                const response = await apiRequest(`/api/tokens/${id}/reset-captcha`, 'POST');
                
                if (response.success) {
                    showToast('Token captcha durumu sıfırlandı.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Token captcha durumu sıfırlanırken hata:', error);
            }
        }

        // Token sil
        async function deleteToken(id) {
            if (!confirm('Bu tokeni silmek istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest(`/api/tokens/${id}`, 'DELETE');
                
                if (response.success) {
                    showToast('Token başarıyla silindi.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Token silinirken hata:', error);
            }
        }

        // Tüm tokenleri başlat
        async function startAllTokens() {
            if (!confirm('Tüm tokenleri başlatmak istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest('/api/tokens/start-all', 'POST');
                
                if (response.success) {
                    showToast('Tüm tokenler başarıyla başlatıldı.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Tokenler başlatılırken hata:', error);
            }
        }

        // Tüm tokenleri durdur
        async function stopAllTokens() {
            if (!confirm('Tüm tokenleri durdurmak istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest('/api/tokens/stop-all', 'POST');
                
                if (response.success) {
                    showToast('Tüm tokenler başarıyla durduruldu.', 'success');
                    loadTokens();
                }
            } catch (error) {
                console.error('Tokenler durdurulurken hata:', error);
            }
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // DataTable başlat
            tokenTable = $('#tokenTable').DataTable({
                order: [[7, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { targets: [3, 4, 8], orderable: false }
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

            // Grafik verilerini yükle
            updateChart('tokens');

            // Token listesini yükle
            loadTokens();

            // Her 30 saniyede bir token listesini güncelle
            setInterval(loadTokens, 30000);
        });
    </script>
</body>
</html> 