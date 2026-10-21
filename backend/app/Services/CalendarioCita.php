<?php

namespace App\Services;

use App\Models\Cita;
use Carbon\Carbon;

class CalendarioCita
{
    /** Contenido del archivo .ics de la cita. $cancelada genera el aviso de cancelación. */
    public static function ics(Cita $cita, bool $cancelada = false): string
    {
        $cita->loadMissing(['sede', 'barbero', 'servicios']);

        $estado = $cancelada || $cita->estado === 'cancelada'
            ? 'CANCELLED'
            : (in_array($cita->estado, ['pendiente', 'pendiente_validacion']) ? 'TENTATIVE' : 'CONFIRMED');
        $metodo = $estado === 'CANCELLED' ? 'CANCEL' : 'PUBLISH';

        $servicios = $cita->servicios->pluck('nombre')->implode(', ');
        $descripcion = "Barbero: {$cita->barbero->name}\nServicios: {$servicios}\nTotal: $" . number_format((float) $cita->monto_total, 2);
        $ubicacion = trim($cita->sede->nombre . ', ' . $cita->sede->direccion, ', ');

        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Barberia Pro//Reservas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:' . $metodo,
            'BEGIN:VEVENT',
            'UID:cita-' . $cita->id . '@barberia-pro',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'SEQUENCE:' . (int) $cita->secuencia_calendario,
            'DTSTART:' . self::utc($cita->inicio()),
            'DTEND:' . self::utc($cita->fin()),
            'SUMMARY:' . self::texto('Cita en Barbería Pro - ' . $cita->sede->nombre),
            'DESCRIPTION:' . self::texto($descripcion),
            'LOCATION:' . self::texto($ubicacion),
            'STATUS:' . $estado,
            'TRANSP:OPAQUE',
        ];

        if ($estado !== 'CANCELLED') {
            $lineas = array_merge($lineas, [
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:Tu cita en Barbería Pro es en 1 hora',
                'TRIGGER:-PT1H',
                'END:VALARM',
            ]);
        }

        $lineas[] = 'END:VEVENT';
        $lineas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'plegar'], $lineas)) . "\r\n";
    }

    /** Enlace para agregar la cita a Google Calendar con un clic. */
    public static function enlaceGoogle(Cita $cita): string
    {
        $cita->loadMissing(['sede', 'barbero', 'servicios']);

        $servicios = $cita->servicios->pluck('nombre')->implode(', ');

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'Cita en Barbería Pro - ' . $cita->sede->nombre,
            'dates' => self::utc($cita->inicio()) . '/' . self::utc($cita->fin()),
            'details' => "Barbero: {$cita->barbero->name}\nServicios: {$servicios}",
            'location' => trim($cita->sede->nombre . ', ' . $cita->sede->direccion, ', '),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function utc(Carbon $fecha): string
    {
        return $fecha->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function texto(string $valor): string
    {
        $valor = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $valor);

        return str_replace(["\r\n", "\n", "\r"], '\n', $valor);
    }

    /** Las líneas de un .ics no pueden superar 75 bytes: se continúan con un espacio. */
    private static function plegar(string $linea): string
    {
        if (strlen($linea) <= 75) {
            return $linea;
        }

        $partes = [];
        $resto = $linea;
        $limite = 75;

        while (strlen($resto) > $limite) {
            $corte = $limite;
            // No partir un carácter UTF-8 por la mitad.
            while ($corte > 0 && (ord($resto[$corte]) & 0xC0) === 0x80) {
                $corte--;
            }
            $partes[] = substr($resto, 0, $corte);
            $resto = substr($resto, $corte);
            $limite = 74;
        }
        $partes[] = $resto;

        return implode("\r\n ", $partes);
    }
}
