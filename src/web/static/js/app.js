// WebSocket bağlantısı
let ws;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;

// Token yönetimi için sınıf
class TokenManager {
    constructor() {
        this.tokens = new Map();
        this.stats = {
            total_tokens: 0,
            active_tokens: 0,
            busy_tokens: 0,
            banned_tokens: 0,
            total_owo: 0,
            total_messages: 0
        };
        this.initWebSocket();
    }

    initWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        ws = new WebSocket(`${protocol}//${window.location.host}/ws`);

        ws.onopen = () => {
            console.log('WebSocket bağlantısı kuruldu');
            reconnectAttempts = 0;
            this.updateConnectionStatus('connected');
        };

        ws.onclose = () => {
            console.log('WebSocket bağlantısı kapandı');
            this.updateConnectionStatus('disconnected');
            this.tryReconnect();
        };

        ws.onerror = (error) => {
            console.error('WebSocket hatası:', error);
            this.updateConnectionStatus('error');
        };

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleUpdate(data);
        };
    }

    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.className = `connection-status ${status}`;
            statusElement.innerHTML = `
                <i class="fas fa-${status === 'connected' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${status === 'connected' ? 'Bağlı' : 'Bağlantı Kesildi'}
            `;
        }
    }

    tryReconnect() {
        if (reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            console.log(`Yeniden bağlanma denemesi: ${reconnectAttempts}`);
            setTimeout(() => this.initWebSocket(), 5000);
        } else {
            console.error('Maksimum yeniden bağlanma denemesi aşıldı');
            this.updateConnectionStatus('error');
        }
    }

    handleUpdate(data) {
        switch (data.type) {
            case 'token_created':
                this.addToken(data.data);
                break;
            case 'token_updated':
                this.updateToken(data.data);
                break;
            case 'token_started':
                this.updateTokenStatus(data.data.token_id, 'busy');
                break;
            case 'token_stopped':
                this.updateTokenStatus(data.data.token_id, 'available');
                this.updateTokenStats(data.data.token_id, data.data.stats);
                break;
            case 'stats_update':
                this.updateStats(data.data);
                break;
        }
    }

    addToken(token) {
        this.tokens.set(token.id, token);
        this.updateTable();
        this.showNotification('Yeni Token Eklendi', `Token ID: ${token.id.substring(0, 8)}...`);
    }

    updateToken(token) {
        this.tokens.set(token.id, token);
        this.updateTable();
    }

    updateTokenStatus(tokenId, status) {
        const token = this.tokens.get(tokenId);
        if (token) {
            token.status = status;
            this.updateTable();
            this.showNotification(
                'Token Durumu Değişti',
                `Token ${status === 'busy' ? 'çalışıyor' : 'durdu'}`
            );
        }
    }

    updateTokenStats(tokenId, stats) {
        const token = this.tokens.get(tokenId);
        if (token) {
            Object.assign(token, stats);
            this.updateTable();
        }
    }

    updateStats(stats) {
        this.stats = stats;
        this.updateDashboard();
    }

    updateTable() {
        const table = $('#tokenTable').DataTable();
        table.clear();
        
        this.tokens.forEach(token => {
            table.row.add([
                token.id.substring(0, 8),
                this.getStatusBadge(token.status),
                this.formatNumber(token.owo_balance),
                this.formatNumber(token.total_messages),
                this.formatDate(token.last_update),
                this.getActionButtons(token)
            ]);
        });
        
        table.draw();
    }

    updateDashboard() {
        document.getElementById('total-owo').textContent = this.formatNumber(this.stats.total_owo);
        document.getElementById('active-tokens').textContent = this.stats.active_tokens;
        document.getElementById('busy-tokens').textContent = this.stats.busy_tokens;
        document.getElementById('banned-tokens').textContent = this.stats.banned_tokens;
    }

    getStatusBadge(status) {
        const colors = {
            available: 'success',
            busy: 'warning',
            banned: 'danger'
        };
        return `<span class="badge bg-${colors[status]}">${status}</span>`;
    }

    getActionButtons(token) {
        const buttons = [];
        
        if (token.status !== 'busy') {
            buttons.push(`
                <button class="btn btn-sm btn-primary" onclick="startToken('${token.id}')">
                    <i class="fas fa-play"></i>
                </button>
            `);
        } else {
            buttons.push(`
                <button class="btn btn-sm btn-danger" onclick="stopToken('${token.id}')">
                    <i class="fas fa-stop"></i>
                </button>
            `);
        }
        
        buttons.push(`
            <button class="btn btn-sm btn-info" onclick="showTokenDetails('${token.id}')">
                <i class="fas fa-info-circle"></i>
            </button>
        `);
        
        return `<div class="btn-group">${buttons.join('')}</div>`;
    }

    formatNumber(number) {
        return new Intl.NumberFormat('tr-TR').format(number);
    }

    formatDate(date) {
        return new Date(date).toLocaleString('tr-TR');
    }

    showNotification(title, message) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        
        document.getElementById('toast-container').appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

