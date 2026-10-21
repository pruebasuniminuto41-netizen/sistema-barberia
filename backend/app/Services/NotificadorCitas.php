<?php

namespace App\Services;

use App\Mail\CitaNotificacion;
use App\Models\Cita;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificadorCitas
{
    /**
     * Envía el correo correspondiente. Nunca lanza excepciones: un fallo de correo
     * no debe impedir que la reserva o el cambio de estado se guarden.
     */
    public static function enviar(Cita $cita, string $tipo, bool $avisarBarbero = false): bool
    {
        try {
            // Cada cambio de estado actualiza el evento que el cliente ya tiene en su calendario.
            if (in_array($tipo, ['confirmada', 'cancelada'])) {
                $cita->increment('secuencia_calendario');
            }

            $cita->load(['cliente', 'barbero', 'sede', 'servicios']);

            $esRecordatorio = in_array($tipo, ['recordatorio_24h', 'recordatorio_2h']);
            $copia = config('barberia.correo_negocio');

            if (!self::esCuentaDeEjemplo($cita->cliente->email)) {
                $mensajeCliente = Mail::to($cita->cliente->email);
                if ($copia && !$esRecordatorio) {
                    $mensajeCliente->bcc($copia);
                }
                $mensajeCliente->send(new CitaNotificacion($cita, $tipo, 'cliente'));
            }

            // El barbero se entera de las citas nuevas y de las canceladas.
            $tipoBarbero = in_array($tipo, ['recibida', 'cancelada']) ? $tipo : ($avisarBarbero ? 'recibida' : null);
            if ($tipoBarbero && $cita->barbero && $cita->barbero->email && !self::esCuentaDeEjemplo($cita->barbero->email)) {
                Mail::to($cita->barbero->email)->send(new CitaNotificacion($cita, $tipoBarbero, 'barbero'));
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("No se pudo enviar el correo '{$tipo}' de la cita {$cita->id}: " . $e->getMessage());

            return false;
        }
    }

    /** Las cuentas de demostración usan dominios reales de terceros: no se les escribe. */
    private static function esCuentaDeEjemplo(string $correo): bool
    {
        $dominio = strtolower(substr(strrchr($correo, '@') ?: '', 1));

        return $dominio !== '' && in_array($dominio, array_map('strtolower', config('barberia.dominios_demo', [])), true);
    }
}
