<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;

class ServicioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $servicios = [
            [
                'nombre' => 'Corte Clásico',
                'descripcion' => 'Corte de cabello tradicional con tijera y máquina',
                'precio' => 15.00,
                'duracion_minutos' => 30,
            ],
            [
                'nombre' => 'Corte Fade',
                'descripcion' => 'Corte degradado moderno con acabado perfecto',
                'precio' => 20.00,
                'duracion_minutos' => 45,
            ],
            [
                'nombre' => 'Barba Completa',
                'descripcion' => 'Afeitado y perfilado de barba con toalla caliente',
                'precio' => 12.00,
                'duracion_minutos' => 25,
            ],
            [
                'nombre' => 'Corte + Barba',
                'descripcion' => 'Combo corte de cabello y arreglo de barba',
                'precio' => 25.00,
                'duracion_minutos' => 60,
            ],
            [
                'nombre' => 'Lavado y Estilo',
                'descripcion' => 'Lavado profundo y estilizado con productos premium',
                'precio' => 10.00,
                'duracion_minutos' => 20,
            ],
            [
                'nombre' => 'Coloración',
                'descripcion' => 'Tinte o mechas según preferencia',
                'precio' => 40.00,
                'duracion_minutos' => 90,
            ],
            [
                'nombre' => 'Corte Infantil',
                'descripcion' => 'Corte especial para niños con paciencia y cuidado',
                'precio' => 12.00,
                'duracion_minutos' => 25,
            ],
            [
                'nombre' => 'Dreadlocks Maintenance',
                'descripcion' => 'Mantenimiento y retocado de dreadlocks',
                'precio' => 35.00,
                'duracion_minutos' => 75,
            ],
            [
                'nombre' => 'Perfilado de Cejas',
                'descripcion' => 'Diseño y perfilado de cejas con pinza y cera',
                'precio' => 8.00,
                'duracion_minutos' => 15,
            ],
            [
                'nombre' => 'Tratamiento Capilar',
                'descripcion' => 'Tratamiento profundo para cabello dañado',
                'precio' => 30.00,
                'duracion_minutos' => 45,
            ],
        ];

        foreach ($servicios as $servicio) {
            Servicio::firstOrCreate($servicio);
        }
    }
}
