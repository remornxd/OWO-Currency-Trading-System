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
    <title><?= SITE_NAME ?> - Giriş</title>
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
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo i {
            font-size: 3rem;
            color: var(--primary-color);
        }
        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            font-weight: bold;
            color: var(--dark-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card fade-in">
            <div class="login-logo">
                <i class="fas fa-robot"></i>
            </div>
            <h1 class="login-title"><?= SITE_NAME ?></h1>
            <form id="loginForm" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="invalid-feedback">Lütfen kullanıcı adınızı girin.</div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">Lütfen şifrenizi girin.</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                </button>
                <div class="text-center">
                    <a href="register.php" class="text-decoration-none">Hesabınız yok mu? Kayıt olun</a>
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
            const loginForm = document.getElementById('loginForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            // Şifre göster/gizle
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Form gönderimi
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Form validasyonu
                const validations = {
                    username: {
                        pattern: /^[a-zA-Z0-9_]{3,20}$/,
                        message: 'Kullanıcı adı 3-20 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.'
                    },
                    password: {
                        pattern: /^.{6,}$/,
                        message: 'Şifre en az 6 karakter olmalıdır.'
                    }
                };

                if (!validateForm(loginForm, validations)) {
                    return;
                }

                try {
                    const response = await apiRequest('/api/auth/login', 'POST', formDataToJson(loginForm));
                    
                    if (response.success) {
                        showToast('Giriş başarılı! Yönlendiriliyorsunuz...', 'success');
                        setTimeout(() => {
                            window.location.href = response.redirect || '/';
                        }, 1000);
                    }
                } catch (error) {
                    console.error('Giriş hatası:', error);
                }
            });
        });
    </script>
</body>
</html> 