@echo off
echo Web Sunucusu Başlatılıyor...

:: XAMPP veya WAMP kontrolü
if exist "C:\xampp\apache_start.bat" (
    echo XAMPP bulundu, Apache ve MySQL başlatılıyor...
    set "SERVER_TYPE=XAMPP"
    set "WEB_ROOT=C:\xampp\htdocs"
    set "PHP_PATH=C:\xampp\php"
    set "APACHE_PATH=C:\xampp\apache"
    
    :: PHP'yi PATH'e ekle
    set "PATH=%PHP_PATH%;%PATH%"
    
    :: Apache ve MySQL'i durdur (eğer çalışıyorsa)
    taskkill /F /IM httpd.exe /T > nul 2>&1
    taskkill /F /IM mysqld.exe /T > nul 2>&1
    
    :: 2 saniye bekle
    timeout /t 2 /nobreak > nul
    
    :: Apache ve MySQL'i başlat
    start "" /B "C:\xampp\apache_start.bat"
    start "" /B "C:\xampp\mysql_start.bat"
    
) else if exist "C:\wamp64\wampmanager.exe" (
    echo WAMP bulundu, başlatılıyor...
    set "SERVER_TYPE=WAMP"
    set "WEB_ROOT=C:\wamp64\www"
    set "PHP_PATH=C:\wamp64\bin\php\php8.2.12"
    set "APACHE_PATH=C:\wamp64\bin\apache\apache2.4.58"
    
    :: PHP'yi PATH'e ekle
    set "PATH=%PHP_PATH%;%PATH%"
    
    :: WAMP'ı yeniden başlat
    taskkill /F /IM wampmanager.exe /T > nul 2>&1
    timeout /t 2 /nobreak > nul
    start "" "C:\wamp64\wampmanager.exe"
) else (
    echo Hata: XAMPP veya WAMP bulunamadı!
    echo Lütfen XAMPP veya WAMP'ı yükleyin.
    echo XAMPP: https://www.apachefriends.org/download.html
    echo WAMP: https://www.wampserver.com/en/download-wampserver-64bits/
    pause
    exit
)

:: Veritabanını oluştur
echo Veritabanı oluşturuluyor...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS owo_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

:: Proje klasörünü web root'a kopyala
echo Proje dosyaları web sunucusuna kopyalanıyor...
if exist "%WEB_ROOT%\owo-system" (
    rmdir /S /Q "%WEB_ROOT%\owo-system"
)
mkdir "%WEB_ROOT%\owo-system"

:: Dosya izinlerini ayarla
echo Dosya izinleri ayarlanıyor...
icacls "%WEB_ROOT%\owo-system" /grant Everyone:(OI)(CI)F /T

:: Dosyaları kopyala
xcopy /E /Y /I "." "%WEB_ROOT%\owo-system"

:: index.php oluştur
echo ^<?php > "%WEB_ROOT%\owo-system\index.php"
echo require_once 'config/config.php'; >> "%WEB_ROOT%\owo-system\index.php"

:: PHP hata raporlamasını etkinleştir
echo PHP hata raporlaması etkinleştiriliyor...
echo ^<?php > "%WEB_ROOT%\owo-system\error_reporting.php"
echo error_reporting(E_ALL); >> "%WEB_ROOT%\owo-system\error_reporting.php"
echo ini_set('display_errors', 1); >> "%WEB_ROOT%\owo-system\error_reporting.php"
echo ini_set('log_errors', 1); >> "%WEB_ROOT%\owo-system\error_reporting.php"
echo ini_set('error_log', dirname(__FILE__) . '/error.log'); >> "%WEB_ROOT%\owo-system\error_reporting.php"
echo require_once 'config/config.php'; >> "%WEB_ROOT%\owo-system\error_reporting.php"

:: .htaccess dosyasını güncelle
echo Sunucu ayarları yapılandırılıyor...
echo Options +FollowSymLinks -MultiViews -Indexes > "%WEB_ROOT%\owo-system\.htaccess"
echo RewriteEngine On >> "%WEB_ROOT%\owo-system\.htaccess"
echo RewriteBase /owo-system/ >> "%WEB_ROOT%\owo-system\.htaccess"
echo RewriteCond %%{REQUEST_FILENAME} !-f >> "%WEB_ROOT%\owo-system\.htaccess"
echo RewriteCond %%{REQUEST_FILENAME} !-d >> "%WEB_ROOT%\owo-system\.htaccess"
echo RewriteRule ^(.*)$ index.php?/$1 [L,QSA] >> "%WEB_ROOT%\owo-system\.htaccess"
echo php_flag display_errors on >> "%WEB_ROOT%\owo-system\.htaccess"
echo php_value error_reporting E_ALL >> "%WEB_ROOT%\owo-system\.htaccess"

:: Config dosyasını oluştur
if not exist "%WEB_ROOT%\owo-system\config\config.php" (
    echo Config dosyası oluşturuluyor...
    copy "%WEB_ROOT%\owo-system\config\config.example.php" "%WEB_ROOT%\owo-system\config\config.php"
)

:: Veritabanı kurulumunu yap
echo Veritabanı tabloları oluşturuluyor...
cd "%WEB_ROOT%\owo-system"
php -f install.php

:: Apache'yi yeniden başlat
if "%SERVER_TYPE%"=="XAMPP" (
    "%APACHE_PATH%\bin\httpd.exe" -k restart
) else (
    net stop wampapache64
    timeout /t 2 /nobreak > nul
    net start wampapache64
)

:: 5 saniye bekle
echo Web sunucusu başlatılıyor...
timeout /t 5 /nobreak > nul

:: Tarayıcıyı aç
start http://localhost/owo-system/error_reporting.php

echo.
echo Web sunucusu başlatıldı!
echo Panel: http://localhost/owo-system
echo Hata raporu: http://localhost/owo-system/error_reporting.php
echo.
echo Çıkmak için bu pencereyi kapatabilirsiniz.
pause 