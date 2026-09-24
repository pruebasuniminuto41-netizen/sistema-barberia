# GUÍA DE INSTALACIÓN PARA UNIVERSIDAD

## INSTRUCCIONES PARA INSTALAR EN CUALQUIER COMPUTADORA

Esta guía está diseñada para que el sistema funcione en cualquier computadora de la universidad con un solo paso.

### MÉTODO AUTOMÁTICO (RECOMENDADO)

#### PASO 1: Copiar Archivos
1. Copia la carpeta completa `barberia` a:
   ```
   C:\xampp\htdocs\barberia
   ```

#### PASO 2: Ejecutar Instalador Automático
1. Navega a `C:\xampp\htdocs\barberia`
2. Doble clic en `INSTALAR.bat`
3. El script verificará automáticamente:
   - ✅ XAMPP instalado y funcionando
   - ✅ Apache corriendo
   - ✅ MySQL corriendo
   - ✅ PHP disponible
   - ✅ Composer instalado
   - ✅ Node.js instalado
   - ✅ npm disponible

4. Si falta algún requisito, el script te mostrará:
   - Qué falta instalar
   - Dónde descargarlo
   - Cómo instalarlo

5. Si todo está correcto, instalará automáticamente:
   - Dependencias de Laravel
   - Base de datos con datos de prueba
   - Dependencias de React
   - Configuración de Tailwind CSS

#### PASO 3: Iniciar Sistema
1. Doble clic en `INICIAR.bat`
2. Espera a que se abran dos ventanas de consola
3. Abre la URL que aparece (generalmente `http://localhost:5173`)

### REQUISITOS DEL SISTEMA

El instalador verificará estos requisitos:

1. **XAMPP** (obligatorio)
   - Descargar: https://www.apachefriends.org/
   - Versión recomendada: PHP 7.4 o superior
   - Instalar en `C:\xampp` (por defecto)

2. **Composer** (obligatorio)
   - Descargar: https://getcomposer.org/download/
   - Versión recomendada: 2.x o superior
   - Instalar como ejecutable global

3. **Node.js** (obligatorio)
   - Descargar: https://nodejs.org/
   - Versión recomendada: LTS (Long Term Support)
   - Instalar con opciones por defecto

### CREDENCIALES DE ACCESO

Una vez instalado, usa estas credenciales:

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | admin@barberia.com | password |
| Cliente | cliente1@test.com | password |
| Barbero | barbero01@barberia.com | password |
| Supervisor | supervisor0@barberia.com | password |

### FUNCIONALIDADES DEL SISTEMA

**Para Clientes:**
- Reserva de citas con selección de sede, barbero y servicios
- Sistema de pagos (efectivo/transferencia)
- Subida de comprobantes
- Historial de citas

**Para Barberos:**
- Vista de agenda personal
- Actualización de estados de citas
- Información detallada de clientes

**Para Supervisores:**
- Control de citas de su sede
- Validación de comprobantes
- Sistema POS para productos
- Gestión de cobros

**Para Administradores:**
- Control total del sistema
- Gestión de usuarios y sedes
- Reportes financieros
- Métricas en tiempo real

### DISEÑO PROFESIONAL

El sistema cuenta con:
- 🎨 Diseño moderno con glassmorphism
- 🌈 Gradientes elegantes (púrpura/azul)
- ✨ Animaciones suaves
- 📱 Interfaz responsiva
- 🎯 Navegación intuitiva
- 💎 Efectos de transparencia y blur

### SOLUCIÓN DE PROBLEMAS

Si el instalador automático falla:

1. **Abre `INSTALACION_MANUAL.md`** para instrucciones paso a paso
2. **Verifica que XAMPP esté funcionando**:
   - Abre el Panel de Control de XAMPP
   - Inicia Apache y MySQL
   - Verifica que ambos estén en verde

3. **Verifica las dependencias**:
   - Abre CMD y ejecuta: `php --version`
   - Abre CMD y ejecuta: `composer --version`
   - Abre CMD y ejecuta: `node --version`

4. **Si falta alguna dependencia**:
   - El instalador te indicará exactamente qué instalar
   - Sigue las instrucciones que aparezcan en pantalla

### CARACTERÍSTICAS TÉCNICAS

- **Backend**: Laravel 8 + MySQL
- **Frontend**: React + TypeScript + Tailwind CSS
- **Autenticación**: Laravel Sanctum
- **Permisos**: Spatie Laravel Permission
- **Arquitectura**: API REST + SPA

### TIEMPO DE INSTALACIÓN

- Con todos los requisitos instalados: ~5-10 minutos
- Sin requisitos instalados: ~20-30 minutos (incluye instalación de dependencias)

### ESPACIO REQUERIDO

- **Mínimo**: 500 MB para el proyecto
- **Recomendado**: 2 GB para dependencias

### NOTAS PARA LA UNIVERSIDAD

1. **Privilegios de Administrador**: Es posible que necesites privilegios de administrador para instalar XAMPP, Composer o Node.js

2. **Conexión a Internet**: Necesaria para descargar dependencias la primera vez

3. **Firewall**: Es posible que el firewall bloquee los puertos 8000 y 5173. Si el sistema no carga, verifica el firewall

4. **Antivirus**: Algunos antivirus pueden bloquear scripts .bat. Si INSTALAR.bat no ejecuta, desactiva temporalmente el antivirus

### VERIFICACIÓN FINAL

Después de la instalación, verifica:

1. ✅ Backend funcionando: http://localhost:8000
2. ✅ Frontend funcionando: http://localhost:5173
3. ✅ Base de datos creada: Revisa en phpMyAdmin
4. ✅ Login funcional: Prueba con credenciales de prueba

### SOPORTE ADICIONAL

Si encuentras problemas no documentados:

1. Revisa el archivo `INSTALACION_MANUAL.md`
2. Revisa los logs en `backend\storage\logs\laravel.log`
3. Verifica la consola del navegador para errores de JavaScript

---

**EL SISTEMA ESTÁ LISTO PARA FUNCIONAR EN CUALQUIER COMPUTADORA CON XAMPP, COMPOSER Y NODE.JS INSTALADOS.**