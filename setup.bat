@echo off
echo OWO System Kurulumu Başlatılıyor...

:: Python 3.11'in yüklü olup olmadığını kontrol et
python --version 2>NUL
if errorlevel 1 goto errorNoPython

:: Mevcut sanal ortamı temizle
if exist venv\Scripts\activate.bat call venv\Scripts\deactivate.bat
if exist venv rmdir /s /q venv

:: Sanal ortam oluştur
python -m venv venv
call venv\Scripts\activate

:: pip'i güncelle
python -m pip install --upgrade pip

:: Önce discord.py'yi kaldır (eğer varsa)
pip uninstall discord.py -y

:: Gerekli paketleri yükle
pip install wheel
pip install --upgrade setuptools
pip install discord.py-self==1.9.2
pip install -r requirements.txt

echo Kurulum tamamlandı!
echo Sistemi başlatmak için 'start.bat' dosyasını çalıştırın.
pause
exit

:errorNoPython
echo.
echo Hata: Python bulunamadı!
echo Lütfen Python 3.11'i yüklerken şunlara dikkat edin:
echo 1. "Add Python to PATH" seçeneğini işaretleyin
echo 2. "Install for all users" seçeneğini işaretleyin
echo 3. Özel kurulum seçeneğini seçin ve tüm özellikleri işaretleyin
echo.
echo Python 3.11 indirme linki: https://www.python.org/downloads/release/python-3116/
echo.
pause 