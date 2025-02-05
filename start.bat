@echo off
echo OWO System Başlatılıyor...

:: Web sunucusunu başlat
start "Web Server" cmd /c "start_web.bat"

:: 5 saniye bekle
timeout /t 5 /nobreak > nul

:: Discord botunu başlat
start "Discord Bot" cmd /c "start_bot.bat"

echo.
echo Sistem başlatıldı!
echo Web Panel: http://localhost/owo-system
echo.
echo Çıkmak için bu pencereyi kapatabilirsiniz.
pause 