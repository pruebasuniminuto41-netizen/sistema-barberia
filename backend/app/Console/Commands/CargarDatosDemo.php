<?php

namespace App\Console\Commands;

use App\Models\Cita;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Carga un historial realista para presentar el sistema: tres semanas de citas atendidas
 * (con productos vendidos y stock descontado), algunas canceladas, citas próximas confirmadas
 * y pagos por transferencia pendientes de validar. Solo actúa si todavía no hay citas.
 */
class CargarDatosDemo extends Command
{
    protected $signature = 'demo:cargar';

    protected $description = 'Carga datos de demostración coherentes (solo si no hay citas registradas)';

    private const NOMBRES = [
        'supervisor0@barberia.com' => 'Laura Martínez',
        'supervisor1@barberia.com' => 'Andrés Gómez',
        'supervisor2@barberia.com' => 'Paola Rincón',
        'supervisor3@barberia.com' => 'Jorge Castaño',
        'barbero01@barberia.com' => 'Carlos Ramírez',
        'barbero02@barberia.com' => 'Miguel Torres',
        'barbero11@barberia.com' => 'Sebastián Rojas',
        'barbero12@barberia.com' => 'Daniel Pardo',
        'barbero21@barberia.com' => 'Felipe Ortiz',
        'barbero22@barberia.com' => 'Julián Vargas',
        'barbero31@barberia.com' => 'Camilo Herrera',
        'barbero32@barberia.com' => 'Esteban Ruiz',
        'cliente1@test.com' => 'Juan Pérez',
        'cliente2@test.com' => 'María Fernanda López',
        'cliente3@test.com' => 'Santiago Díaz',
        'cliente4@test.com' => 'Valentina Moreno',
        'cliente5@test.com' => 'Andrés Cárdenas',
    ];

    private const CLIENTES_EXTRA = [
        'Nicolás Salazar', 'Daniela Ospina', 'Mateo Quintero', 'Camila Duque', 'Tomás Arango',
        'Isabella Mejía', 'Sergio Londoño', 'Luisa Echeverri', 'David Suárez', 'Natalia Zapata',
        'Alejandro Bedoya', 'Carolina Giraldo',
    ];

    /** Frecuencia relativa de cada servicio principal. */
    private const POPULARIDAD = [
        'Corte Clásico' => 25, 'Corte Fade' => 25, 'Corte + Barba' => 15, 'Barba Completa' => 12,
        'Corte Infantil' => 5, 'Lavado y Estilo' => 5, 'Perfilado de Cejas' => 4,
        'Tratamiento Capilar' => 3, 'Coloración' => 3, 'Dreadlocks Maintenance' => 3,
    ];

    private $zona;
    private $stock = [];
    private $ocupado = [];       // "barbero|fecha" => [[ini, fin], ...] en minutos
    private $ocupadoCliente = []; // "cliente|fecha" => [[ini, fin], ...]
    private $servicios;
    private $citasCreadas = 0;

    public function handle()
    {
        if (Cita::count() > 0) {
            $this->warn('Ya hay citas registradas: no se cargan datos de demostración.');

            return 0;
        }

        mt_srand(20261021); // mismo resultado en cada ejecución
        $this->zona = config('app.timezone');
        $hoy = Carbon::now($this->zona)->startOfDay();

        DB::transaction(function () use ($hoy) {
            $this->nombresRealistas();
            $clientes = $this->clientes();
            $this->servicios = Servicio::all()->keyBy('nombre');

            $sedes = Sede::with('barberos')->get();
            foreach (Producto::all() as $p) {
                $this->stock[$p->id] = (int) $p->stock_por_sede;
            }
            $productosPorSede = Producto::all()->groupBy('sede_id');

            // Citas próximas que se usan en la demostración (primero, para que nada las pise)
            $this->citasEspeciales($hoy, $clientes, $sedes);

            // Historial: tres semanas de actividad
            for ($d = 21; $d >= 1; $d--) {
                $dia = $hoy->copy()->subDays($d);
                if ($dia->isSunday()) {
                    continue;
                }
                foreach ($sedes as $sede) {
                    foreach ($sede->barberos as $barbero) {
                        $this->jornada($dia, $sede, $barbero, $clientes, $productosPorSede[$sede->id] ?? collect(), true);
                    }
                }
            }

            // Agenda de los próximos días
            for ($d = 1; $d <= 6; $d++) {
                $dia = $hoy->copy()->addDays($d);
                if ($dia->isSunday()) {
                    continue;
                }
                foreach ($sedes as $sede) {
                    foreach ($sede->barberos as $barbero) {
                        $this->jornada($dia, $sede, $barbero, $clientes, collect(), false);
                    }
                }
            }

            foreach ($this->stock as $id => $cantidad) {
                Producto::whereKey($id)->update(['stock_por_sede' => $cantidad]);
            }
        });

        $this->info("Datos de demostración cargados: {$this->citasCreadas} citas.");

        return 0;
    }

