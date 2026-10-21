<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (\App\Models\User::count() > 0) {
            // ya sembrada: solo completa productos (idempotente) y evita duplicados
            $this->call([ProductoSeeder::class]);
            return;
        }

        $this->call([
            RoleSeeder::class,
            SedeSeeder::class,
            UserSeeder::class,
            ServicioSeeder::class,
            ProductoSeeder::class,
            CitaSeeder::class,
        ]);
    }
}
