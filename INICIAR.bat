@echo off
title Barberia Pro - Iniciando Sistema
color 0B
cls

echo.
echo ============================================================
echo    BARBERIA PRO - INICIANDO SISTEMA
echo ============================================================
echo.

:: Verificar que estemos en la ubicación correcta
if not exist "backend" (
    echo ERROR: Este script debe ejecutarse desde la carpeta principal de barberia
    echo.
    echo Ubicacion actual: %CD%
    echo Ubicacion esperada: C:\xampp\htdocs\barberia
    echo.
    pause
    exit
)

:: Verificar que XAMPP esté corriendo
echo Verificando servicios de XAMPP...
tasklist | find "httpd.exe" > nul
if errorlevel 1 (
    echo ERROR: Apache no esta corriendo
    echo.
    echo Por favor inicia Apache desde el panel de control de XAMPP
    echo y vuelve a ejecutar este script.
    echo.
    pause
    exit
)

tasklist | find "mysqld.exe" > nul
if errorlevel 1 (
    echo ERROR: MySQL no esta corriendo
    echo.
    echo Por favor inicia MySQL desde el panel de control de XAMPP
    echo y vuelve a ejecutar este script.
    echo.
    pause
    exit
)

echo    Servicios de XAMPP verificados
echo.

:: Iniciar Backend
echo Iniciando Backend (Laravel)...
start "Barberia Pro - Backend" cmd /k "cd /d %~dp0backend && php artisan serve"

echo    Esperando 5 segundos para que el backend inicie...
timeout /t 5 /nobreak > nul

:: Iniciar Frontend
echo Iniciando Frontend (React)...
start "Barberia Pro - Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo ============================================================
echo  SISTEMA INICIADO EXITOSAMENTE
echo ============================================================
echo.
echo SERVICIOS ACTIVOS:
echo - Backend: http://localhost:8000
echo - Frontend: Se abrira en una nueva ventana
echo.
echo INSTRUCCIONES:
echo 1. Espera a que aparezca la URL del frontend (generalmente http://localhost:5173)
echo 2. Abre esa URL en tu navegador
echo 3. Usa las credenciales de prueba para iniciar sesion
echo.
echo CREDENCIALES DE PRUEBA:
echo - Admin:     admin@barberia.com / password
echo - Cliente:   cliente1@test.com / password
echo - Barbero:   barbero01@barberia.com / password
echo - Supervisor: supervisor0@barberia.com / password
echo.
echo NOTA: Ambas ventanas de consola deben permanecer abiertas
echo       para que el sistema funcione correctamente.
echo.
pause