# GUÍA DE DEMOSTRACIÓN PARA CLIENTES

## 🎯 ESCENARIO DE DEMOSTRACIÓN COMPLETO

Esta guía te permite mostrar el sistema completo a un cliente potencial con datos realistas.

---

## 📋 DATOS DE PRUEBA DISPONIBLES

### 👥 USUARIOS DE PRUEBA

#### Administrador General
- **Email**: `admin@barberia.com`
- **Password**: `password`
- **Acceso**: Control total del sistema

#### Supervisores (1 por sede)
- **Email**: `supervisor0@barberia.com` a `supervisor3@barberia.com`
- **Password**: `password`
- **Acceso**: Panel de su sede específica

#### Barberos (2 por sede)
- **Email**: `barbero01@barberia.com` a `barbero31@barberia.com`
- **Password**: `password`
- **Acceso**: Agenda personal

#### Clientes de prueba
- **Email**: `cliente1@test.com` a `cliente5@test.com`
- **Password**: `password`
- **Acceso**: Reserva de citas

---

## 🏢 SEDES DISPONIBLES

1. **Barbería Centro** - Calle Principal #123, Centro
2. **Barbería Norte** - Avenida Norte #456, Zona Norte
3. **Barbería Sur** - Calle Sur #789, Zona Sur
4. **Barbería Este** - Boulevard Este #321, Zona Este

---

## 💇 SERVICIOS DISPONIBLES (10 servicios)

| Servicio | Precio | Duración |
|----------|--------|----------|
| Corte Clásico | $15.00 | 30 min |
| Corte Fade | $20.00 | 45 min |
| Barba Completa | $12.00 | 25 min |
| Corte + Barba | $25.00 | 60 min |
| Lavado y Estilo | $10.00 | 20 min |
| Coloración | $40.00 | 90 min |
| Corte Infantil | $12.00 | 25 min |
| Dreadlocks Maintenance | $35.00 | 75 min |
| Perfilado de Cejas | $8.00 | 15 min |
| Tratamiento Capilar | $30.00 | 45 min |

---

## 🛍️ PRODUCTOS DISPONIBLES (10 productos)

| Producto | Precio | Stock |
|----------|--------|-------|
| Cerveza Importada | $5.00 | 50 |
| Refresco | $2.00 | 100 |
| Agua Mineral | $1.50 | 100 |
| Pomada Barber | $15.00 | 30 |
| Aceite para Barba | $12.00 | 25 |
| Gel Fijador | $8.00 | 40 |
| Shampoo Premium | $18.00 | 20 |
| Acondicionador | $16.00 | 20 |
| Cera Modelo | $14.00 | 35 |
| Spray Fijador | $10.00 | 45 |

---

## 📅 CITAS DE EJEMPLO CREADAS

El sistema incluye automáticamente 10 citas de ejemplo con diferentes estados:

### Citas Completadas (Histórico) - 3 citas
- Muestran el historial de citas pasadas
- Demostración de reportes financieros

### Citas Canceladas - 1 cita
- Muestra el manejo de cancelaciones

### Citas en Proceso - 1 cita
- Demostración de workflow activo

### Citas Confirmadas - 2 citas
- Citas futuras ya confirmadas

### Citas Pendientes - 3 citas
- Incluye 1 con validación de comprobante pendiente

---

## 🎬 FLUJO DE DEMOSTRACIÓN SUGERIDO

### Paso 1: Mostrar Sistema de Roles (5 min)
1. **Login como Admin**: `admin@barberia.com` / `password`
2. Mostrar dashboard con métricas
3. Mostrar gestión de usuarios, sedes, servicios, productos
4. Crear un nuevo servicio o producto como ejemplo

### Paso 2: Demostrar Reserva de Citas (5 min)
1. **Logout** y **Login como Cliente**: `cliente1@test.com` / `password`
2. Mostrar historial de citas existentes
3. Crear una nueva cita:
   - Seleccionar sede: "Barbería Centro"
   - Seleccionar barbero: "Barbero 1 - Barbería Centro"
   - Seleccionar servicios: "Corte Clásico" + "Barba Completa"
   - Fecha: mañana
   - Hora: 10:00
   - Método de pago: Efectivo
4. Mostrar confirmación

### Paso 3: Demostrar Validación de Pagos (3 min)
1. **Logout** y **Login como Supervisor**: `supervisor0@barberia.com` / `password`
2. Mostrar panel de la sede
3. Validar un comprobante de transferencia pendiente
4. Agregar productos a una cita existente

### Paso 4: Demostrar Gestión de Barberos (3 min)
1. **Logout** y **Login como Barbero**: `barbero01@barberia.com` / `password`
2. Mostrar agenda personal
3. Cambiar estado de una cita: "pendiente" → "en_proceso" → "completada"

### Paso 5: Mostrar Reportes y Métricas (4 min)
1. **Login como Admin** nuevamente
2. Mostrar dashboard con:
   - Ingresos por sede
   - Ingresos por barbero
   - Productos más vendidos
   - Volumen total de citas

---

## 💡 PUNTOS CLAVE A DESTACAR

### ✅ Características Principales
- **Sistema de roles completo**: 4 roles con permisos específicos
- **Anti-solapamiento**: Prevención automática de reservas duplicadas
- **Pagos dual**: Efectivo y transferencia con validación
- **Sistema POS**: Venta de productos durante citas
- **Dashboard administrativo**: Métricas en tiempo real
- **Diseño profesional**: Glassmorphism moderno

### ✅ Beneficios Comerciales
- **Gestión multi-sede**: Control de múltiples ubicaciones
- **Optimización de agenda**: Maximizar productividad de barberos
- **Control financiero**: Reportes detallados de ingresos
- **Experiencia cliente**: Reserva fácil y transparente
- **Escalabilidad**: Sistema preparado para crecimiento

---

## 🚀 INSTALACIÓN RÁPIDA PARA DEMO

1. Copiar carpeta `barberia` a `C:\xampp\htdocs\`
2. Doble clic en `INSTALAR.bat`
3. Doble clic en `INICIAR.bat`
4. Abrir URL en navegador (generalmente `http://localhost:5173`)

**Tiempo total de instalación**: 5-10 minutos

---

## 📞 PREGUNTAS FRECUENTES PARA DEMO

**P: ¿Puedo agregar más sedes?**
R: Sí, desde el panel de admin puedes crear sedes ilimitadas.

**P: ¿Puedo personalizar los servicios y precios?**
R: Sí, todos los servicios y productos son editables.

**P: ¿Funciona para cadenas de barberías?**
R: Sí, está diseñado específicamente para multi-sede.

**P: ¿Puedo ver reportes financieros?**
R: Sí, el dashboard muestra ingresos por sede, barbero y productos.

**P: ¿Los clientes pueden reservar desde celular?**
R: Sí, el diseño es responsive y funciona en móviles.

---

## 🎯 CONCLUSIÓN

Este sistema está **100% listo para producción** y puede manejar:
- Múltiples sedes simultáneamente
- Alto volumen de citas
- Gestión completa de inventario
- Reportes financieros detallados
- Escalabilidad comercial

**Demostrar con confianza: Es un producto profesional y completo.**