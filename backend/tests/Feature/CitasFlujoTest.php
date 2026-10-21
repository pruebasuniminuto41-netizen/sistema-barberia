<?php

namespace Tests\Feature;

use App\Mail\CitaNotificacion;
use App\Models\Cita;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitasFlujoTest extends TestCase
{
    use RefreshDatabase;

    private string $zona = 'America/Bogota';

    protected function setUp(): void
    {
        parent::setUp();

        // Lunes 2 de noviembre de 2026, 09:00 hora de Bogotá
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', $this->zona));
        // Datos base del negocio (sin las citas de demostración)
        foreach (['Role', 'Sede', 'User', 'Servicio', 'Producto'] as $seeder) {
            $this->seed('Database\\Seeders\\' . $seeder . 'Seeder');
        }
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $correo): User
    {
        return User::where('email', $correo)->firstOrFail();
    }

    private function como(string $correo): User
    {
        $u = $this->usuario($correo);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function reservar(array $extra = [])
    {
        $datos = array_merge([
            'sede_id' => 1,
            'barbero_id' => $this->usuario('barbero01@barberia.com')->id,
            'fecha' => '2026-11-05',
            'hora_inicio' => '10:00',
            'servicios' => [['id' => 1]], // Corte clásico, 30 min
            'metodo_pago' => 'efectivo',
        ], $extra);

        return $this->postJson('/api/citas', $datos);
    }

    /** PNG real de 1x1 píxel (el entorno de pruebas puede no tener la extensión GD). */
    private function comprobante(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cmp');
        file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        return new UploadedFile($ruta, $nombre, 'image/png', null, true);
    }

    private function html(CitaNotificacion $m): string
    {
        $m->build();

        return view($m->view, $m->viewData)->render();
    }

    private function correosA(string $correo, string $tipo)
    {
        return Mail::sent(CitaNotificacion::class, function ($m) use ($correo, $tipo) {
            return $m->hasTo($correo) && $m->tipo === $tipo;
        });
    }

    private function adjuntoIcs(CitaNotificacion $m): string
    {
        $m->build();

        return $m->rawAttachments[0]['data'];
    }

    // ------------------------------------------------------------------ reserva

    public function test_reserva_en_efectivo_queda_confirmada_y_envia_correo_con_calendario()
    {
        $this->como('cliente1@test.com');

        $r = $this->reservar()->assertStatus(201);
        $r->assertJsonPath('cita.estado', 'confirmada');
        $this->assertStringContainsString('calendar.google.com', $r->json('enlace_calendario'));

        $correos = $this->correosA('cliente1@test.com', 'confirmada');
        $this->assertCount(1, $correos);

        $ics = $this->adjuntoIcs($correos->first());
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
        // 10:00 en Bogotá (UTC-5) son las 15:00 UTC; el corte clásico dura 30 min
        $this->assertStringContainsString('DTSTART:20261105T150000Z', $ics);
        $this->assertStringContainsString('DTEND:20261105T153000Z', $ics);
        $this->assertStringContainsString("\r\n", $ics);
        foreach (explode("\r\n", $ics) as $linea) {
            $this->assertLessThanOrEqual(75, strlen($linea), "Línea de .ics demasiado larga: $linea");
        }

        // El barbero también se entera de la cita nueva
        $this->assertCount(1, $this->correosA('barbero01@barberia.com', 'recibida'));
    }

    public function test_el_correo_se_renderiza_con_los_datos_de_la_cita()
    {
        $this->como('cliente1@test.com');
        $this->reservar()->assertStatus(201);

        $m = $this->correosA('cliente1@test.com', 'confirmada')->first();
        $html = $this->html($m);
        $this->assertStringContainsString('Tu cita está confirmada', $html);
        $this->assertStringContainsString('Barbería Centro', $html);
        $this->assertStringContainsString('10:00 - 10:30', $html);
        $this->assertStringContainsString('Corte Clásico', $html);
        $this->assertStringContainsString('Agregar a Google Calendar', $html);
        $this->assertStringContainsString('Jueves 05 de noviembre de 2026', $html);
    }

    public function test_transferencia_exige_comprobante_y_se_confirma_al_validarlo()
    {
        $this->como('cliente1@test.com');
        $this->reservar(['metodo_pago' => 'transferencia'])->assertStatus(422);

        $r = $this->reservar([
            'metodo_pago' => 'transferencia',
            'comprobante_pago' => $this->comprobante('pago.png'),
            'servicios' => json_encode([['id' => 1]]),
        ])->assertStatus(201);
        $r->assertJsonPath('cita.estado', 'pendiente_validacion');
        $r->assertJsonPath('cita.tiene_comprobante', true);
        $id = $r->json('cita.id');

        $this->assertCount(1, $this->correosA('cliente1@test.com', 'recibida'));
        $this->assertCount(0, $this->correosA('cliente1@test.com', 'confirmada'));

        // El comprobante no viaja en los listados, pero el supervisor puede abrirlo
        $this->assertArrayNotHasKey('comprobante_pago_data', $this->getJson('/api/citas')->json('0'));

        $this->como('supervisor0@barberia.com');
        $this->get("/api/citas/$id/comprobante")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson("/api/citas/$id/validar-comprobante", ['estado' => 'confirmada'])->assertOk();

        $conf = $this->correosA('cliente1@test.com', 'confirmada');
        $this->assertCount(1, $conf);
        $this->assertStringContainsString('SEQUENCE:1', $this->adjuntoIcs($conf->first()));

        // No se puede validar dos veces
        $this->postJson("/api/citas/$id/validar-comprobante", ['estado' => 'cancelada'])->assertStatus(422);

        // Otro cliente no puede ver el comprobante; el supervisor de otra sede tampoco
        $this->como('cliente2@test.com');
        $this->get("/api/citas/$id/comprobante")->assertStatus(403);
        $this->como('supervisor1@barberia.com');
        $this->get("/api/citas/$id/comprobante")->assertStatus(403);
        $this->postJson("/api/citas/$id/validar-comprobante", ['estado' => 'confirmada'])->assertStatus(403);
    }

    public function test_rechazar_comprobante_cancela_y_avisa()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar([
            'metodo_pago' => 'transferencia',
            'comprobante_pago' => $this->comprobante('pago.png'),
            'servicios' => json_encode([['id' => 1]]),
        ])->json('cita.id');

        $this->como('supervisor0@barberia.com');
        $this->postJson("/api/citas/$id/validar-comprobante", ['estado' => 'cancelada'])->assertOk();

        $cancel = $this->correosA('cliente1@test.com', 'cancelada');
        $this->assertCount(1, $cancel);
        $ics = $this->adjuntoIcs($cancel->first());
        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
    }

    public function test_reglas_de_la_reserva()
    {
        $this->como('cliente1@test.com');
        $b1 = $this->usuario('barbero01@barberia.com')->id;
        $b2 = $this->usuario('barbero02@barberia.com')->id;

        $this->reservar()->assertStatus(201);

        // Solapamiento con el mismo barbero (10:15 cae dentro de 10:00-10:30)
        $this->como('cliente2@test.com');
        $this->reservar(['hora_inicio' => '10:15'])->assertStatus(409);
        // Justo al terminar la anterior sí se puede
        $this->reservar(['hora_inicio' => '10:30'])->assertStatus(201);

        // El mismo cliente no puede estar en dos lugares a la vez (otro barbero, mismo horario)
        $this->como('cliente1@test.com');
        $this->reservar(['barbero_id' => $b2, 'hora_inicio' => '10:00'])->assertStatus(409);

        // Fuera de horario, hora pasada, sin servicios, barbero de otra sede, barbero inexistente como tal
        $this->reservar(['hora_inicio' => '06:00'])->assertStatus(422);
        $this->reservar(['hora_inicio' => '19:45', 'servicios' => [['id' => 6]]])->assertStatus(422); // coloración 90 min pasa del cierre
        $this->reservar(['fecha' => '2026-11-02', 'hora_inicio' => '08:00'])->assertStatus(422);
        $this->reservar(['servicios' => []])->assertStatus(422);
        $this->reservar(['sede_id' => 2])->assertStatus(422);
        $this->reservar(['barbero_id' => $this->usuario('cliente2@test.com')->id])->assertStatus(422);
        $this->reservar(['fecha' => '2026-10-01'])->assertStatus(422);

        // Un error de validación llega en español y legible
        $r = $this->reservar(['servicios' => []])->assertStatus(422);
        $this->assertStringContainsString('servicio', mb_strtolower(json_encode($r->json('errors'))));
    }

    public function test_disponibilidad_excluye_horarios_ocupados_y_pasados()
    {
        $this->como('cliente1@test.com');
        $b1 = $this->usuario('barbero01@barberia.com')->id;
        $this->reservar()->assertStatus(201); // 10:00-10:30

        $r = $this->getJson("/api/barberos/$b1/disponibilidad?fecha=2026-11-05&duracion=30")->assertOk();
        $h = $r->json('horarios');
        $this->assertContains('09:30', $h);
        $this->assertNotContains('10:00', $h);
        $this->assertContains('10:30', $h);
        $this->assertContains('19:30', $h);
        $this->assertNotContains('20:00', $h);

        // Con servicios de 60 min, las 09:30 ya chocarían con la cita de las 10:00
        $h60 = $this->getJson("/api/barberos/$b1/disponibilidad?fecha=2026-11-05&duracion=60")->json('horarios');
        $this->assertNotContains('09:30', $h60);
        $this->assertContains('09:00', $h60);

        // Hoy a las 09:00 no se ofrecen horas pasadas
        $hoy = $this->getJson("/api/barberos/$b1/disponibilidad?fecha=2026-11-02&duracion=30")->json('horarios');
        $this->assertNotContains('08:30', $hoy);
        $this->assertNotContains('09:00', $hoy);
        $this->assertContains('09:30', $hoy);
    }

    // ------------------------------------------------------------------ cambios de estado

    public function test_cliente_cancela_con_anticipacion_y_se_actualiza_el_calendario()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id');

        $this->putJson("/api/citas/$id", ['estado' => 'completada'])->assertStatus(403);
        $this->putJson("/api/citas/$id", ['estado' => 'cancelada'])->assertOk();

        $cancel = $this->correosA('cliente1@test.com', 'cancelada');
        $this->assertCount(1, $cancel);
        $this->assertStringContainsString('METHOD:CANCEL', $this->adjuntoIcs($cancel->first()));
        $this->assertCount(1, $this->correosA('barbero01@barberia.com', 'cancelada'));

        // Ya cancelada: no se puede volver a cancelar
        $this->putJson("/api/citas/$id", ['estado' => 'cancelada'])->assertOk(); // sin cambio de estado, no hace nada
        $this->assertCount(1, $this->correosA('cliente1@test.com', 'cancelada'));

        // El horario quedó libre otra vez
        $this->reservar()->assertStatus(201);
    }

    public function test_cliente_no_puede_cancelar_a_ultima_hora_ni_citas_ajenas()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar(['fecha' => '2026-11-02', 'hora_inicio' => '10:00'])->json('cita.id'); // en 1 hora
        $this->putJson("/api/citas/$id", ['estado' => 'cancelada'])->assertStatus(422);

        $otra = $this->reservar()->json('cita.id');
        $this->como('cliente2@test.com');
        $this->putJson("/api/citas/$otra", ['estado' => 'cancelada'])->assertStatus(403);
        $this->getJson("/api/citas/$otra")->assertStatus(403);
    }

    public function test_barbero_sigue_el_flujo_de_estados_solo_en_sus_citas()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id');

        $this->como('barbero02@barberia.com');
        $this->putJson("/api/citas/$id", ['estado' => 'en_proceso'])->assertStatus(403); // no es su cita

        $this->como('barbero01@barberia.com');
        $this->putJson("/api/citas/$id", ['estado' => 'completada'])->assertStatus(422); // no se puede saltar pasos
        $this->putJson("/api/citas/$id", ['estado' => 'en_proceso'])->assertOk();
        $this->putJson("/api/citas/$id", ['estado' => 'completada'])->assertOk();
        $this->putJson("/api/citas/$id", ['estado' => 'cancelada'])->assertStatus(422); // ya completada
        $this->putJson("/api/citas/$id", ['estado' => 'pendiente'])->assertStatus(422);
    }

    public function test_una_cita_pendiente_de_validacion_no_la_confirma_el_barbero()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar([
            'metodo_pago' => 'transferencia',
            'comprobante_pago' => $this->comprobante('p.png'),
            'servicios' => json_encode([['id' => 1]]),
        ])->json('cita.id');

        $this->como('barbero01@barberia.com');
        $this->putJson("/api/citas/$id", ['estado' => 'confirmada'])->assertStatus(422);
        $this->putJson("/api/citas/$id", ['estado' => 'en_proceso'])->assertStatus(422);
    }

    // ------------------------------------------------------------------ productos

    public function test_agregar_productos_descuenta_stock_y_actualiza_totales()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id'); // $15

        $p = Producto::where('sede_id', 1)->first();
        $stock = $p->stock_por_sede;

        $this->como('supervisor0@barberia.com');
        // La pantalla envía producto_id
        $this->postJson("/api/citas/$id/productos", ['productos' => [['producto_id' => $p->id, 'cantidad' => 2]]])->assertOk();
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $p->id, 'cantidad' => 1]]])->assertOk();

        $cita = Cita::find($id);
        $this->assertEquals(round(15 + 3 * $p->precio, 2), round($cita->monto_total, 2));
        $this->assertEquals(3, $cita->productos()->first()->pivot->cantidad);
        $this->assertEquals($stock - 3, $p->fresh()->stock_por_sede);

        // Stock insuficiente: no cambia nada
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $p->id, 'cantidad' => $stock]]])->assertStatus(422);
        $this->assertEquals($stock - 3, $p->fresh()->stock_por_sede);

        // Producto de otra sede
        $otro = Producto::where('sede_id', 2)->first();
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $otro->id, 'cantidad' => 1]]])->assertStatus(422);

        // Supervisor de otra sede, barbero y cliente no pueden
        $this->como('supervisor1@barberia.com');
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $p->id, 'cantidad' => 1]]])->assertStatus(403);
        $this->como('barbero01@barberia.com');
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $p->id, 'cantidad' => 1]]])->assertStatus(403);
    }

    public function test_no_se_agregan_productos_a_citas_canceladas()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id');
        $this->putJson("/api/citas/$id", ['estado' => 'cancelada'])->assertOk();

        $p = Producto::where('sede_id', 1)->first();
        $this->como('supervisor0@barberia.com');
        $this->postJson("/api/citas/$id/productos", ['productos' => [['id' => $p->id, 'cantidad' => 1]]])->assertStatus(422);
    }

    // ------------------------------------------------------------------ recordatorios

    public function test_recordatorios_se_envian_una_sola_vez_y_con_calendario()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar(['fecha' => '2026-11-04', 'hora_inicio' => '14:00'])->json('cita.id');
        // La reserva se "hizo" hace dos días, para que tenga sentido recordarla
        Cita::whereKey($id)->update(['created_at' => Carbon::now($this->zona)->subDays(2)]);
        Mail::fake();

        // A 29 h de la cita todavía no toca
        $this->artisan('citas:enviar-recordatorios')->assertExitCode(0);
        Mail::assertNothingSent();

        // A 23 h: recordatorio de 24 h, una sola vez aunque el comando corra varias veces
        Carbon::setTestNow(Carbon::parse('2026-11-03 15:00:00', $this->zona));
        $this->artisan('citas:enviar-recordatorios');
        $this->artisan('citas:enviar-recordatorios');
        $this->artisan('citas:enviar-recordatorios');
        $this->assertCount(1, $this->correosA('cliente1@test.com', 'recordatorio_24h'));
        $this->assertStringContainsString(
            'BEGIN:VCALENDAR',
            $this->adjuntoIcs($this->correosA('cliente1@test.com', 'recordatorio_24h')->first())
        );

        // A 1 h 30 min: recordatorio de 2 h, una sola vez
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:30:00', $this->zona));
        $this->artisan('citas:enviar-recordatorios');
        $this->artisan('citas:enviar-recordatorios');
        $this->assertCount(1, $this->correosA('cliente1@test.com', 'recordatorio_2h'));

        // Pasada la hora de la cita, nada más
        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', $this->zona));
        $this->artisan('citas:enviar-recordatorios');
        $this->assertCount(1, $this->correosA('cliente1@test.com', 'recordatorio_2h'));
        $this->assertCount(1, $this->correosA('cliente1@test.com', 'recordatorio_24h'));
    }

    public function test_no_se_recuerda_una_cita_cancelada_ni_una_recien_reservada()
    {
        $this->como('cliente1@test.com');
        $cancelada = $this->reservar(['fecha' => '2026-11-03', 'hora_inicio' => '16:00'])->json('cita.id');
        $this->putJson("/api/citas/$cancelada", ['estado' => 'cancelada'])->assertOk();
        // Reservada hace un momento para dentro de 3 horas escasas
        $this->reservar(['fecha' => '2026-11-02', 'hora_inicio' => '12:00'])->assertStatus(201);
        Mail::fake();

        $this->artisan('citas:enviar-recordatorios')->assertExitCode(0);
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------ robustez y permisos

    public function test_si_el_correo_falla_la_reserva_se_guarda_igual()
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->como('cliente1@test.com');
        $this->reservar()->assertStatus(201);
        $this->assertDatabaseCount('citas', 1);
    }

    public function test_calendario_descargable_solo_para_quien_corresponde()
    {
        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id');

        $r = $this->get("/api/citas/$id/calendario")->assertOk();
        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VEVENT', $r->getContent());

        $this->como('cliente2@test.com');
        $this->get("/api/citas/$id/calendario")->assertStatus(403);
    }

    public function test_admin_crea_usuarios_y_productos_y_edita_citas()
    {
        $this->como('admin@barberia.com');

        $this->postJson('/api/users', [
            'name' => 'Nuevo Barbero', 'email' => 'nuevo@barberia.com', 'password' => 'password123',
            'role' => 'barbero', 'sede_id' => 1,
        ])->assertStatus(201);
        $this->assertTrue($this->usuario('nuevo@barberia.com')->hasRole('barbero'));

        $this->postJson('/api/productos', ['nombre' => 'Cera', 'precio' => 9, 'stock_por_sede' => 5, 'sede_id' => 1])->assertStatus(201);

        $this->como('cliente1@test.com');
        $id = $this->reservar()->json('cita.id');
        $this->como('admin@barberia.com');
        $this->putJson("/api/citas/$id", ['estado' => 'en_proceso'])->assertOk();
        $this->getJson('/api/admin/metricas')->assertOk();
    }

    public function test_supervisor_solo_gestiona_productos_de_su_sede()
    {
        $this->como('supervisor0@barberia.com');
        $this->postJson('/api/productos', ['nombre' => 'X', 'precio' => 1, 'stock_por_sede' => 1, 'sede_id' => 1])->assertStatus(201);
        $this->postJson('/api/productos', ['nombre' => 'X', 'precio' => 1, 'stock_por_sede' => 1, 'sede_id' => 2])->assertStatus(403);

        $ajeno = Producto::where('sede_id', 2)->first();
        $this->putJson("/api/productos/{$ajeno->id}", ['precio' => 99])->assertStatus(403);
        $this->deleteJson("/api/productos/{$ajeno->id}")->assertStatus(403);
    }

    public function test_listados_por_rol_y_orden()
    {
        $this->como('cliente1@test.com');
        $this->reservar(['fecha' => '2026-11-06'])->assertStatus(201);
        $this->reservar(['fecha' => '2026-11-04'])->assertStatus(201);
        $lista = $this->getJson('/api/citas')->assertOk()->json();
        $this->assertCount(2, $lista);
        $this->assertSame('2026-11-06', $lista[0]['fecha']); // más reciente primero
        $this->assertSame('10:00', $lista[0]['hora_inicio']);

        $this->como('cliente2@test.com');
        $this->assertCount(0, $this->getJson('/api/citas')->json());
        $this->como('barbero01@barberia.com');
        $this->assertCount(2, $this->getJson('/api/citas')->json());
        $this->como('barbero02@barberia.com');
        $this->assertCount(0, $this->getJson('/api/citas')->json());
        $this->como('supervisor0@barberia.com');
        $this->assertCount(2, $this->getJson('/api/citas')->json());
        $this->como('supervisor1@barberia.com');
        $this->assertCount(0, $this->getJson('/api/citas')->json());
    }
}
