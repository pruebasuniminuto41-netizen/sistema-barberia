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
