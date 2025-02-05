<?php
require_once __DIR__ . '/../config/config.php';

// Oturum kontrolü
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/' . ($_SESSION['role'] === 'admin' ? 'admin/' : '') . 'dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Kayıt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
        }
        .register-card {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-logo i {
            font-size: 3rem;
            color: var(--primary-color);
        }
        .register-title {
            text-align: center;
            margin-bottom: 2rem;
            font-weight: bold;
            color: var(--dark-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card fade-in">
            <div class="register-logo">
                <i class="fas fa-robot"></i>
            </div>
            <h1 class="register-title"><?= SITE_NAME ?></h1>
            <form id="registerForm" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="invalid-feedback">Kullanıcı adı 3-20 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-posta</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Şifre Tekrar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">Şifreler eşleşmiyor.</div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="key" class="form-label">Aktivasyon Anahtarı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="text" class="form-control" id="key" name="key" required>
                        <div class="invalid-feedback">Lütfen geçerli bir aktivasyon anahtarı girin.</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-user-plus me-2"></i>Kayıt Ol
                </button>
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">Zaten hesabınız var mı? Giriş yapın</a>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            // Şifre göster/gizle
            [togglePassword, toggleConfirmPassword].forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.id === 'togglePassword' ? passwordInput : confirmPasswordInput;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            });

            // Form gönderimi
            registerForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Form validasyonu
                const validations = {
                    username: {
                        pattern: /^[a-zA-Z0-9_]{3,20}$/,
                        message: 'Kullanıcı adı 3-20 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.'
                    },
                    email: {
                        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                        message: 'Lütfen geçerli bir e-posta adresi girin.'
                    },
                    password: {
                        pattern: /^.{6,}$/,
                        message: 'Şifre en az 6 karakter olmalıdır.'
                    },
                    key: {
                        pattern: /^[A-Z0-9]{16}$/,
                        message: 'Lütfen geçerli bir aktivasyon anahtarı girin.'
                    }
                };

                if (!validateForm(registerForm, validations)) {
                    return;
                }

                // Şifre eşleşme kontrolü
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add('is-invalid');
                    return;
                }

                const formData = formDataToJson(registerForm);
                delete formData.confirmPassword; // API'ye gönderilmeyecek

                try {
                    const response = await apiRequest('/api/auth/register', 'POST', formData);
                    
                    if (response.success) {
                        showToast('Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz...', 'success');
                        setTimeout(() => {
                            window.location.href = '/pages/login.php';
                        }, 2000);
                    }
                } catch (error) {
                    console.error('Kayıt hatası:', error);
                }
            });
        });
    </script>
</body>
</html> 