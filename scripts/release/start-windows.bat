@echo off
setlocal

cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo [ShopWeb] PHP was not found in PATH.
    echo Please install PHP 8.3+ and enable required extensions, then run this script again.
    pause
    exit /b 1
)

if not exist ".env" (
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo [ShopWeb] Created .env from .env.example.
    )
)

if not exist "storage\app" mkdir "storage\app"
if not exist "storage\app\private" mkdir "storage\app\private"
if not exist "storage\app\private\payment-proofs" mkdir "storage\app\private\payment-proofs"
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data"
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions"
if not exist "storage\framework\views" mkdir "storage\framework\views"
if not exist "storage\logs" mkdir "storage\logs"
if not exist "bootstrap\cache" mkdir "bootstrap\cache"
if not exist "public\uploads" mkdir "public\uploads"

echo [ShopWeb] Starting local server at http://127.0.0.1:8000
echo [ShopWeb] First install URL: http://127.0.0.1:8000/install
echo [ShopWeb] Press Ctrl+C to stop.

php artisan serve --host=127.0.0.1 --port=8000

endlocal
