<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Sede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['barberia.dominios_demo' => []]); // en las pruebas sí se "envían" correos a las cuentas de ejemplo
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'America/Bogota'));
        foreach (['Role', 'Sede', 'User', 'Servicio', 'Producto'] as $seeder) {
            $this->seed('Database\\Seeders\\' . $seeder . 'Seeder');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function como(string $correo): User
    {
        $u = User::where('email', $correo)->firstOrFail();
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    public function test_cambiar_sede_o_rol_actualiza_donde_atiende_el_barbero()
    {
        $this->como('admin@barberia.com');
        $barbero = User::where('email', 'barbero01@barberia.com')->first();

        // Pasa de la sede 1 a la 2: ya no aparece en la sede 1 y sí en la 2
        $this->putJson("/api/users/{$barbero->id}", ['sede_id' => 2])->assertOk();
        $this->assertFalse($barbero->barberosSedes()->where('sedes.id', 1)->exists());
        $this->assertTrue($barbero->barberosSedes()->where('sedes.id', 2)->exists());

        $en2 = $this->getJson('/api/sedes/2/barberos')->json();
        $this->assertContains($barbero->id, array_column($en2, 'id'));

        // Si deja de ser barbero, deja de atender en cualquier sede
        $this->putJson("/api/users/{$barbero->id}", ['role' => 'cliente'])->assertOk();
        $this->assertSame(0, $barbero->barberosSedes()->count());
    }

    public function test_no_se_borran_usuarios_ni_sedes_con_citas()
    {
        $cliente = User::where('email', 'cliente1@test.com')->first();
        $barbero = User::where('email', 'barbero01@barberia.com')->first();
        Cita::create([
            'cliente_id' => $cliente->id, 'barbero_id' => $barbero->id, 'sede_id' => 1,
            'fecha' => '2026-11-05', 'hora_inicio' => '10:00', 'hora_fin' => '10:30',
            'estado' => 'confirmada', 'metodo_pago' => 'efectivo',
            'monto_servicios' => 15, 'monto_productos' => 0, 'monto_total' => 15,
        ]);

        $this->como('admin@barberia.com');
        $this->deleteJson("/api/users/{$cliente->id}")->assertStatus(409);
        $this->deleteJson("/api/users/{$barbero->id}")->assertStatus(409);
        $this->deleteJson('/api/sedes/1')->assertStatus(409);
        $this->assertDatabaseCount('citas', 1);

        // Sin citas sí se puede
        $this->deleteJson('/api/sedes/4')->assertOk();
        $libre = User::where('email', 'cliente5@test.com')->first();
        $this->deleteJson("/api/users/{$libre->id}")->assertOk();
        // Y no puede borrarse a sí mismo
        $admin = User::where('email', 'admin@barberia.com')->first();
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(400);
    }

    public function test_nuevo_barbero_queda_disponible_para_reservas_en_su_sede()
    {
        $this->como('admin@barberia.com');
        $r = $this->postJson('/api/users', [
            'name' => 'Barbero Nuevo', 'email' => 'nuevo@barberia.com', 'password' => 'password123',
            'role' => 'barbero', 'sede_id' => 3,
        ])->assertStatus(201);

        $ids = array_column($this->getJson('/api/sedes/3/barberos')->json(), 'id');
        $this->assertContains($r->json('user.id'), $ids);
    }
}
