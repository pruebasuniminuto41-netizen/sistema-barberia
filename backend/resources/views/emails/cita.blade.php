<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbería Pro</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
@php
    $titulos = [
        'recibida' => $esCliente ? 'Recibimos tu reserva' : 'Nueva cita asignada',
        'confirmada' => 'Tu cita está confirmada',
        'cancelada' => 'Tu cita fue cancelada',
        'recordatorio_24h' => 'Tu cita es mañana',
        'recordatorio_2h' => 'Tu cita es en 2 horas',
    ];
    $colores = ['recibida' => '#d97706', 'confirmada' => '#16a34a', 'cancelada' => '#dc2626', 'recordatorio_24h' => '#7c3aed', 'recordatorio_2h' => '#7c3aed'];
@endphp
<div style="max-width:560px;margin:0 auto;padding:24px 16px;">
    <div style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:{{ $colores[$tipo] }};padding:20px 24px;color:#ffffff;">
            <div style="font-size:13px;opacity:.9;">Barbería Pro</div>
            <div style="font-size:22px;font-weight:bold;margin-top:4px;">{{ $titulos[$tipo] }}</div>
        </div>

        <div style="padding:24px;line-height:1.55;">
            <p style="margin:0 0 16px;">Hola <strong>{{ $nombre }}</strong>,</p>

            @if($tipo === 'recibida' && $esCliente)
                @if($cita->estado === 'pendiente_validacion')
                    <p style="margin:0 0 16px;">Recibimos tu reserva y tu comprobante de transferencia. Un supervisor lo revisará y, cuando lo valide, te enviaremos otro correo con la confirmación definitiva. Tu horario queda reservado mientras tanto.</p>
                @else
                    <p style="margin:0 0 16px;">Tu reserva quedó registrada y el horario está reservado. Pagas en la barbería el día de tu cita.</p>
                @endif
            @elseif($tipo === 'recibida')
                <p style="margin:0 0 16px;">Se registró una nueva cita en tu agenda.</p>
            @elseif($tipo === 'confirmada')
                <p style="margin:0 0 16px;">¡Listo! Tu cita está confirmada. Te esperamos.</p>
            @elseif($tipo === 'cancelada')
                <p style="margin:0 0 16px;">La siguiente cita fue cancelada y el horario quedó liberado. El archivo adjunto elimina el evento de tu calendario.</p>
            @elseif($tipo === 'recordatorio_24h')
                <p style="margin:0 0 16px;">Te recordamos que mañana tienes una cita con nosotros.</p>
            @else
                <p style="margin:0 0 16px;">Te recordamos que tu cita es en aproximadamente 2 horas.</p>
            @endif

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f9fafb;border-radius:8px;margin:8px 0 20px;">
                <tr><td style="padding:14px 16px;">
                    <div style="margin:4px 0;"><strong>Sede:</strong> {{ $cita->sede->nombre }}@if($cita->sede->direccion) — {{ $cita->sede->direccion }}@endif</div>
                    <div style="margin:4px 0;"><strong>Fecha:</strong> {{ ucfirst($fecha) }}</div>
                    <div style="margin:4px 0;"><strong>Hora:</strong> {{ $hora }}</div>
                    @if($esCliente)
                        <div style="margin:4px 0;"><strong>Barbero:</strong> {{ $cita->barbero->name }}</div>
                    @else
                        <div style="margin:4px 0;"><strong>Cliente:</strong> {{ $cita->cliente->name }}@if($cita->cliente->phone) ({{ $cita->cliente->phone }})@endif</div>
                    @endif
                    <div style="margin:4px 0;"><strong>Servicios:</strong> {{ $servicios }}</div>
                    <div style="margin:4px 0;"><strong>Total:</strong> ${{ number_format((float) $cita->monto_total, 2) }} ({{ $cita->metodo_pago }})</div>
                </td></tr>
            </table>

            @if($enlaceGoogle)
                <p style="margin:0 0 8px;text-align:center;">
                    <a href="{{ $enlaceGoogle }}" style="display:inline-block;background:#7c3aed;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;">Agregar a Google Calendar</a>
                </p>
                <p style="margin:0 0 16px;text-align:center;font-size:13px;color:#6b7280;">También puedes abrir el archivo adjunto <strong>.ics</strong> para Apple, Outlook u otro calendario.</p>
            @endif

            @if($esCliente && in_array($tipo, ['recibida', 'confirmada', 'recordatorio_24h', 'recordatorio_2h']))
                <p style="margin:0 0 8px;">Por favor llega 10 minutos antes de tu hora. Si necesitas cancelar, hazlo desde tu cuenta con al menos {{ config('barberia.horas_min_cancelacion') }} horas de anticipación.</p>
            @endif

            @if($urlApp)
                <p style="margin:16px 0 0;text-align:center;font-size:13px;"><a href="{{ $urlApp }}" style="color:#7c3aed;">Ir a Barbería Pro</a></p>
            @endif
        </div>
    </div>
    <p style="text-align:center;font-size:12px;color:#9ca3af;margin:16px 0 0;">Este es un mensaje automático de Barbería Pro.</p>
</div>
</body>
</html>