    // ------------------------------------------------------------------ usuarios

    private function nombresRealistas(): void
    {
        foreach (self::NOMBRES as $correo => $nombre) {
            User::where('email', $correo)->update(['name' => $nombre]);
        }
    }

    /** Los 5 clientes de demostración más 12 clientes habituales. Devuelve todos. */
    private function clientes()
    {
        foreach (self::CLIENTES_EXTRA as $i => $nombre) {
            $numero = $i + 6;
            $cliente = User::firstOrCreate(
                ['email' => "cliente{$numero}@test.com"],
                ['name' => $nombre, 'password' => Hash::make('password'), 'phone' => sprintf('300%07d', 1200000 + $numero * 137)]
            );
            if (!$cliente->hasRole('cliente')) {
                $cliente->assignRole('cliente');
            }
        }

        return User::role('cliente')->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ citas

    /** Citas que se muestran en la presentación: pagos por validar y agenda de mañana. */
    private function citasEspeciales(Carbon $hoy, $clientes, $sedes): void
    {
        $manana = $hoy->copy()->addDay();
        while ($manana->isSunday()) {
            $manana->addDay();
        }
        $pasado = $manana->copy()->addDay();
        while ($pasado->isSunday()) {
            $pasado->addDay();
        }

        $cliente = function ($n) use ($clientes) {
            return $clientes->firstWhere('email', "cliente{$n}@test.com");
        };
        $barbero = function ($correo) {
            return User::where('email', $correo)->first();
        };
        $s = $this->servicios ?: Servicio::all()->keyBy('nombre');
        $this->servicios = $s;

        // Pagos por transferencia pendientes de validar (para que el supervisor los valide en vivo)
        $this->crearCita($manana, '16:00', $sedes->firstWhere('id', 1), $barbero('barbero01@barberia.com'), $cliente(2), [$s['Corte + Barba']], 'transferencia', 'pendiente_validacion');
        $this->crearCita($manana, '17:30', $sedes->firstWhere('id', 1), $barbero('barbero02@barberia.com'), $cliente(4), [$s['Corte Fade']], 'transferencia', 'pendiente_validacion');
        $this->crearCita($pasado, '11:00', $sedes->firstWhere('id', 2), $barbero('barbero11@barberia.com'), $cliente(3), [$s['Corte Clásico'], $s['Barba Completa']], 'transferencia', 'pendiente_validacion');

        // Agenda confirmada del cliente de demostración
        $this->crearCita($manana, '10:00', $sedes->firstWhere('id', 1), $barbero('barbero01@barberia.com'), $cliente(1), [$s['Corte Clásico'], $s['Barba Completa']], 'efectivo', 'confirmada');
        $this->crearCita($pasado->copy()->addDay()->isSunday() ? $pasado->copy()->addDays(2) : $pasado->copy()->addDay(), '15:00', $sedes->firstWhere('id', 1), $barbero('barbero02@barberia.com'), $cliente(1), [$s['Corte Fade']], 'efectivo', 'confirmada');
        $this->crearCita($manana, '12:00', $sedes->firstWhere('id', 1), $barbero('barbero02@barberia.com'), $cliente(5), [$s['Corte Infantil']], 'efectivo', 'confirmada');
    }

    /** Agenda de un barbero en un día. $pasado = historial (atendidas/canceladas); si no, citas próximas. */
    private function jornada(Carbon $dia, $sede, $barbero, $clientes, $productos, bool $pasado): void
    {
        $citas = $pasado ? $this->aleatorio([1, 2, 2, 3, 3]) : $this->aleatorio([0, 1, 1, 1, 2]);
        $minuto = 9 * 60 + 30 * $this->aleatorio([0, 0, 1, 2]);   // empieza entre 09:00 y 10:00
        if (!$pasado) {
            $minuto = 10 * 60 + 30 * $this->aleatorio([0, 1, 2, 3, 4]);
        }

        for ($i = 0; $i < $citas; $i++) {
            $servicios = $this->elegirServicios();
            $duracion = array_sum(array_map(function ($s) {
                return $s->duracion_minutos;
            }, $servicios));

            // Si el hueco ya está ocupado (por una cita especial), se corre al final de esa cita
            $claveBarbero = $barbero->id . '|' . $dia->format('Y-m-d');
            do {
                $choque = false;
                foreach ($this->ocupado[$claveBarbero] ?? [] as [$a, $b]) {
                    if ($minuto < $b && $minuto + $duracion > $a) {
                        $minuto = (int) (ceil($b / 30) * 30);
                        $choque = true;
                    }
                }
            } while ($choque);

            if ($minuto + $duracion > 19 * 60 + 30) {
                break;
            }

            $cliente = $this->elegirCliente($clientes, $dia, $minuto, $minuto + $duracion);
            if ($cliente) {
                $efectivo = $this->aleatorio([1, 1, 1, 1, 1, 1, 1, 0, 0, 0]) === 1;
                $estado = $pasado
                    ? ($this->aleatorio([1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0]) === 1 ? 'completada' : 'cancelada')
                    : 'confirmada';

                $this->crearCita(
                    $dia,
                    sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60),
                    $sede,
                    $barbero,
                    $cliente,
                    $servicios,
                    $efectivo ? 'efectivo' : 'transferencia',
                    $estado,
                    $productos
                );
            }

            // Descanso hasta el siguiente cliente (múltiplos de 30 minutos)
            $minuto += (int) (ceil($duracion / 30) * 30) + 30 * $this->aleatorio([0, 0, 1, 1, 2, 3]);
        }
    }

