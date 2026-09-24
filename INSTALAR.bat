@echo off
title Sistema de Barberia Pro - Instalacion Automatica
color 0B
cls

echo.
echo ============================================================
echo    BARBERIA PRO - INSTALACION AUTOMATICA
echo ============================================================
echo.

:: Verificar si estamos en la ubicación correcta
if not exist "backend" (
    echo ERROR: Este script debe ejecutarse desde la carpeta principal de barberia
    echo.
    echo Ubicacion actual: %CD%
    echo Ubicacion esperada: C:\xampp\htdocs\barberia
    echo.
    pause
    exit
)

:: Verificar XAMPP
echo [1/7] Verificando XAMPP...
if exist "C:\xampp" (
    echo    XAMPP encontrado en C:\xampp
) else (
    echo    XAMPP no encontrado
    echo.
    echo ============================================================
    echo   REQUISITO: XAMPP
    echo ============================================================
    echo XAMPP es necesario para ejecutar este sistema.
    echo Descargalo e instalalo desde: https://www.apachefriends.org/
    echo.
    echo Una vez instalado, vuelve a ejecutar este script.
    echo.
    pause
    exit
)

:: Verificar que Apache y MySQL estén corriendo
echo [2/7] Verificando servicios de XAMPP...
tasklist | find "httpd.exe" > nul
if errorlevel 1 (
    echo    Apache no esta corriendo
    echo.
    echo Por favor inicia Apache desde el panel de control de XAMPP
    echo y vuelve a ejecutar este script.
    echo.
    pause
    exit
) else (
    echo    Apache esta corriendo
)

tasklist | find "mysqld.exe" > nul
if errorlevel 1 (
    echo    MySQL no esta corriendo
    echo.
    echo Por favor inicia MySQL desde el panel de control de XAMPP
    echo y vuelve a ejecutar este script.
    echo.
    pause
    exit
) else (
    echo    MySQL esta corriendo
)

:: Verificar PHP
echo [3/7] Verificando PHP...
php --version > nul 2>&1
if errorlevel 1 (
    echo    PHP no encontrado en PATH
    echo    Intentando usar PHP de XAMPP...
    set "PATH=C:\xampp\php;%PATH%"
    php --version > nul 2>&1
    if errorlevel 1 (
        echo    PHP no encontrado ni en PATH ni en XAMPP
        echo.
        echo Asegurate de que XAMPP este instalado correctamente.
        echo.
        pause
        exit
    )
)
echo    PHP encontrado

:: Verificar Composer
echo [4/7] Verificando Composer...
composer --version > nul 2>&1
if errorlevel 1 (
    echo    Composer no encontrado
    echo.
    echo ============================================================
    echo   REQUISITO: COMPOSER
    echo ============================================================
    echo Composer es necesario para instalar dependencias de Laravel.
    echo Descargalo e instalalo desde: https://getcomposer.org/download/
    echo.
    echo Una vez instalado, vuelve a ejecutar este script.
    echo.
    pause
    exit
)
echo    Composer encontrado

:: Verificar Node.js
echo [5/7] Verificando Node.js...
node --version > nul 2>&1
if errorlevel 1 (
    echo    Node.js no encontrado
    echo.
    echo ============================================================
    echo   REQUISITO: NODE.JS
    echo ============================================================
    echo Node.js es necesario para instalar dependencias de React.
    echo Descargalo e instalalo desde: https://nodejs.org/
    echo.
    echo Una vez instalado, vuelve a ejecutar este script.
    echo.
    pause
    exit
)
echo    Node.js encontrado

:: Verificar npm
npm --version > nul 2>&1
if errorlevel 1 (
    echo    npm no encontrado
    echo.
    echo npm deberia venir con Node.js. Por favor reinstala Node.js.
    echo.
    pause
    exit
)
echo    npm encontrado

echo.
echo ============================================================
echo  TODOS LOS REQUISITOS CUMPLIDOS
echo ============================================================
echo.

