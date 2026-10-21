<?php

namespace Tests\Feature;

use App\Mail\CitaNotificacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorreoCuentasEjemploTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'America/Bogota'));
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

    private function reservar()
    {
        return $this->postJson('/api/citas', [
            'sede_id' => 1,
            'barbero_id' => User::where('email', 'barbero01@barberia.com')->value('id'),
            'fecha' => '2026-11-05',
            'hora_inicio' => '10:00',
            'servicios' => [['id' => 1]],
            'metodo_pago' => 'efectivo',
        ]);
    }

    public function test_no_se_envian_correos_a_dominios_de_cuentas_de_ejemplo()
    {
        // Por defecto barberia.com y test.com son dominios de ejemplo
        $this->assertContains('test.com', config('barberia.dominios_demo'));

        Sanctum::actingAs(User::where('email', 'cliente1@test.com')->first(), ['*']);
        $this->reservar()->assertStatus(201);

        Mail::assertNothingSent();
    }

    public function test_un_cliente_real_si_recibe_su_correo_y_el_barbero_de_ejemplo_no()
    {
        $real = User::create([
            'name' => 'Cliente Real', 'email' => 'cliente.real@gmail.com', 'password' => bcrypt('password123'),
        ]);
        $real->assignRole('cliente');

        Sanctum::actingAs($real, ['*']);
        $this->reservar()->assertStatus(201);

        Mail::assertSent(CitaNotificacion::class, function ($m) {
            return $m->hasTo('cliente.real@gmail.com') && $m->tipo === 'confirmada';
        });
        Mail::assertNotSent(CitaNotificacion::class, function ($m) {
            return $m->hasTo('barbero01@barberia.com');
        });
    }
}