    private function crearCita(Carbon $dia, string $hora, $sede, $barbero, $cliente, array $servicios, string $metodo, string $estado, $productos = null): void
    {
        $zona = $this->zona;
        $duracion = array_sum(array_map(function ($s) {
            return $s->duracion_minutos;
        }, $servicios));
        $inicio = Carbon::parse($dia->format('Y-m-d') . ' ' . $hora, $zona);
        $fin = $inicio->copy()->addMinutes($duracion);
        $ini = $inicio->hour * 60 + $inicio->minute;
        $finMin = $ini + $duracion;

        $claveB = $barbero->id . '|' . $dia->format('Y-m-d');
        $claveC = $cliente->id . '|' . $dia->format('Y-m-d');
        $this->ocupado[$claveB][] = [$ini, $finMin];
        $this->ocupadoCliente[$claveC][] = [$ini, $finMin];

        $montoServicios = array_sum(array_map(function ($s) {
            return (float) $s->precio;
        }, $servicios));

        // Productos vendidos en citas atendidas: descuentan stock real
        $vendidos = [];
        $montoProductos = 0.0;
        if ($estado === 'completada' && $productos && $productos->count() > 0 && $this->aleatorio([1, 1, 1, 0, 0, 0, 0, 0, 0, 0]) === 1) {
            $cuantos = $this->aleatorio([1, 1, 2]);
            foreach ($productos->shuffle(mt_rand()) ->take($cuantos) as $producto) {
                $cantidad = $this->aleatorio([1, 1, 1, 2]);
                if ($this->stock[$producto->id] - $cantidad < 3) {
                    continue; // nunca se agota el inventario
                }
                $this->stock[$producto->id] -= $cantidad;
                $vendidos[] = [$producto, $cantidad];
                $montoProductos += (float) $producto->precio * $cantidad;
            }
        }

        $creada = $dia->copy()->subDays($this->aleatorio([1, 1, 2, 3, 4]))->setTime($this->aleatorio([8, 9, 10, 12, 15, 17, 19]), $this->aleatorio([5, 20, 40]));
        if ($creada->gt(Carbon::now($zona))) {
            $creada = Carbon::now($zona)->subHours(3);
        }

        $fila = [
            'cliente_id' => $cliente->id,
            'barbero_id' => $barbero->id,
            'sede_id' => $sede->id,
            'fecha' => $dia->format('Y-m-d'),
            'hora_inicio' => $inicio->format('H:i'),
            'hora_fin' => $fin->format('H:i'),
            'estado' => $estado,
            'metodo_pago' => $metodo,
            'monto_servicios' => $montoServicios,
            'monto_productos' => $montoProductos,
            'monto_total' => $montoServicios + $montoProductos,
            'secuencia_calendario' => $estado === 'pendiente_validacion' ? 0 : ($estado === 'cancelada' ? 2 : 1),
            // Los recordatorios de estas citas ya se dan por enviados: no se manda correo a cuentas de ejemplo
            'recordatorio_24h_enviado_at' => $creada,
            'recordatorio_2h_enviado_at' => $creada,
            'created_at' => $creada,
            'updated_at' => $creada,
        ];

        if ($metodo === 'transferencia') {
            $fila['comprobante_pago_data'] = base64_encode($this->comprobanteSvg($cliente->name, $montoServicios + $montoProductos, $creada));
            $fila['comprobante_pago_mime'] = 'image/svg+xml';
        }

        $id = DB::table('citas')->insertGetId($fila);

        foreach ($servicios as $servicio) {
            DB::table('cita_servicio')->insert([
                'cita_id' => $id, 'servicio_id' => $servicio->id,
                'precio_momento' => $servicio->precio, 'created_at' => $creada, 'updated_at' => $creada,
            ]);
        }
        foreach ($vendidos as [$producto, $cantidad]) {
            DB::table('cita_producto')->insert([
                'cita_id' => $id, 'producto_id' => $producto->id, 'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio, 'created_at' => $creada, 'updated_at' => $creada,
            ]);
        }

        $this->citasCreadas++;
    }

