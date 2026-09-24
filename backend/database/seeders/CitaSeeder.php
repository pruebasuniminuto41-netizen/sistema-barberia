<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cita;
use App\Models\User;
use App\Models\Sede;
use App\Models\Servicio;
use Carbon\Carbon;

class CitaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Obtener datos de referencia
        $clientes = User::whereHas('roles', function($q) {
            $q->where('name', 'cliente');
        })->take(5)->get();

        $barberos = User::whereHas('roles', function($q) {
            $q->where('name', 'barbero');
        })->get();

        $sedes = Sede::all();
        $servicios = Servicio::all();

        if ($clientes->isEmpty() || $barberos->isEmpty() || $sedes->isEmpty()) {
            $this->command->warn('No hay suficientes usuarios o sedes para crear citas de ejemplo');
            return;
        }

        // Crear citas de ejemplo con diferentes estados
        $estados = ['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada'];
        $metodosPago = ['efectivo', 'transferencia'];

        $citasEjemplo = [
            // Citas completadas (histórico)
            [
                'estado' => 'completada',
                'fecha' => Carbon::now()->subDays(5)->toDateString(),
                'hora_inicio' => '10:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Corte Clásico', 'Barba Completa'],
            ],
            [
                'estado' => 'completada',
                'fecha' => Carbon::now()->subDays(3)->toDateString(),
                'hora_inicio' => '14:30',
                'metodo_pago' => 'transferencia',
                'servicios' => ['Corte Fade'],
            ],
            [
                'estado' => 'completada',
                'fecha' => Carbon::now()->subDays(1)->toDateString(),
                'hora_inicio' => '09:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Corte + Barba', 'Lavado y Estilo'],
            ],
            
            // Citas canceladas
            [
                'estado' => 'cancelada',
                'fecha' => Carbon::now()->subDays(2)->toDateString(),
                'hora_inicio' => '16:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Coloración'],
            ],
            
            // Citas en proceso
            [
                'estado' => 'en_proceso',
                'fecha' => Carbon::now()->toDateString(),
                'hora_inicio' => '11:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Corte Fade', 'Perfilado de Cejas'],
            ],
            
            // Citas confirmadas (futuras cercanas)
            [
                'estado' => 'confirmada',
                'fecha' => Carbon::now()->addDays(1)->toDateString(),
                'hora_inicio' => '10:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Corte Clásico'],
            ],
            [
                'estado' => 'confirmada',
                'fecha' => Carbon::now()->addDays(2)->toDateString(),
                'hora_inicio' => '15:30',
                'metodo_pago' => 'transferencia',
                'servicios' => ['Corte + Barba'],
            ],
            
            // Citas pendientes (futuras)
            [
                'estado' => 'pendiente',
                'fecha' => Carbon::now()->addDays(3)->toDateString(),
                'hora_inicio' => '09:00',
                'metodo_pago' => 'efectivo',
                'servicios' => ['Barba Completa', 'Lavado y Estilo'],
            ],
            [
                'estado' => 'pendiente',
                'fecha' => Carbon::now()->addDays(4)->toDateString(),
                'hora_inicio' => '14:00',
                'metodo_pago' => 'transferencia',
                'servicios' => ['Corte Infantil'],
            ],
            [
                'estado' => 'pendiente_validacion',
                'fecha' => Carbon::now()->addDays(5)->toDateString(),
                'hora_inicio' => '11:30',
                'metodo_pago' => 'transferencia',
                'servicios' => ['Dreadlocks Maintenance'],
            ],
        ];

        foreach ($citasEjemplo as $index => $citaData) {
            $cliente = $clientes[$index % $clientes->count()];
            $barbero = $barberos[$index % $barberos->count()];
            $sede = $sedes[$index % $sedes->count()];

            // Obtener servicios de la cita
            $serviciosCita = $servicios->whereIn('nombre', $citaData['servicios']);
            
            if ($serviciosCita->isEmpty()) {
                continue;
            }

            // Calcular duración total y hora fin
            $duracionTotal = $serviciosCita->sum('duracion_minutos');
            $horaInicio = Carbon::parse($citaData['hora_inicio']);
            $horaFin = $horaInicio->copy()->addMinutes($duracionTotal)->format('H:i');

            // Calcular montos
            $montoServicios = $serviciosCita->sum('precio');

            // Crear cita
            $cita = Cita::create([
                'cliente_id' => $cliente->id,
                'barbero_id' => $barbero->id,
                'sede_id' => $sede->id,
                'fecha' => $citaData['fecha'],
                'hora_inicio' => $citaData['hora_inicio'],
                'hora_fin' => $horaFin,
                'estado' => $citaData['estado'],
                'metodo_pago' => $citaData['metodo_pago'],
                'monto_servicios' => $montoServicios,
                'monto_total' => $montoServicios,
            ]);

            // Asociar servicios
            foreach ($serviciosCita as $servicio) {
                $cita->servicios()->attach($servicio->id, [
                    'precio_momento' => $servicio->precio,
                ]);
            }

            // Simular comprobante para transferencias
            if ($citaData['metodo_pago'] === 'transferencia') {
                $cita->update([
                    'comprobante_pago_url' => 'storage/comprobantes/ejemplo_' . $cita->id . '.jpg',
                ]);
            }
        }

        $this->command->info('Citas de ejemplo creadas exitosamente');
    }
}