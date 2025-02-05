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

// Key istatistiklerini al
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_keys,
        SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as available_keys,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used_keys,
        COUNT(DISTINCT package_type) as package_types
    FROM `keys`
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Paket tipine göre key sayılarını al
$stmt = $db->prepare("
    SELECT 
        package_type,
        COUNT(*) as total,
        SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used
    FROM `keys`
    GROUP BY package_type
");
$stmt->execute();
$packageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Key Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row g-4 mb-4">
            <!-- Key İstatistikleri -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="title">Toplam Key</div>
                    <div class="value"><?= number_format($stats['total_keys']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="title">Kullanılabilir Key</div>
                    <div class="value"><?= number_format($stats['available_keys']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="title">Kullanılmış Key</div>
                    <div class="value"><?= number_format($stats['used_keys']) ?></div>
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

        <!-- Paket İstatistikleri -->
        <div class="row g-4 mb-4">
            <?php foreach ($packageStats as $stat): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= getPackageTypeName($stat['package_type']) ?></h5>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <div class="text-muted mb-1">Toplam</div>
                                <h4 class="mb-0"><?= number_format($stat['total']) ?></h4>
                            </div>
                            <div>
                                <div class="text-success mb-1">Kullanılabilir</div>
                                <h4 class="mb-0"><?= number_format($stat['available']) ?></h4>
                            </div>
                            <div>
                                <div class="text-danger mb-1">Kullanılmış</div>
                                <h4 class="mb-0"><?= number_format($stat['used']) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Key Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Key Listesi</h5>
                <button type="button" class="btn btn-primary btn-sm" onclick="generateKeys()">
                    <i class="fas fa-plus me-2"></i>Key Oluştur
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="keyTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Paket</th>
                                <th>Durum</th>
                                <th>Kullanan</th>
                                <th>Kullanım Tarihi</th>
                                <th>Oluşturan</th>
                                <th>Oluşturma Tarihi</th>
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
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        let keyTable;

        // Key listesini yükle
        async function loadKeys() {
            try {
                const response = await apiRequest('/api/packages/keys');
                
                if (response.success) {
                    if (keyTable) {
                        keyTable.clear();
                        keyTable.rows.add(response.data.map(key => [
                            key.key,
                            getPackageTypeName(key.package_type),
                            `<span class="badge ${key.is_used ? 'bg-danger' : 'bg-success'}">${key.is_used ? 'Kullanıldı' : 'Kullanılabilir'}</span>`,
                            key.used_by ? key.used_by_username : '-',
                            key.used_at ? formatDate(key.used_at) : '-',
                            key.created_by_username,
                            formatDate(key.created_at),
                            `<div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-danger" onclick="deleteKey('${key.id}')" data-bs-toggle="tooltip" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`
                        ]));
                        keyTable.draw();
                    }
                }
            } catch (error) {
                console.error('Key listesi yüklenirken hata:', error);
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
                    loadKeys();
                }
            } catch (error) {
                console.error('Key oluşturulurken hata:', error);
            }
        }

        // Key sil
        async function deleteKey(id) {
            if (!confirm('Bu keyi silmek istediğinize emin misiniz?')) {
                return;
            }

            try {
                const response = await apiRequest(`/api/packages/keys/${id}`, 'DELETE');
                
                if (response.success) {
                    showToast('Key başarıyla silindi.', 'success');
                    loadKeys();
                }
            } catch (error) {
                console.error('Key silinirken hata:', error);
            }
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // DataTable başlat
            keyTable = $('#keyTable').DataTable({
                order: [[6, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { targets: [2, 7], orderable: false }
                ]
            });

            // Key listesini yükle
            loadKeys();

            // Her 30 saniyede bir key listesini güncelle
            setInterval(loadKeys, 30000);
        });
    </script>
</body>
</html> 