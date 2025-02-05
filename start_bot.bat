@echo off
echo Discord Bot Başlatılıyor...

:: Sanal ortamı aktive et
call venv\Scripts\activate

:: Botu başlat
python src/main.py

pause 