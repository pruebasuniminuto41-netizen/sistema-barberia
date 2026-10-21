<?php

return [

    /*
    | Horario de atención (hora local de la aplicación, formato H:i).
    | Se pueden cambiar con las variables de entorno sin tocar el código.
    */
    'hora_apertura' => env('BARBERIA_HORA_APERTURA', '08:00'),
    'hora_cierre' => env('BARBERIA_HORA_CIERRE', '20:00'),

    // Cada cuántos minutos se ofrecen horarios de inicio al reservar.
    'intervalo_minutos' => (int) env('BARBERIA_INTERVALO_MINUTOS', 30),

    // Horas mínimas de anticipación para que un cliente cancele su cita.
    'horas_min_cancelacion' => (int) env('BARBERIA_HORAS_MIN_CANCELACION', 2),

    // Destinatario de copia de las reservas (opcional).
    'correo_negocio' => env('BARBERIA_CORREO_NEGOCIO'),

    // Dirección pública del frontend (se usa en los correos).
    'url_frontend' => env('FRONTEND_URL', ''),
];
