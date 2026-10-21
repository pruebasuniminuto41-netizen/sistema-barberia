<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatosDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-21 09:30:00', 'America/Bogota'));
        foreach (['Role', 'Sede', 'User', 'Servicio', 'Producto'] as $seeder) {
            $this->seed('Database\\Seeders\\' . $seeder . 'Seeder');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function minutos($hora): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));

        return $h * 60 + $m;
    }

    public function test_los_datos_de_demostracion_son_coherentes()
    {
        $stockInicial = Producto::pluck('stock_por_sede', 'id')->all();

        $this->artisan('demo:cargar')->assertExitCode(0);

        $citas = Cita::with(['servicios', 'productos', 'barbero.barberosSedes'])->get();
        $this->assertGreaterThan(80, $citas->count());

        $hoy = Carbon::now('America/Bogota')->startOfDay();

        foreach ($citas as $c) {
            // Horario de atención y duración = suma de los servicios
            $ini = $this->minutos($c->hora_inicio);
            $fin = $this->minutos($c->hora_fin);
            $this->assertGreaterThanOrEqual(8 * 60, $ini, "cita {$c->id} antes de abrir");
            $this->assertLessThanOrEqual(20 * 60, $fin, "cita {$c->id} después del cierre");
            $this->assertSame((int) $c->servicios->sum('duracion_minutos'), $fin - $ini, "duración cita {$c->id}");

            // Dinero que cuadra
            $this->assertEquals(round($c->servicios->sum('precio'), 2), round($c->monto_servicios, 2));
            $porProductos = $c->productos->sum(function ($p) {
                return $p->pivot->precio_unitario * $p->pivot->cantidad;
            });
            $this->assertEquals(round($porProductos, 2), round($c->monto_productos, 2));
            $this->assertEquals(round($c->monto_servicios + $c->monto_productos, 2), round($c->monto_total, 2));

            // El barbero atiende en la sede de la cita y la cita es de un cliente
            $this->assertTrue($c->barbero->barberosSedes->contains('id', $c->sede_id), "barbero fuera de su sede en cita {$c->id}");
            $this->assertTrue(User::find($c->cliente_id)->hasRole('cliente'));

            // Estados con sentido según el día
            $dia = Carbon::parse($c->fecha->format('Y-m-d'), 'America/Bogota');
            if ($dia->lt($hoy)) {
                $this->assertContains($c->estado, ['completada', 'cancelada'], "cita pasada {$c->id} con estado {$c->estado}");
            } else {
                $this->assertContains($c->estado, ['confirmada', 'pendiente_validacion'], "cita futura {$c->id} con estado {$c->estado}");
            }
            $this->assertFalse($dia->isSunday(), 'no se agenda en domingo');
            if ($c->estado === 'pendiente_validacion') {
                $this->assertSame('transferencia', $c->metodo_pago);
            }
            if ($c->metodo_pago === 'transferencia') {
                $this->assertTrue($c->tiene_comprobante, "transferencia sin comprobante en cita {$c->id}");
            } else {
                $this->assertFalse($c->tiene_comprobante);
            }
            // Solo las atendidas llevan productos
            if ($c->estado !== 'completada') {
                $this->assertCount(0, $c->productos);
            }
        }

        // Nadie se solapa: ni el barbero ni el cliente
        foreach (['barbero_id', 'cliente_id'] as $campo) {
            $porClave = $citas->where('estado', '!=', 'cancelada')->groupBy(function ($c) use ($campo) {
                return $c->$campo . '|' . $c->fecha->format('Y-m-d');
            });
            foreach ($porClave as $clave => $grupo) {
                $rangos = $grupo->map(function ($c) {
                    return [$this->minutos($c->hora_inicio), $this->minutos($c->hora_fin)];
                })->sortBy(0)->values()->all();
                for ($i = 1; $i < count($rangos); $i++) {
                    $this->assertLessThanOrEqual($rangos[$i][0], $rangos[$i - 1][1], "solape ($campo) en $clave");
                }
            }
        }

        // El stock actual = inicial - lo vendido, y nunca queda negativo ni agotado
        $vendido = [];
        foreach ($citas as $c) {
            foreach ($c->productos as $p) {
                $vendido[$p->id] = ($vendido[$p->id] ?? 0) + $p->pivot->cantidad;
            }
        }
        foreach (Producto::all() as $p) {
            $this->assertSame($stockInicial[$p->id] - ($vendido[$p->id] ?? 0), $p->stock_por_sede, "stock de {$p->nombre}");
            $this->assertGreaterThanOrEqual(3, $p->stock_por_sede);
        }
        $this->assertGreaterThan(0, array_sum($vendido), 'debe haber ventas de productos');

        // Lo que se usa en la presentación
        $this->assertSame(3, $citas->where('estado', 'pendiente_validacion')->count());
        $juan = User::where('email', 'cliente1@test.com')->first();
        $this->assertSame('Juan Pérez', $juan->name);
        $proximasJuan = $citas->where('cliente_id', $juan->id)->where('estado', 'confirmada')->filter(function ($c) use ($hoy) {
            return Carbon::parse($c->fecha->format('Y-m-d'), 'America/Bogota')->gte($hoy);
        });
        $this->assertGreaterThanOrEqual(2, $proximasJuan->count());
        $this->assertGreaterThanOrEqual(3, $citas->where('cliente_id', $juan->id)->where('estado', 'completada')->count());
        $this->assertSame('Carlos Ramírez', User::where('email', 'barbero01@barberia.com')->first()->name);

        // Cada barbero tiene historial y todas las sedes generan ingresos
        foreach (User::role('barbero')->get() as $b) {
            $this->assertGreaterThan(5, $citas->where('barbero_id', $b->id)->where('estado', 'completada')->count(), "poco historial de {$b->name}");
        }
        $this->assertSame(4, $citas->where('estado', 'completada')->pluck('sede_id')->unique()->count());
    }

    public function test_es_idempotente_y_no_toca_una_base_con_citas()
    {
        $this->artisan('demo:cargar')->assertExitCode(0);
        $total = Cita::count();
        $stock = Producto::sum('stock_por_sede');

        $this->artisan('demo:cargar')->assertExitCode(0);

        $this->assertSame($total, Cita::count());
        $this->assertEquals($stock, Producto::sum('stock_por_sede'));
    }
}
