<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Cita</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px;">
            <h1 style="color: #333; text-align: center;">Recordatorio de Cita</h1>
            
            <p>Hola <strong>{{ $clienteNombre }}</strong>,</p>
            
            <p>Este es un recordatorio de que tienes una cita programada en <strong>{{ $tiempo }}</strong>:</p>
            
            <div style="background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p><strong>Sede:</strong> {{ $sedeNombre }}</p>
                <p><strong>Barbero:</strong> {{ $barberoNombre }}</p>
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora:</strong> {{ $hora }}</p>
                <p><strong>Servicios:</strong> {{ $servicios }}</p>
            </div>
            
            <p>Por favor llega 10 minutos antes de tu hora programada.</p>
            
            <p>Si necesitas cancelar o reprogramar tu cita, por favor contáctanos lo antes posible.</p>
            
            <p style="text-align: center; margin-top: 30px;">
                <em>¡Te esperamos en Barbería!</em>
            </p>
        </div>
    </div>
</body>
</html>