    // ------------------------------------------------------------------ elecciones al azar

    private function aleatorio(array $opciones)
    {
        return $opciones[mt_rand(0, count($opciones) - 1)];
    }

    private function elegirServicios(): array
    {
        $bolsa = [];
        foreach (self::POPULARIDAD as $nombre => $peso) {
            if (isset($this->servicios[$nombre])) {
                $bolsa = array_merge($bolsa, array_fill(0, $peso, $nombre));
            }
        }
        $principal = $this->servicios[$bolsa[mt_rand(0, count($bolsa) - 1)]];
        $lista = [$principal];

        // A veces el cliente suma un complemento a su corte
        if (in_array($principal->nombre, ['Corte Clásico', 'Corte Fade']) && mt_rand(1, 100) <= 25) {
            $complemento = mt_rand(0, 1) ? 'Barba Completa' : 'Perfilado de Cejas';
            if (isset($this->servicios[$complemento])) {
                $lista[] = $this->servicios[$complemento];
            }
        }

        return $lista;
    }

    /** Prefiere a los clientes de demostración y evita que alguien tenga dos citas a la vez. */
    private function elegirCliente($clientes, Carbon $dia, int $ini, int $fin)
    {
        for ($intento = 0; $intento < 6; $intento++) {
            $cliente = mt_rand(1, 100) <= 55
                ? $clientes->get(mt_rand(0, min(4, $clientes->count() - 1)))
                : $clientes->get(mt_rand(0, $clientes->count() - 1));

            $clave = $cliente->id . '|' . $dia->format('Y-m-d');
            $choca = false;
            foreach ($this->ocupadoCliente[$clave] ?? [] as [$a, $b]) {
                if ($ini < $b && $fin > $a) {
                    $choca = true;
                }
            }
            if (!$choca) {
                return $cliente;
            }
        }

        return null;
    }

    private function comprobanteSvg(string $cliente, float $monto, Carbon $fecha): string
    {
        $referencia = 'TRF-' . str_pad((string) mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $nombre = htmlspecialchars($cliente, ENT_XML1);
        $total = number_format($monto, 2, ',', '.');
        $cuando = $fecha->format('d/m/Y H:i');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="300" viewBox="0 0 420 300">
  <rect width="420" height="300" rx="14" fill="#ffffff" stroke="#d1d5db"/>
  <rect width="420" height="54" rx="14" fill="#16a34a"/>
  <text x="24" y="34" font-family="Arial" font-size="20" font-weight="bold" fill="#ffffff">Transferencia exitosa</text>
  <text x="24" y="92" font-family="Arial" font-size="13" fill="#6b7280">Valor transferido</text>
  <text x="24" y="124" font-family="Arial" font-size="30" font-weight="bold" fill="#111827">\${$total}</text>
  <text x="24" y="166" font-family="Arial" font-size="13" fill="#6b7280">Origen</text>
  <text x="24" y="186" font-family="Arial" font-size="16" fill="#111827">{$nombre}</text>
  <text x="24" y="222" font-family="Arial" font-size="13" fill="#6b7280">Destino</text>
  <text x="24" y="242" font-family="Arial" font-size="16" fill="#111827">Barbería Pro</text>
  <text x="24" y="280" font-family="Arial" font-size="12" fill="#9ca3af">{$cuando}  ·  Ref. {$referencia}</text>
</svg>
SVG;
    }
}
