# INSTALACIÓN MANUAL - SISTEMA DE BARBERÍA

Este documento proporciona instrucciones paso a paso para instalar el sistema manualmente si el script automático falla.

## REQUISITOS PREVIOS

Antes de comenzar, asegúrate de tener instalado:

1. **XAMPP** (Apache + MySQL)
   - Descargar: https://www.apachefriends.org/
   - Instalar en `C:\xampp`
   - Iniciar Apache y MySQL desde el panel de control

2. **PHP** (incluido en XAMPP)
   - Verificar: Abre CMD y ejecuta `php --version`
   - Si no funciona, agrega `C:\xampp\php` al PATH del sistema

3. **Composer**
   - Descargar: https://getcomposer.org/download/
   - Instalar y verificar: `composer --version`

4. **Node.js**
   - Descargar: https://nodejs.org/
   - Instalar versión LTS
   - Verificar: `node --version` y `npm --version`

## PASO 1: COPIAR ARCHIVOS

1. Copia la carpeta `barberia` completa a:
   ```
   C:\xampp\htdocs\barberia
   ```

## PASO 2: CONFIGURAR BACKEND (LARAVEL)

1. Abre CMD y navega al backend:
   ```cmd
   cd C:\xampp\htdocs\barberia\backend
   ```

2. Instala dependencias de Laravel:
   ```cmd
   composer install
   ```

3. Configura el archivo `.env`:
   ```cmd
   copy .env.example .env
   php artisan key:generate
   ```

4. Edita el archivo `.env` y configura la base de datos:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=barberia
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   *Nota: Si tu MySQL tiene contraseña, cámbiala en `DB_PASSWORD`*

5. Crea la base de datos:
   - Abre phpMyAdmin: http://localhost/phpmyadmin
   - Crea una base de datos llamada `barberia`
   - O ejecuta en CMD:
     ```cmd
     mysql -u root -e "CREATE DATABASE IF NOT EXISTS barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
     ```

6. Ejecuta migraciones y seeders:
   ```cmd
   php artisan migrate --seed
   ```

## PASO 3: CONFIGURAR FRONTEND (REACT)

1. Abre CMD y navega al frontend:
   ```cmd
   cd C:\xampp\htdocs\barberia\frontend
   ```

2. Instala dependencias de React:
   ```cmd
   npm install
   ```

3. Instala configuración de Tailwind CSS:
   ```cmd
   npm install @tailwindcss/postcss
   ```

## PASO 4: INICIAR EL SISTEMA

### Opción A: Usar el script INICIAR.bat

1. Ve a `C:\xampp\htdocs\barberia`
2. Doble clic en `INICIAR.bat`
3. Espera a que se abran las ventanas de consola

### Opción B: Iniciar manualmente

**Terminal 1 - Backend:**
```cmd
cd C:\xampp\htdocs\barberia\backend
php artisan serve
```

**Terminal 2 - Frontend:**
```cmd
cd C:\xampp\htdocs\barberia\frontend
npm run dev
```

## PASO 5: ACCEDER AL SISTEMA

1. Abre tu navegador
2. Ve a la URL que muestra el frontend (generalmente `http://localhost:5173`)
3. Usa las credenciales de prueba:
   - **Admin**: `admin@barberia.com` / `password`
   - **Cliente**: `cliente1@test.com` / `password`
   - **Barbero**: `barbero01@barberia.com` / `password`
   - **Supervisor**: `supervisor0@barberia.com` / `password`

## SOLUCIÓN DE PROBLEMAS

### Error: "php no reconocido"
- Agrega `C:\xampp\php` al PATH del sistema
- O usa la ruta completa: `C:\xampp\php\php`

### Error: "composer no reconocido"
- Reinstala Composer desde https://getcomposer.org/download/
- Reinicia CMD después de la instalación

### Error: "node no reconocido"
- Reinstala Node.js desde https://nodejs.org/
- Reinicia CMD después de la instalación

### Error de conexión a base de datos
- Verifica que MySQL esté corriendo en XAMPP
- Verifica las credenciales en `.env`
- Verifica que la base de datos `barberia` exista

### Error de Tailwind CSS
- Ejecuta: `npm install @tailwindcss/postcss`
- Verifica que `postcss.config.js` tenga la configuración correcta

### Puerto ya en uso
- Cambia el puerto en `php artisan serve --port=8001`
- Cambia el puerto en `vite.config.ts`

## VERIFICACIÓN

Verifica que todo funcione correctamente:

1. **Backend**: http://localhost:8000 debería mostrar redirección al frontend
2. **Frontend**: http://localhost:5173 debería mostrar la página de login
3. **Base de datos**: Revisa en phpMyAdmin que las tablas existan
4. **Login**: Prueba iniciar sesión con las credenciales de prueba

## LIMPIEZA (si necesitas reinstalar)

Si necesitas reinstalar desde cero:

1. **Base de datos**:
   - Elimina la base de datos `barberia` en phpMyAdmin
   - O ejecuta: `mysql -u root -e "DROP DATABASE IF EXISTS barberia;"`

2. **Backend**:
   ```cmd
   cd C:\xampp\htdocs\barberia\backend
   rmdir /s /q vendor
   del .env
   ```

3. **Frontend**:
   ```cmd
   cd C:\xampp\htdocs\barberia\frontend
   rmdir /s /q node_modules
   del package-lock.json
   ```

4. **Repite el proceso de instalación**

## SOPORTE

Si encuentras problemas no documentados aquí:

1. Revisa los logs de Laravel: `backend\storage\logs\laravel.log`
2. Revisa la consola del navegador para errores de JavaScript
3. Verifica que todos los servicios estén corriendo (Apache, MySQL)
4. Asegúrate de tener las versiones correctas de las dependencias