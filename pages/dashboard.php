<?php
require_once __DIR__ . '/../config/config.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit();
}

if ($_SESSION['role'] === 'admin') {
    header('Location: ' . SITE_URL . '/pages/admin/dashboard.php');
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
    <title><?= SITE_NAME ?> - Kontrol Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Token İstatistikleri -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="title">Toplam Token</div>
                    <div class="value"><?= number_format($stats['total_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="title">Aktif Token</div>
                    <div class="value"><?= number_format($stats['active_tokens']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="title">Toplam owo</div>
                    <div class="value"><?= number_format($stats['total_owo']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-message"></i>
                    </div>
                    <div class="title">Toplam Mesaj</div>
                    <div class="value"><?= number_format($stats['total_messages']) ?></div>
                </div>
            </div>
        </div>

        <!-- Token Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Token Listesi</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTokenModal">
                    <i class="fas fa-plus me-2"></i>Token Ekle
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tokenTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Kanal ID</th>
                                <th>Durum</th>
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

    <!-- Token Ekleme Modal -->
    <div class="modal fade" id="addTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Token Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTokenForm" novalidate>
                        <div class="mb-3">
                            <label for="token" class="form-label">Token</label>
                            <input type="text" class="form-control" id="token" name="token" required>
                            <div class="invalid-feedback">Lütfen geçerli bir token girin.</div>
                        </div>
                        <div class="mb-3">
                            <label for="channelId" class="form-label">Kanal ID</label>
                            <input type="text" class="form-control" id="channelId" name="channelId" required>
                            <div class="invalid-feedback">Lütfen geçerli bir kanal ID girin.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="addToken()">Ekle</button>
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
                            `<span class="badge ${token.is_running ? 'bg-success' : 'bg-danger'}">${token.is_running ? 'Çalışıyor' : 'Durdu'}</span>`,
                            formatNumber(token.owo_balance),
                            formatNumber(token.total_messages),
                            token.last_used ? formatDate(token.last_used) : '-',
                            `<div class="btn-group btn-group-sm">
                                ${token.is_running ? 
                                    `<button type="button" class="btn btn-danger" onclick="stopToken('${token.id}')" data-bs-toggle="tooltip" title="Durdur">
                                        <i class="fas fa-stop"></i>
                                    </button>` :
                                    `<button type="button" class="btn btn-success" onclick="startToken('${token.id}')" data-bs-toggle="tooltip" title="Başlat">
                                        <i class="fas fa-play"></i>
                                    </button>`
                                }
                                <button type="button" class="btn btn-danger" onclick="deleteToken('${token.id}')" data-bs-toggle="tooltip" title="Sil">
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

        // Token ekle
        async function addToken() {
            const form = document.getElementById('addTokenForm');
            
            const validations = {
                token: {
                    pattern: /^[A-Za-z0-9._-]{50,100}$/,
                    message: 'Lütfen geçerli bir token girin.'
                },
                channelId: {
                    pattern: /^\d{17,20}$/,
                    message: 'Lütfen geçerli bir kanal ID girin.'
                }
            };

            if (!validateForm(form, validations)) {
                return;
            }

            try {
                const response = await apiRequest('/api/tokens', 'POST', formDataToJson(form));
                
                if (response.success) {
                    showToast('Token başarıyla eklendi.', 'success');
                    $('#addTokenModal').modal('hide');
                    form.reset();
                    loadTokens();
                }
            } catch (error) {
                console.error('Token eklenirken hata:', error);
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

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // DataTable başlat
            tokenTable = $('#tokenTable').DataTable({
                order: [[5, 'desc']],
                pageLength: 10,
                columnDefs: [
                    { targets: [2, 6], orderable: false }
                ]
            });

            // Token listesini yükle
            loadTokens();

            // Her 30 saniyede bir token listesini güncelle
            setInterval(loadTokens, 30000);
        });
    </script>
</body>
</html> 