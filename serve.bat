@echo off
setlocal

where php >nul 2>nul
if errorlevel 1 (
    echo ============================================================
    echo  ERROR: PHP was not found on your PATH.
    echo ============================================================
    echo.
    echo  This can happen with bundled Apache+MySQL/MariaDB+PHP stacks
    echo  that do NOT add PHP to PATH automatically ^(unlike XAMPP^).
    echo.
    echo  To fix this, do ONE of the following:
    echo.
    echo   1^) Add your PHP folder to PATH:
    echo      - Find where php.exe lives, e.g.:
    echo          C:\xampp\php
    echo          C:\wamp64\bin\php\php8.x.x
    echo          C:\laragon\bin\php\php-8.x.x
    echo      - Open "Edit the system environment variables" ^(Windows
    echo        search^) -^> Environment Variables -^> under "System
    echo        variables" select "Path" -^> Edit -^> New -^> paste that
    echo        folder path -^> OK on every dialog.
    echo      - Close and reopen this terminal / double-click serve.bat
    echo        again.
    echo.
    echo   2^) Or run this script from a shell where PHP is already
    echo      set up, e.g. XAMPP's "Shell" button, or WAMP's/Laragon's
    echo      bundled terminal.
    echo.
    echo   3^) Or edit this file and set PHP_EXE below to the full path
    echo      of your php.exe instead of relying on PATH.
    echo.
    pause
    exit /b 1
)

for /f "delims=" %%v in ('php -r "echo PHP_VERSION;" 2^>nul') do set PHP_VERSION=%%v
echo PHP found on PATH ^(version %PHP_VERSION%^).
echo.
echo Starting PHP built-in server for BAMBOSA...
echo   Root: %~dp0
echo   URL:  http://localhost:8000/
echo   ^(error pages come from error\router.php^)
echo.
echo Reminder: MySQL/MariaDB must be running separately, and
echo config\database.php must be configured, for the app to work.
echo.
echo Press Ctrl+C to stop.
echo.
php -S localhost:8000 -t "%~dp0" "%~dp0error\router.php"

endlocal
