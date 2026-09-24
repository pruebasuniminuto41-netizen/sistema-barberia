<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecordatorioCita;

class EnviarRecordatoriosCitas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citas:enviar-recordatorios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios de citas próximas por correo electrónico';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Buscando citas próximas para enviar recordatorios...');

        // Buscar citas confirmadas para las próximas 24 horas
        $citasManana = Cita::with(['cliente', 'barbero', 'sede', 'servicios'])
                            ->where('estado', 'confirmada')
                            ->where('fecha', Carbon::tomorrow()->toDateString())
                            ->get();

        // Buscar citas para dentro de 2 horas
        $citasDosHoras = Cita::with(['cliente', 'barbero', 'sede', 'servicios'])
                            ->where('estado', 'confirmada')
                            ->where('fecha', Carbon::today()->toDateString())
                            ->where('hora_inicio', '>=', Carbon::now()->addHours(2)->format('H:i'))
                            ->where('hora_inicio', '<=', Carbon::now()->addHours(3)->format('H:i'))
                            ->get();

        $totalEnviados = 0;

        // Enviar recordatorios para citas de mañana
        foreach ($citasManana as $cita) {
            try {
                Mail::to($cita->cliente->email)->send(new RecordatorioCita($cita, '24 horas'));
                $this->info("Recordatorio enviado a {$cita->cliente->email} para cita mañana");
                $totalEnviados++;
            } catch (\Exception $e) {
                $this->error("Error enviando recordatorio a {$cita->cliente->email}: " . $e->getMessage());
            }
        }

        // Enviar recordatorios para citas en 2 horas
        foreach ($citasDosHoras as $cita) {
            try {
                Mail::to($cita->cliente->email)->send(new RecordatorioCita($cita, '2 horas'));
                $this->info("Recordatorio enviado a {$cita->cliente->email} para cita en 2 horas");
                $totalEnviados++;
            } catch (\Exception $e) {
                $this->error("Error enviando recordatorio a {$cita->cliente->email}: " . $e->getMessage());
            }
        }

        $this->info("Total de recordatorios enviados: {$totalEnviados}");
        return 0;
    }
}