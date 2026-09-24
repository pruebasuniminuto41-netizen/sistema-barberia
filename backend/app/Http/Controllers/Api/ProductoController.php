<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Sede;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $productos = Producto::with('sede')->get();
        } elseif ($user->hasRole('supervisor')) {
            $sedeId = $user->supervisoresSedes()->first()->id;
            $productos = Producto::where('sede_id', $sedeId)->with('sede')->get();
        } else {
            // Clientes y barberos ven productos de su sede
            $sedeId = $user->sede_id;
            $productos = Producto::where('sede_id', $sedeId)->with('sede')->get();
        }

        return response()->json($productos);
    }

    public function show($id)
    {
        $producto = Producto::with('sede')->findOrFail($id);
        return response()->json($producto);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'stock_por_sede' => 'required|integer|min:0',
            'sede_id' => 'required|exists:sedes,id',
        ]);

        $producto = Producto::create($request->all());

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'producto' => $producto->load('sede'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'sometimes|numeric|min:0',
            'stock_por_sede' => 'sometimes|integer|min:0',
            'sede_id' => 'sometimes|exists:sedes,id',
        ]);

        $producto->update($request->all());

        return response()->json([
            'message' => 'Producto actualizado',
            'producto' => $producto->load('sede'),
        ]);
    }

    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado',
        ]);
    }
}
