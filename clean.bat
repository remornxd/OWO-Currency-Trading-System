@echo off
echo Temizleme işlemi başlatılıyor...

:: Sanal ortamı deaktive et
if exist venv\Scripts\activate.bat call venv\Scripts\deactivate.bat

:: Sanal ortam klasörünü sil
if exist venv rmdir /s /q venv

:: Önbellek dosyalarını temizle
if exist src\__pycache__ rmdir /s /q src\__pycache__
if exist src\bot\__pycache__ rmdir /s /q src\bot\__pycache__
if exist src\web\__pycache__ rmdir /s /q src\web\__pycache__

echo Temizleme tamamlandı!
pause 