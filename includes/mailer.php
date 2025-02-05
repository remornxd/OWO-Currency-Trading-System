<?php
require_once '../config/config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;
    private $settings;
    
    public function __construct() {
        global $db;
        
        // Mail ayarlarını al
        $stmt = $db->query("
            SELECT `key`, value 
            FROM settings 
            WHERE `key` IN (
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'mail_from_address',
                'mail_from_name',
                'site_name'
            )
        ");
        $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // PHPMailer'ı yapılandır
        $this->mailer = new PHPMailer(true);
        
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->settings['smtp_host'] ?? 'localhost';
            $this->mailer->Port = $this->settings['smtp_port'] ?? 587;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->settings['smtp_username'] ?? '';
            $this->mailer->Password = $this->settings['smtp_password'] ?? '';
            $this->mailer->SMTPSecure = $this->settings['smtp_encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            
            $this->mailer->setFrom(
                $this->settings['mail_from_address'] ?? 'noreply@example.com',
                $this->settings['mail_from_name'] ?? ($this->settings['site_name'] ?? 'OWO System')
            );
            
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            throw new Exception('Mail yapılandırması başlatılamadı: ' . $e->getMessage());
        }
    }
    
    public function sendPasswordReset($email, $token, $username) {
        try {
            $resetUrl = SITE_URL . '/pages/reset_password.php?token=' . $token;
            
            $this->mailer->addAddress($email, $username);
            $this->mailer->Subject = 'Şifre Sıfırlama Talebi';
            
            $body = "
                <h2>Şifre Sıfırlama</h2>
                <p>Sayın {$username},</p>
                <p>Hesabınız için şifre sıfırlama talebinde bulundunuz.</p>
                <p>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın:</p>
                <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                <p>Bu bağlantı 1 saat süreyle geçerlidir.</p>
                <p>Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
            ";
            
            $this->mailer->Body = $this->getEmailTemplate($body);
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Şifre sıfırlama e-postası gönderilemedi: ' . $e->getMessage());
        }
    }
    
    public function sendPackageExpiring($email, $username, $expiryDate) {
        try {
            $daysLeft = ceil((strtotime($expiryDate) - time()) / (60 * 60 * 24));
            
            $this->mailer->addAddress($email, $username);
            $this->mailer->Subject = 'Paket Süreniz Dolmak Üzere';
            
            $body = "
                <h2>Paket Süresi Bildirimi</h2>
                <p>Sayın {$username},</p>
                <p>Paket sürenizin dolmasına {$daysLeft} gün kaldı.</p>
                <p>Paketinizi yenilemek için lütfen admin ile iletişime geçin.</p>
                <p>Paket Bitiş Tarihi: {$expiryDate}</p>
            ";
            
            $this->mailer->Body = $this->getEmailTemplate($body);
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Paket süresi bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }
    
    public function sendWelcome($email, $username) {
        try {
            $this->mailer->addAddress($email, $username);
            $this->mailer->Subject = 'Hoş Geldiniz';
            
            $body = "
                <h2>Hoş Geldiniz!</h2>
                <p>Sayın {$username},</p>
                <p>OWO System'e hoş geldiniz!</p>
                <p>Hesabınız başarıyla oluşturuldu. Şimdi giriş yapabilir ve tokenlerinizi yönetmeye başlayabilirsiniz.</p>
                <p>Herhangi bir sorunuz olursa, lütfen bizimle iletişime geçmekten çekinmeyin.</p>
            ";
            
            $this->mailer->Body = $this->getEmailTemplate($body);
            $this->mailer->send();
            
            return true;
        } catch (Exception $e) {
            throw new Exception('Hoş geldiniz e-postası gönderilemedi: ' . $e->getMessage());
        }
    }
    
    private function getEmailTemplate($content) {
        $siteName = $this->settings['site_name'] ?? 'OWO System';
        
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>{$siteName}</title>
            </head>
            <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h1 style='color: #333;'>{$siteName}</h1>
                    </div>
                    
                    <div style='background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                        {$content}
                    </div>
                    
                    <div style='text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; color: #666;'>
                        <p>Bu e-posta {$siteName} tarafından otomatik olarak gönderilmiştir.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
} 