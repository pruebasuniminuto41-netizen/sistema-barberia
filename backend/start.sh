#!/bin/bash
set -e

echo "Running Laravel migrations..."
php artisan migrate --force

echo "Starting Laravel application..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

