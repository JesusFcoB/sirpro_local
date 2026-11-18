@echo off
setlocal EnableExtensions
title Sirpro - Punto de venta

rem =========================================================
rem  SIRPRO - Punto de venta
rem  Levanta los servicios necesarios, crea la base de datos
rem  la primera vez y los detiene al cerrar.
rem
rem  Uso:  iniciar_sirpro.bat             (abre el navegador)
rem        iniciar_sirpro.bat /nobrowser  (sin abrir navegador)
rem        iniciar_sirpro.bat /auto       (prueba: cierra solo tras 5 s)
rem =========================================================

set "BASE=%~dp0"
set "XAMPP=C:\xampp"
set "PUERTO=8000"
set "MYSQL_INICIADO=0"

if not exist "%XAMPP%\php\php.exe" set "XAMPP=D:\xampp"
if not exist "%XAMPP%\php\php.exe" (
    echo No se encontr¢ XAMPP en C:\xampp ni D:\xampp.
    echo Edita la variable XAMPP dentro de este archivo y vuelve a intentarlo.
    pause
    exit /b 1
)

set "PHP=%XAMPP%\php\php.exe"
set "MYSQLBIN=%XAMPP%\mysql\bin"

echo ==========================================
echo   Sirpro - Punto de venta
echo ==========================================
echo.

rem ---------- MySQL ----------
call :puerto_ok 3306
if "%PUERTO_OK%"=="1" goto mysql_listo

echo Iniciando MySQL...
if exist "%MYSQLBIN%\my.ini" (
    start "MySQL Sirpro" /min "%MYSQLBIN%\mysqld.exe" --defaults-file="%MYSQLBIN%\my.ini"
) else (
    start "MySQL Sirpro" /min "%MYSQLBIN%\mysqld.exe"
)
set "MYSQL_INICIADO=1"
set /a INTENTOS=0

:esperar_mysql
"%MYSQLBIN%\mysqladmin.exe" -u root ping >nul 2>&1
if not errorlevel 1 goto mysql_listo
set /a INTENTOS+=1
if %INTENTOS% gtr 60 (
    echo No se pudo iniciar MySQL. Revisa el panel de XAMPP.
    pause
    exit /b 1
)
timeout /t 1 /nobreak >nul 2>nul
goto esperar_mysql

:mysql_listo
echo MySQL listo.

rem ---------- Base de datos ----------
"%MYSQLBIN%\mysql.exe" -u root -e "USE sirpro; SELECT 1 FROM usuarios LIMIT 1;" >nul 2>&1
if not errorlevel 1 goto db_lista

echo Creando la base de datos sirpro ^(solo la primera vez^)...
"%MYSQLBIN%\mysql.exe" -u root --default-character-set=utf8mb4 < "%BASE%database.sql"
if errorlevel 1 (
    echo Ocurri¢ un problema al importar database.sql
    pause
    exit /b 1
)
echo Base de datos creada con datos de ejemplo.

:db_lista
echo Base de datos lista.

rem ---------- Servidor web ----------
call :puerto_ok %PUERTO%
if "%PUERTO_OK%"=="1" set "PUERTO=8001"

echo Iniciando servidor web local en el puerto %PUERTO%...
start "Sirpro PHP" /min "%PHP%" -S 127.0.0.1:%PUERTO% -t "%BASE%"
timeout /t 2 /nobreak >nul 2>nul

rem captura del PID del servidor web
set "PHP_PID="
powershell -NoProfile -Command "(Get-NetTCPConnection -LocalPort %PUERTO% -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess" > "%BASE%_php_pid.txt" 2>nul
set /p PHP_PID=<"%BASE%_php_pid.txt"

if /i "%~1"=="/nobrowser" goto sin_navegador
if /i "%~2"=="/nobrowser" goto sin_navegador
start "" http://127.0.0.1:%PUERTO%/login.php

:sin_navegador
echo.
echo ------------------------------------------
echo  Sirpro est  corriendo en:
echo      http://127.0.0.1:%PUERTO%
echo  Usuario: admin     Contrase¤a: admin123
echo ------------------------------------------
echo.
echo  NO cierres esta ventana con la X.
echo  Presiona una tecla para detener los servicios y cerrar.
echo.

set "AUTO=0"
if /i "%~1"=="/auto" set "AUTO=1"
if /i "%~2"=="/auto" set "AUTO=1"
if "%AUTO%"=="1" (
    timeout /t 5 /nobreak >nul 2>nul
) else (
    pause >nul
)

rem ---------- Apagado ----------
echo.
echo Deteniendo servicios...
if not "%PHP_PID%"=="" taskkill /pid %PHP_PID% /f >nul 2>&1

rem red de seguridad: solo el servidor PHP de Sirpro (por linea de comandos)
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { $_.CommandLine -like '*-S 127.0.0.1:*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }" >nul 2>&1

if "%MYSQL_INICIADO%"=="1" (
    echo Deteniendo MySQL...
    "%MYSQLBIN%\mysqladmin.exe" -u root shutdown >nul 2>&1
    timeout /t 2 /nobreak >nul 2>nul
    taskkill /im mysqld.exe /f >nul 2>&1
)
echo Listo. Ya puedes cerrar esta ventana.

type nul > "%BASE%_fin.txt"
endlocal
exit /b 0

rem ---------- funcion: detecta si un puerto esta en escucha ----------
:puerto_ok
set "PUERTO_OK=0"
for /f %%R in ('powershell -NoProfile -Command "$c=New-Object Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1',%~1); '1' } catch { '0' } finally { $c.Close() }"') do set "PUERTO_OK=%%R"
exit /b 0
