<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cita;
use App\Models\User;
use App\Models\Sede;
use App\Models\Servicio;
use Carbon\Carbon;

class CitaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $citas = Cita::with(['cliente', 'barbero', 'sede', 'servicios', 'productos'])->get();
        } elseif ($user->hasRole('supervisor')) {
            $sedeId = $user->supervisoresSedes()->first()->id;
            $citas = Cita::with(['cliente', 'barbero', 'sede', 'servicios', 'productos'])
                          ->where('sede_id', $sedeId)->get();
        } elseif ($user->hasRole('barbero')) {
            $citas = Cita::with(['cliente', 'barbero', 'sede', 'servicios', 'productos'])
                          ->where('barbero_id', $user->id)->get();
        } else {
            $citas = Cita::with(['cliente', 'barbero', 'sede', 'servicios', 'productos'])
                          ->where('cliente_id', $user->id)->get();
        }

        return response()->json($citas);
    }

    public function store(Request $request)
    {
        // Manejar diferentes formatos de servicios
        $servicios = $request->servicios;
        if (is_string($servicios)) {
            $servicios = json_decode($servicios, true);
        }

        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'barbero_id' => 'required|exists:users,id',
            'fecha' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'servicios' => 'required|array',
            'servicios.*.id' => 'required|exists:servicios,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_pago' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Validar anti-solapamiento
        $fecha = $request->fecha;
        $horaInicio = $request->hora_inicio;
        $barberoId = $request->barbero_id;

        // Calcular duración total de servicios
        $serviciosIds = collect($servicios)->pluck('id');
        $servicios = Servicio::whereIn('id', $serviciosIds)->get();
        $duracionTotal = $servicios->sum('duracion_minutos');

        $horaFin = Carbon::parse($horaInicio)->addMinutes($duracionTotal)->format('H:i');

        // Verificar si existe solapamiento
        $solapamiento = Cita::where('barbero_id', $barberoId)
                            ->where('fecha', $fecha)
                            ->where('estado', '!=', 'cancelada')
                            ->where(function ($query) use ($horaInicio, $horaFin) {
                                $query->where(function ($q) use ($horaInicio, $horaFin) {
                                    $q->where('hora_inicio', '<', $horaFin)
                                      ->where('hora_fin', '>', $horaInicio);
                                });
                            })->exists();

        if ($solapamiento) {
            return response()->json([
                'message' => 'El barbero ya tiene una cita en este horario',
            ], 400);
        }

        // Manejar comprobante de pago
        $comprobanteUrl = null;
        if ($request->hasFile('comprobante_pago')) {
            $file = $request->file('comprobante_pago');
            $path = $file->store('comprobantes', 'public');
            $comprobanteUrl = $path;
        }

        // Calcular montos
        $montoServicios = $servicios->sum('precio');
        $estado = $request->metodo_pago === 'transferencia' ? 'pendiente_validacion' : 'pendiente';

        $cita = Cita::create([
            'cliente_id' => $request->user()->id,
            'barbero_id' => $barberoId,
            'sede_id' => $request->sede_id,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'estado' => $estado,
            'metodo_pago' => $request->metodo_pago,
            'comprobante_pago_url' => $comprobanteUrl,
            'monto_servicios' => $montoServicios,
            'monto_total' => $montoServicios,
        ]);

        // Asociar servicios
        foreach ($servicios as $servicio) {
            $cita->servicios()->attach($servicio->id, [
                'precio_momento' => $servicio->precio,
            ]);
        }

        return response()->json([
            'message' => 'Cita creada exitosamente',
            'cita' => $cita->load('servicios'),
        ], 201);
    }

    public function show($id)
    {
        $cita = Cita::with(['cliente', 'barbero', 'sede', 'servicios', 'productos'])
                    ->findOrFail($id);

        return response()->json($cita);
    }

    public function update(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $user = $request->user();

        // Validar permisos
        if ($user->hasRole('cliente') && $cita->cliente_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'estado' => 'sometimes|in:pendiente,confirmada,en_proceso,completada,cancelada',
            'notas' => 'sometimes|string',
        ]);

        $cita->update($request->only(['estado', 'notas']));

        return response()->json([
            'message' => 'Cita actualizada',
            'cita' => $cita->load('servicios', 'productos'),
        ]);
    }

    public function agregarProductos(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $user = $request->user();

        // Solo supervisores pueden agregar productos
        if (!$user->hasRole('supervisor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'productos' => 'required|array',
            'productos.*.id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|integer|min:1',
        ]);

        $montoProductos = 0;

        foreach ($request->productos as $productoData) {
            $productoId = $productoData['id'] ?? $productoData['producto_id'];
            $producto = \App\Models\Producto::findOrFail($productoId);
            $cantidad = $productoData['cantidad'];
            $subtotal = $producto->precio * $cantidad;
            $montoProductos += $subtotal;

            $cita->productos()->attach($producto->id, [
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio,
            ]);

            // Actualizar stock
            $producto->decrement('stock', $cantidad);
        }

        $cita->update([
            'monto_productos' => $cita->monto_productos + $montoProductos,
            'monto_total' => $cita->monto_servicios + $cita->monto_productos + $montoProductos,
        ]);

        return response()->json([
            'message' => 'Productos agregados exitosamente',
            'cita' => $cita->load('productos'),
        ]);
    }

    public function validarComprobante(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $user = $request->user();

        // Solo supervisores pueden validar comprobantes
        if (!$user->hasRole('supervisor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($cita->metodo_pago !== 'transferencia') {
            return response()->json(['message' => 'Esta cita no requiere validación de comprobante'], 400);
        }

        $request->validate([
            'estado' => 'required|in:confirmada,cancelada',
        ]);

        $cita->update(['estado' => $request->estado]);

        return response()->json([
            'message' => $request->estado === 'confirmada' ? 'Comprobante validado y cita confirmada' : 'Comprobante rechazado y cita cancelada',
            'cita' => $cita,
        ]);
    }

    public function metricas()
    {
        // Ingresos por sede
        $ingresosPorSede = Cita::join('sedes', 'citas.sede_id', '=', 'sedes.id')
            ->selectRaw('sedes.nombre as sede, SUM(citas.monto_total) as total')
            ->where('citas.estado', 'completada')
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        // Ingresos por barbero
        $ingresosPorBarbero = Cita::join('users', 'citas.barbero_id', '=', 'users.id')
            ->selectRaw('users.name as barbero, SUM(citas.monto_total) as total')
            ->where('citas.estado', 'completada')
            ->groupBy('users.id', 'users.name')
            ->get();

        // Productos más vendidos
        $productosMasVendidos = \DB::table('cita_producto')
            ->join('productos', 'cita_producto.producto_id', '=', 'productos.id')
            ->selectRaw('productos.nombre as producto, SUM(cita_producto.cantidad) as cantidad')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get();

        // Volumen de citas
        $volumenCitas = Cita::count();

        return response()->json([
            'ingresos_por_sede' => $ingresosPorSede,
            'ingresos_por_barbero' => $ingresosPorBarbero,
            'productos_mas_vendidos' => $productosMasVendidos,
            'volumen_citas' => $volumenCitas,
        ]);
    }
}
