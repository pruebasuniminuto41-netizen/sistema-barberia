<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sede;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@barberia.com',
        ], [
            'name' => 'Administrador General',
            'password' => Hash::make('password'),
            'phone' => '555-0000',
        ]);
        $admin->assignRole('admin');

        // Sedes
        $sedes = Sede::all();

        // Supervisores (1 por sede)
        foreach ($sedes as $index => $sede) {
            $supervisor = User::firstOrCreate([
                'email' => "supervisor{$index}@barberia.com",
            ], [
                'name' => "Supervisor {$sede->nombre}",
                'password' => Hash::make('password'),
                'phone' => "555-02{$index}",
                'sede_id' => $sede->id,
            ]);
            $supervisor->assignRole('supervisor');
            $supervisor->supervisoresSedes()->attach($sede->id);
        }

        // Barberos (2 por sede)
        foreach ($sedes as $sedeIndex => $sede) {
            for ($i = 1; $i <= 2; $i++) {
                $barbero = User::firstOrCreate([
                    'email' => "barbero{$sedeIndex}{$i}@barberia.com",
                ], [
                    'name' => "Barbero {$i} - {$sede->nombre}",
                    'password' => Hash::make('password'),
                    'phone' => "555-03{$sedeIndex}{$i}",
                    'sede_id' => $sede->id,
                ]);
                $barbero->assignRole('barbero');
                $barbero->barberosSedes()->attach($sede->id);
            }
        }

        // Clientes de prueba
        for ($i = 1; $i <= 5; $i++) {
            $cliente = User::firstOrCreate([
                'email' => "cliente{$i}@test.com",
            ], [
                'name' => "Cliente Test {$i}",
                'password' => Hash::make('password'),
                'phone' => "555-04{$i}",
            ]);
            $cliente->assignRole('cliente');
        }
    }
}
