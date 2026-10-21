<?php

namespace App\Console\Commands;

use App\Models\Cita;
use App\Services\NotificadorCitas;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatoriosCitas extends Command
{
    protected $signature = 'citas:enviar-recordatorios';

    protected $description = 'Envía recordatorios por correo de las citas confirmadas (24 horas y 2 horas antes)';

    public function handle()
    {
        $this->info('Buscando citas próximas para enviar recordatorios...');

        $ahora = Carbon::now(config('app.timezone'));

        $citas = Cita::with(['cliente', 'barbero', 'sede', 'servicios'])
            ->where('estado', 'confirmada')
            ->whereBetween('fecha', [$ahora->toDateString(), $ahora->copy()->addDay()->toDateString()])
            ->get();

        $enviados = 0;

        foreach ($citas as $cita) {
            $minutos = $ahora->diffInMinutes($cita->inicio(), false);
            if ($minutos <= 0 || $minutos > 1440) {
                continue;
            }

            // Minutos entre que se hizo la reserva y la cita: no se recuerda lo que acaba de reservarse.
            $anticipacion = $cita->created_at ? $cita->created_at->diffInMinutes($cita->inicio(), false) : PHP_INT_MAX;

            if ($minutos <= 120) {
                if ($cita->recordatorio_2h_enviado_at) {
                    continue;
                }
                $cita->recordatorio_2h_enviado_at = $ahora;
                // Si ya estamos dentro de las 2 horas, el aviso de 24 horas ya no tiene sentido.
                $cita->recordatorio_24h_enviado_at = $cita->recordatorio_24h_enviado_at ?: $ahora;
                $cita->save();

                if ($anticipacion > 180 && NotificadorCitas::enviar($cita, 'recordatorio_2h')) {
                    $enviados++;
                    $this->info("Recordatorio 2h enviado a {$cita->cliente->email} (cita {$cita->id})");
                }
            } else {
                if ($cita->recordatorio_24h_enviado_at) {
                    continue;
                }
                $cita->recordatorio_24h_enviado_at = $ahora;
                $cita->save();

                if ($anticipacion > 1440 && NotificadorCitas::enviar($cita, 'recordatorio_24h')) {
                    $enviados++;
                    $this->info("Recordatorio 24h enviado a {$cita->cliente->email} (cita {$cita->id})");
                }
            }
        }

        $this->info("Total de recordatorios enviados: {$enviados}");

        return 0;
    }
}
