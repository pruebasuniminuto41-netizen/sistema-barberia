<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\Sede;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $productos = [
            [
                'nombre' => 'Cerveza Importada',
                'descripcion' => 'Cerveza premium servida fría',
                'precio' => 5.00,
                'stock_por_sede' => 50,
            ],
            [
                'nombre' => 'Refresco',
                'descripcion' => 'Bebida gaseosa assorted',
                'precio' => 2.00,
                'stock_por_sede' => 100,
            ],
            [
                'nombre' => 'Agua Mineral',
                'descripcion' => 'Agua embotellada natural',
                'precio' => 1.50,
                'stock_por_sede' => 100,
            ],
            [
                'nombre' => 'Pomada Barber',
                'descripcion' => 'Pomada para peinar cabello',
                'precio' => 15.00,
                'stock_por_sede' => 30,
            ],
            [
                'nombre' => 'Aceite para Barba',
                'descripcion' => 'Aceite hidratante para barba',
                'precio' => 12.00,
                'stock_por_sede' => 25,
            ],
            [
                'nombre' => 'Gel Fijador',
                'descripcion' => 'Gel para mantener el estilo',
                'precio' => 8.00,
                'stock_por_sede' => 40,
            ],
            [
                'nombre' => 'Shampoo Premium',
                'descripcion' => 'Shampoo profesional para salón',
                'precio' => 18.00,
                'stock_por_sede' => 20,
            ],
            [
                'nombre' => 'Acondicionador',
                'descripcion' => 'Acondicionador reparador',
                'precio' => 16.00,
                'stock_por_sede' => 20,
            ],
            [
                'nombre' => 'Cera Modelo',
                'descripcion' => 'Cera para modelar acabado mate',
                'precio' => 14.00,
                'stock_por_sede' => 35,
            ],
            [
                'nombre' => 'Spray Fijador',
                'descripcion' => 'Spray de fijación fuerte',
                'precio' => 10.00,
                'stock_por_sede' => 45,
            ],
        ];

        $sedes = Sede::all();

        foreach ($sedes as $sede) {
            foreach ($productos as $producto) {
                Producto::firstOrCreate([
                    'nombre' => $producto['nombre'],
                    'sede_id' => $sede->id,
                ], [
                    'descripcion' => $producto['descripcion'],
                    'precio' => $producto['precio'],
                    'stock_por_sede' => $producto['stock_por_sede'],
                ]);
            }
        }
    }
}