:: Instalación del Backend
echo [6/7] Instalando Backend (Laravel)...
cd /d "%~dp0backend"

echo    Instalando dependencias de Laravel...
call composer install --no-interaction --prefer-dist
if errorlevel 1 (
    echo    Error instalando dependencias de Laravel
    echo    Intentando con opciones alternativas...
    call composer install --no-interaction
    if errorlevel 1 (
        echo    Error critico en instalacion de Laravel
        pause
        exit
    )
)
echo    Dependencias de Laravel instaladas

echo    Configurando archivo .env...
if not exist .env (
    copy .env.example .env
)
call php artisan key:generate > nul 2>&1

echo    Configurando credenciales MySQL...
powershell -Command "(Get-Content .env) -replace '^DB_USERNAME=.*', 'DB_USERNAME=root' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace '^DB_PASSWORD=.*', 'DB_PASSWORD=' | Set-Content .env"

echo    Creando base de datos...
echo CREATE DATABASE IF NOT EXISTS barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; > temp_db.sql
call mysql -u root < temp_db.sql
if errorlevel 1 (
    echo    Error con usuario root sin contraseña
    echo    Intentando solicitar contraseña...
    set /p MYSQL_PASSWORD="Ingresa contraseña de MySQL (presiona Enter si no tiene): "
    if "%MYSQL_PASSWORD%"=="" (
        echo CREATE DATABASE IF NOT EXISTS barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; > temp_db.sql
        call mysql -u root < temp_db.sql
    ) else (
        echo CREATE DATABASE IF NOT EXISTS barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; > temp_db.sql
        call mysql -u root -p%MYSQL_PASSWORD% < temp_db.sql
    )
    if errorlevel 1 (
        echo    Error creando base de datos
        del temp_db.sql
        pause
        exit
    )
)
del temp_db.sql
echo    Base de datos creada

echo    Ejecutando migraciones y seeders...
call php artisan migrate --seed --force
if errorlevel 1 (
    echo    Error en migraciones
    echo    Intentando limpiar caches y reintentar...
    call php artisan config:clear > nul 2>&1
    call php artisan cache:clear > nul 2>&1
    call php artisan migrate --seed --force
    if errorlevel 1 (
        echo    Error critico en migraciones
        pause
        exit
    )
)
echo    Migraciones y seeders ejecutados

:: Instalación del Frontend
echo [7/7] Instalando Frontend (React)...
cd /d "%~dp0frontend"

echo    Instalando dependencias de React...
call npm install --silent --no-audit --no-fund
if errorlevel 1 (
    echo    Error instalando dependencias de React
    echo    Intentando con opciones alternativas...
    call npm install
    if errorlevel 1 (
        echo    Error critico en instalacion de React
        pause
        exit
    )
)
echo    Dependencias de React instaladas

echo    Instalando configuración de Tailwind CSS...
call npm install @tailwindcss/postcss --silent --no-audit --no-fund
if errorlevel 1 (
    echo    Error instalando @tailwindcss/postcss
    echo    Intentando con opciones alternativas...
    call npm install @tailwindcss/postcss
    if errorlevel 1 (
        echo    Error critico en instalacion de Tailwind
        pause
        exit
    )
)
echo    Tailwind CSS configurado

echo.
echo ============================================================
echo  INSTALACION COMPLETADA EXITOSAMENTE
echo ============================================================
echo.
echo El sistema esta listo para usar.
echo.
echo PASOS SIGUIENTES:
echo 1. Ejecuta INICIAR.bat para iniciar el sistema
echo 2. Abre la URL que aparezca en tu navegador
echo.
echo CREDENCIALES DE PRUEBA:
echo - Admin: admin@barberia.com / password
echo - Cliente: cliente1@test.com / password
echo - Barbero: barbero01@barberia.com / password
echo - Supervisor: supervisor0@barberia.com / password
echo.
pause