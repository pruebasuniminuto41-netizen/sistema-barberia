#!/bin/sh
# Arranque del contenedor en Render. Define $PORT.
set -e

sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:clear
php artisan migrate --force
# Siembra solo si la base esta vacia (el seeder es idempotente)
php artisan db:seed --force || true

chown -R www-data:www-data storage bootstrap/cache

# Programador de tareas: ejecuta los recordatorios de citas por correo cada 10 minutos
(
  while true; do
    runuser -u www-data -- php artisan schedule:run >/dev/null 2>&1 || true
    sleep 60
  done
) &

exec apache2-foreground
