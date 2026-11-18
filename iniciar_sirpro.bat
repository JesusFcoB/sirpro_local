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
    echo No se encuentra XAMPP en C:\xampp ni D:\xampp.
    echo Edita la variable XAMPP dentro de este archivo y lo intenta de nuevo.
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
    echo No se puede iniciar MySQL. Revisa el panel de XAMPP.
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
    echo Ocurre un problema al importar database.sql
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
start /b "" "%PHP%" -S 127.0.0.1:%PUERTO% -t "%BASE%" >nul 2>&1

rem captura del PID y arranca el vigilante cuanto antes
set "PHP_PID="
set /a INTENTOS=0
:esperar_php
powershell -NoProfile -Command "(Get-NetTCPConnection -LocalPort %PUERTO% -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1).OwningProcess" > "%BASE%_php_pid.txt" 2>nul
set /p PHP_PID=<"%BASE%_php_pid.txt"
if not "%PHP_PID%"=="" goto php_listo
set /a INTENTOS+=1
if %INTENTOS% gtr 20 goto php_listo
timeout /t 1 /nobreak >nul 2>nul
goto esperar_php

:php_listo
rem vigilante: apaga el PHP si el lanzador se cierra con la X
if not "%PHP_PID%"=="" start "Vigilante Sirpro" /min powershell -NoProfile -ExecutionPolicy Bypass -File "%BASE%sirpro_servicios.ps1" -Php %PHP_PID% -Puerto %PUERTO% -MysqlAdmin "%MYSQLBIN%\mysqladmin.exe" -MysqlIniciado %MYSQL_INICIADO%

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
echo  Si cierras esta ventana con la X, el PHP se detiene solo.
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

rem red de seguridad: apaga solo el PHP de Sirpro en este puerto
powershell -NoProfile -ExecutionPolicy Bypass -File "%BASE%sirpro_servicios.ps1" -Puerto %PUERTO% >nul 2>&1

if "%MYSQL_INICIADO%"=="1" (
    echo Deteniendo MySQL...
    "%MYSQLBIN%\mysqladmin.exe" -u root shutdown >nul 2>&1
    timeout /t 2 /nobreak >nul 2>nul
    )
echo Listo. Ya puedes cerrar esta ventana.

endlocal
exit /b 0

rem ---------- funcion: detecta si un puerto esta en escucha ----------
:puerto_ok
set "PUERTO_OK=0"
for /f %%R in ('powershell -NoProfile -Command "$c=New-Object Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1',%~1); '1' } catch { '0' } finally { $c.Close() }"') do set "PUERTO_OK=%%R"
exit /b 0
