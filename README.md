# Sistema de Reservas para Barbería

Sistema web completo de reservas y gestión integral para una cadena de barberías con múltiples sedes.

## Características

- **Sistema de Roles**: Cliente, Barbero, Supervisor (por sede), Administrador
- **Reservas de Citas**: Selección de sede, barbero, servicios, fecha y hora
- **Anti-solapamiento**: Prevención automática de reservas duplicadas
- **Pagos**: Efectivo y transferencia con validación de comprobantes
- **Punto de Venta (POS)**: Agregado de productos por supervisores
- **Notificaciones**: Recordatorios automáticos por correo electrónico
- **Reportes**: Métricas financieras y de operación
- **Autenticación**: JWT tokens con Laravel Sanctum

## Stack Tecnológico

- **Backend**: Laravel 8 + MySQL
- **Frontend**: React + TypeScript + Tailwind CSS
- **Autenticación**: Laravel Sanctum
- **Permisos**: Spatie Laravel Permission
- **Programación**: Laravel Cron Jobs

## Estructura del Proyecto

```
barberia/
├── backend/              # API Laravel
│   ├── app/
│   │   ├── Console/Commands/    # Comandos programados
│   │   ├── Http/Controllers/Api/
│   │   ├── Mail/                 # Correos electrónicos
│   │   └── Models/              # Modelos Eloquent
│   ├── database/
│   │   ├── migrations/          # Migraciones de BD
│   │   └── seeders/            # Datos de prueba
│   └── resources/views/emails/  # Plantillas de correo
└── frontend/             # React App
    ├── src/
    │   ├── components/          # Componentes por rol
    │   ├── context/            # Contextos de React
    │   └── services/           # Servicios API
    └── public/                # Archivos estáticos
```

## Instalación

### Requisitos Previos

- PHP 7.3 o superior
- Composer
- Node.js 14 o superior
- MySQL
- XAMPP (para Windows) o servidor web equivalente

### Backend (Laravel)

1. **Navegar al directorio backend:**
   ```bash
   cd C:\xampp\htdocs\barberia\backend
   ```

2. **Instalar dependencias:**
   ```bash
   composer install
   ```

3. **Configurar archivo .env:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurar base de datos en .env:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=barberia
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Crear base de datos:**
   ```sql
   CREATE DATABASE barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

6. **Ejecutar migraciones y seeders:**
   ```bash
   php artisan migrate --seed
   ```

7. **Iniciar servidor de desarrollo:**
   ```bash
   php artisan serve
   ```
   El API estará disponible en `http://localhost:8000`

### Frontend (React)

1. **Navegar al directorio frontend:**
   ```bash
   cd C:\xampp\htdocs\barberia\frontend
   ```

2. **Instalar dependencias:**
   ```bash
   npm install
   ```

3. **Iniciar servidor de desarrollo:**
   ```bash
   npm run dev
   ```
   La aplicación estará disponible en la URL que se muestre (generalmente `http://localhost:5173`)

## Configuración de Cron Jobs

Para que funcionen los recordatorios automáticos, configura el cron job:

### Windows (Task Scheduler)

1. Abre el Programador de tareas de Windows
2. Crea una tarea básica que se ejecute cada hora
3. Acción: `php C:\xampp\htdocs\barberia\backend\artisan schedule:run`

### Linux/Mac

Agrega esta línea a tu crontab:
```bash
* * * * * cd /path-to-barberia/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Credenciales de Prueba

Después de ejecutar los seeders, tendrás estos usuarios disponibles:

### Administrador
- Email: `admin@barberia.com`
- Password: `password`

### Supervisores (1 por sede)
- Email: `supervisor0@barberia.com` a `supervisor3@barberia.com`
- Password: `password`

### Barberos (2 por sede)
- Email: `barbero01@barberia.com` a `barbero31@barberia.com`
- Password: `password`

### Clientes de prueba
- Email: `cliente1@test.com` a `cliente5@test.com`
- Password: `password`

## Funcionalidades por Rol

### Cliente
- Registro público autónomo
- Reserva de citas con selección de sede, barbero y servicios
- Selección de método de pago (efectivo/transferencia)
- Subida de comprobantes para transferencias
- Visualización de historial de citas

### Barbero
- Vista de agenda personal filtrada por sede
- Actualización de estados de citas (en proceso, completada)
- Información detallada de cada cita

### Supervisor
- Panel exclusivo para su sede asignada
- Control global de citas del día
- Validación de comprobantes de pago
- Sistema POS para agregar productos a citas
- Gestión de cobros

### Administrador
- Control total del sistema y las 4 sedes
- Gestión de usuarios (supervisores, barberos)
- Reportes financieros y métricas
- Gestión de catálogo de servicios y productos
- Validación de comprobantes

## API Endpoints

### Autenticación
- `POST /api/register` - Registro de cliente
- `POST /api/login` - Inicio de sesión
- `POST /api/logout` - Cierre de sesión
- `GET /api/me` - Información del usuario actual

### Sedes
- `GET /api/sedes` - Listar sedes
- `GET /api/sedes/{id}` - Ver sede específica
- `GET /api/sedes/{id}/barberos` - Listar barberos de sede
- `POST /api/sedes` - Crear sede (admin)
- `PUT /api/sedes/{id}` - Actualizar sede (admin)
- `DELETE /api/sedes/{id}` - Eliminar sede (admin)

### Servicios
- `GET /api/servicios` - Listar servicios
- `GET /api/servicios/{id}` - Ver servicio específico
- `POST /api/servicios` - Crear servicio (admin)
- `PUT /api/servicios/{id}` - Actualizar servicio (admin)
- `DELETE /api/servicios/{id}` - Eliminar servicio (admin)

### Productos
- `GET /api/productos` - Listar productos
- `GET /api/productos/{id}` - Ver producto específico
- `POST /api/productos` - Crear producto (admin/supervisor)
- `PUT /api/productos/{id}` - Actualizar producto (admin/supervisor)
- `DELETE /api/productos/{id}` - Eliminar producto (admin/supervisor)

### Citas
- `GET /api/citas` - Listar citas (filtrado por rol)
- `GET /api/citas/{id}` - Ver cita específica
- `POST /api/citas` - Crear cita
- `PUT /api/citas/{id}` - Actualizar cita
- `POST /api/citas/{id}/productos` - Agregar productos (supervisor)
- `POST /api/citas/{id}/validar-comprobante` - Validar comprobante (supervisor/admin)

### Admin
- `GET /api/admin/metricas` - Obtener métricas y reportes
- `GET /api/users` - Listar usuarios (admin)
- `POST /api/users` - Crear usuario (admin)
- `PUT /api/users/{id}` - Actualizar usuario (admin)
- `DELETE /api/users/{id}` - Eliminar usuario (admin)

## Desarrollo

### Backend
- Ejecutar tests: `php artisan test`
- Ver rutas: `php artisan route:list`
- Limpiar caché: `php artisan cache:clear`

### Frontend
- Build para producción: `npm run build`
- Preview de producción: `npm run preview`

## Solución de Problemas

### Error de conexión a base de datos
- Verifica que MySQL esté corriendo
- Confirma las credenciales en `.env`
- Asegúrate de que la base de datos exista

### Error de CORS
- Verifica la configuración en `config/cors.php`
- Confirma que el frontend esté en los orígenes permitidos

### Cron jobs no funcionan
- Verifica que el comando funcione manualmente
- Revisa los logs de Laravel en `storage/logs`
- Confirma la configuración de permisos

## Licencia

Este proyecto es para fines educativos y de demostración.

## Soporte

Para problemas o preguntas, consulta la documentación de Laravel y React.