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
            return; // ya sembrada: evita duplicados en cada despliegue
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
