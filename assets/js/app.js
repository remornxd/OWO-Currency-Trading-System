// Toast bildirimi gösterme fonksiyonu
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-warning';
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle';
    
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <i class="fas fa-${icon} me-2"></i>
                <strong class="me-auto">Bildirim</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Kapat"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// API istekleri için yardımcı fonksiyon
async function apiRequest(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(endpoint, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Bir hata oluştu');
        }

        return result;
    } catch (error) {
        showToast(error.message, 'error');
        throw error;
    }
}

// Form verilerini JSON formatına dönüştürme
function formDataToJson(form) {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

// DataTables için Türkçe dil desteği
const dataTablesTurkish = {
    "emptyTable": "Tabloda veri bulunmuyor",
    "info": "_TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor",
    "infoEmpty": "Kayıt yok",
    "infoFiltered": "(_MAX_ kayıt içerisinden bulunan)",
    "infoPostFix": "",
    "thousands": ".",
    "lengthMenu": "_MENU_ kayıt göster",
    "loadingRecords": "Yükleniyor...",
    "processing": "İşleniyor...",
    "search": "Ara:",
    "zeroRecords": "Eşleşen kayıt bulunamadı",
    "paginate": {
        "first": "İlk",
        "last": "Son",
        "next": "Sonraki",
        "previous": "Önceki"
    },
    "aria": {
        "sortAscending": ": artan sütun sıralamasını aktifleştir",
        "sortDescending": ": azalan sütun sıralamasını aktifleştir"
    },
    "select": {
        "rows": {
            "_": "%d kayıt seçildi",
            "1": "1 kayıt seçildi"
        }
    }
};

// Çıkış yapma fonksiyonu
async function logout() {
    try {
        await apiRequest('/api/auth/logout', 'POST');
        window.location.href = '/pages/login.php';
    } catch (error) {
        console.error('Çıkış yapılırken hata oluştu:', error);
    }
}

// Tarih formatı için yardımcı fonksiyon
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('tr-TR', options);
}

// Sayı formatı için yardımcı fonksiyon
function formatNumber(number) {
    return new Intl.NumberFormat('tr-TR').format(number);
}

// Paket tipini Türkçe metne çevirme
function getPackageTypeName(type) {
    const types = {
        'basic': 'Temel',
        'premium': 'Premium',
        'enterprise': 'Kurumsal'
    };
    return types[type] || type;
}

// Token durumunu Türkçe metne çevirme
function getTokenStatusName(status) {
    const statuses = {
        'active': 'Aktif',
        'inactive': 'Pasif',
        'expired': 'Süresi Dolmuş',
        'suspended': 'Askıya Alınmış'
    };
    return statuses[status] || status;
}

// Kullanıcı rolünü Türkçe metne çevirme
function getUserRoleName(role) {
    const roles = {
        'admin': 'Yönetici',
        'user': 'Kullanıcı'
    };
    return roles[role] || role;
}

// Input validasyonu için yardımcı fonksiyon
function validateInput(input, pattern, errorMessage) {
    const value = input.value.trim();
    const isValid = pattern.test(value);
    
    const feedback = input.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.textContent = isValid ? '' : errorMessage;
    }
    
    input.classList.toggle('is-invalid', !isValid);
    input.classList.toggle('is-valid', isValid);
    
    return isValid;
}

// Form validasyonu için yardımcı fonksiyon
function validateForm(form, validations) {
    let isValid = true;
    
    for (const [inputName, validation] of Object.entries(validations)) {
        const input = form.querySelector(`[name="${inputName}"]`);
        if (input) {
            const inputValid = validateInput(input, validation.pattern, validation.message);
            isValid = isValid && inputValid;
        }
    }
    
    return isValid;
}

// Sayfa yüklendiğinde çalışacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // DataTables için varsayılan dil ayarı
    if (typeof $.fn.dataTable !== 'undefined') {
        $.extend(true, $.fn.dataTable.defaults, {
            language: dataTablesTurkish
        });
    }
    
    // Bootstrap tooltips'i aktifleştir
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}); 