// Global token yöneticisi
const tokenManager = new TokenManager();

// Token işlemleri
async function addToken() {
    const form = document.getElementById('addTokenForm');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/api/tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(Object.fromEntries(formData))
        });

        if (response.ok) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addTokenModal'));
            modal.hide();
            form.reset();
        } else {
            const data = await response.json();
            showError(data.detail || 'Token eklenirken bir hata oluştu!');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Token eklenirken bir hata oluştu!');
    }
}

async function startToken(tokenId) {
    try {
        const response = await fetch(`/api/tokens/${tokenId}/start`, {
            method: 'POST'
        });

        if (!response.ok) {
            const data = await response.json();
            showError(data.detail || 'Token başlatılırken bir hata oluştu!');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Token başlatılırken bir hata oluştu!');
    }
}

async function stopToken(tokenId) {
    try {
        const response = await fetch(`/api/tokens/${tokenId}/stop`, {
            method: 'POST'
        });

        if (!response.ok) {
            const data = await response.json();
            showError(data.detail || 'Token durdurulurken bir hata oluştu!');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Token durdurulurken bir hata oluştu!');
    }
}

async function showTokenDetails(tokenId) {
    const modal = new bootstrap.Modal(document.getElementById('tokenDetailModal'));
    const contentDiv = document.getElementById('tokenDetailContent');
    contentDiv.innerHTML = '<div class="loading"></div>';
    modal.show();
    
    try {
        const response = await fetch(`/api/tokens/${tokenId}`);
        if (response.ok) {
            const token = await response.json();
            contentDiv.innerHTML = `
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <th>Token ID:</th>
                            <td>${token.id}</td>
                        </tr>
                        <tr>
                            <th>Durum:</th>
                            <td>${tokenManager.getStatusBadge(token.status)}</td>
                        </tr>
                        <tr>
                            <th>OWO Bakiyesi:</th>
                            <td>${tokenManager.formatNumber(token.owo_balance)}</td>
                        </tr>
                        <tr>
                            <th>Mesaj Sayısı:</th>
                            <td>${tokenManager.formatNumber(token.total_messages)}</td>
                        </tr>
                        <tr>
                            <th>Kanal ID:</th>
                            <td>${token.channel_id}</td>
                        </tr>
                        <tr>
                            <th>Son Güncelleme:</th>
                            <td>${tokenManager.formatDate(token.last_update)}</td>
                        </tr>
                    </table>
                </div>
            `;
        } else {
            contentDiv.innerHTML = '<div class="alert alert-danger">Token bilgileri alınamadı!</div>';
        }
    } catch (error) {
        console.error('Error:', error);
        contentDiv.innerHTML = '<div class="alert alert-danger">Bir hata oluştu!</div>';
    }
}

function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `
        <div class="toast-header bg-danger text-white">
            <strong class="me-auto">Hata</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">${message}</div>
    `;
    
    document.getElementById('toast-container').appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', () => {
    // DataTable'ı başlat
    $('#tokenTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
        },
        order: [[4, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]],
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>rtip',
        responsive: true
    });
    
    // Toast container'ı oluştur
    const toastContainer = document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(toastContainer);
}); 