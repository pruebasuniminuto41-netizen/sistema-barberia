<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sede;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sedes = [
            [
                'nombre' => 'Barbería Centro',
                'direccion' => 'Calle Principal #123, Centro',
                'telefono' => '555-0101',
            ],
            [
                'nombre' => 'Barbería Norte',
                'direccion' => 'Avenida Norte #456, Zona Norte',
                'telefono' => '555-0102',
            ],
            [
                'nombre' => 'Barbería Sur',
                'direccion' => 'Calle Sur #789, Zona Sur',
                'telefono' => '555-0103',
            ],
            [
                'nombre' => 'Barbería Este',
                'direccion' => 'Boulevard Este #321, Zona Este',
                'telefono' => '555-0104',
            ],
        ];

        foreach ($sedes as $sede) {
            Sede::firstOrCreate($sede);
        }
    }
}
