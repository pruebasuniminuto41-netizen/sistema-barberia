<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sede;
use App\Models\User;
use App\Models\Cita;

class SedeController extends Controller
{
    public function index()
    {
        $sedes = Sede::with(['barberos', 'supervisores'])->get();
        return response()->json($sedes);
    }

    public function show($id)
    {
        $sede = Sede::with(['barberos', 'supervisores', 'productos', 'citas'])->findOrFail($id);
        return response()->json($sede);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string',
            'telefono' => 'required|string|max:20',
        ]);

        $sede = Sede::create($request->all());

        return response()->json([
            'message' => 'Sede creada exitosamente',
            'sede' => $sede,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $sede = Sede::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string',
            'telefono' => 'sometimes|string|max:20',
        ]);

        $sede->update($request->all());

        return response()->json([
            'message' => 'Sede actualizada',
            'sede' => $sede,
        ]);
    }

    public function destroy($id)
    {
        $sede = Sede::findOrFail($id);

        // Las citas se borran en cascada con la sede: se protege el historial de ingresos.
        if (Cita::where('sede_id', $sede->id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la sede: tiene citas registradas.',
            ], 409);
        }

        $sede->delete();

        return response()->json([
            'message' => 'Sede eliminada',
        ]);
    }

    public function barberos($id)
    {
        $sede = Sede::findOrFail($id);
        $barberos = $sede->barberos;
        return response()->json($barberos);
    }

    public function agregarBarbero(Request $request, $id)
    {
        $sede = Sede::findOrFail($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        if (!$user->hasRole('barbero')) {
            return response()->json(['message' => 'El usuario no tiene rol de barbero'], 400);
        }

        $sede->barberos()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'message' => 'Barbero agregado a la sede',
            'sede' => $sede->load('barberos'),
        ]);
    }

    public function supervisores($id)
    {
        $sede = Sede::findOrFail($id);
        $supervisores = $sede->supervisores;
        return response()->json($supervisores);
    }
}
