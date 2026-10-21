<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\User;
use App\Services\CalendarioCita;
use App\Services\NotificadorCitas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CitaController extends Controller
{
    /** Estados a los que puede pasar el personal (barbero, supervisor, admin). */
    private const TRANSICIONES = [
        'pendiente' => ['confirmada', 'en_proceso', 'cancelada'],
        'pendiente_validacion' => ['cancelada'],
        'confirmada' => ['en_proceso', 'cancelada'],
        'en_proceso' => ['completada'],
        'completada' => [],
        'cancelada' => [],
    ];

    private const RELACIONES = ['cliente', 'barbero', 'sede', 'servicios', 'productos'];

    // ---------------------------------------------------------------- listados

    public function index(Request $request)
    {
        $user = $request->user();
        $consulta = Cita::with(self::RELACIONES);

        if ($user->hasRole('admin')) {
            // todas
        } elseif ($user->hasRole('supervisor')) {
            $consulta->whereIn('sede_id', $this->sedesDelSupervisor($user));
        } elseif ($user->hasRole('barbero')) {
            $consulta->where('barbero_id', $user->id);
        } else {
            $consulta->where('cliente_id', $user->id);
        }

        return response()->json(
            $consulta->orderBy('fecha', 'desc')->orderBy('hora_inicio', 'desc')->get()
        );
    }

    public function show(Request $request, $id)
    {
        $cita = Cita::with(self::RELACIONES)->findOrFail($id);
        $this->autorizarVer($request->user(), $cita);

        return response()->json($cita);
    }

    /** Horarios de inicio libres de un barbero en una fecha, según la duración de los servicios. */
    public function disponibilidad(Request $request, $barberoId)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'duracion' => 'required|integer|min:5|max:600',
        ], $this->mensajes(), $this->atributos());

        $barbero = User::findOrFail($barberoId);
        if (!$barbero->hasRole('barbero')) {
            return response()->json(['message' => 'El usuario indicado no es un barbero'], 422);
        }

        $zona = config('app.timezone');
        $fecha = $request->fecha;
        $duracion = (int) $request->duracion;
        $paso = max(5, config('barberia.intervalo_minutos'));
        $ahora = Carbon::now($zona);

        $apertura = Carbon::parse("$fecha " . config('barberia.hora_apertura'), $zona);
        $cierre = Carbon::parse("$fecha " . config('barberia.hora_cierre'), $zona);

        $ocupadas = Cita::where('barbero_id', $barbero->id)
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->map(function ($cita) {
                return [$cita->inicio(), $cita->fin()];
            });

        $horarios = [];
        for ($inicio = $apertura->copy(); $inicio->copy()->addMinutes($duracion)->lte($cierre); $inicio->addMinutes($paso)) {
            $fin = $inicio->copy()->addMinutes($duracion);

            if ($inicio->lte($ahora)) {
                continue;
            }

            $solapa = $ocupadas->contains(function ($rango) use ($inicio, $fin) {
                return $inicio->lt($rango[1]) && $fin->gt($rango[0]);
            });

            if (!$solapa) {
                $horarios[] = $inicio->format('H:i');
            }
        }

        return response()->json([
            'fecha' => $fecha,
            'apertura' => config('barberia.hora_apertura'),
            'cierre' => config('barberia.hora_cierre'),
            'horarios' => $horarios,
        ]);
    }

    // ---------------------------------------------------------------- reserva

    public function store(Request $request)
    {
        // Manejar diferentes formatos de servicios
        $servicios = $request->servicios;
        if (is_string($servicios)) {
            $request->merge(['servicios' => json_decode($servicios, true)]);
        }

        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'barbero_id' => 'required|exists:users,id',
            'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'servicios' => 'required|array|min:1',
            'servicios.*.id' => 'required|exists:servicios,id',
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'comprobante_pago' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], $this->mensajes(), $this->atributos());

        if ($request->metodo_pago === 'transferencia' && !$request->hasFile('comprobante_pago')) {
            return response()->json([
                'message' => 'Debes adjuntar el comprobante de la transferencia',
                'errors' => ['comprobante_pago' => ['Debes adjuntar el comprobante de la transferencia']],
            ], 422);
        }

        $cliente = $request->user();
        $barbero = User::findOrFail($request->barbero_id);

        if (!$barbero->hasRole('barbero') || !$barbero->barberosSedes()->where('sedes.id', $request->sede_id)->exists()) {
            return $this->rechazar('El barbero seleccionado no atiende en esa sede', 422);
        }

        $zona = config('app.timezone');
        $serviciosDb = Servicio::whereIn('id', collect($request->servicios)->pluck('id')->unique())->get();
        $duracion = $serviciosDb->sum('duracion_minutos');

        $inicio = Carbon::parse("{$request->fecha} {$request->hora_inicio}", $zona);
        $fin = $inicio->copy()->addMinutes($duracion);

        if ($inicio->lte(Carbon::now($zona))) {
            return $this->rechazar('La hora elegida ya pasó. Selecciona un horario futuro', 422);
        }

        $apertura = Carbon::parse("{$request->fecha} " . config('barberia.hora_apertura'), $zona);
        $cierre = Carbon::parse("{$request->fecha} " . config('barberia.hora_cierre'), $zona);
        if ($inicio->lt($apertura) || $fin->gt($cierre)) {
            return $this->rechazar(
                'Atendemos de ' . config('barberia.hora_apertura') . ' a ' . config('barberia.hora_cierre')
                . '. Los servicios elegidos deben terminar antes del cierre',
                422
            );
        }

        $horaInicio = $inicio->format('H:i');
        $horaFin = $fin->format('H:i');

        $cita = DB::transaction(function () use ($request, $cliente, $barbero, $serviciosDb, $horaInicio, $horaFin) {
            // Bloquea al barbero para que dos reservas simultáneas no ocupen el mismo horario.
            User::whereKey($barbero->id)->lockForUpdate()->first();

            $solapa = function ($consulta) use ($request, $horaInicio, $horaFin) {
                return $consulta->whereDate('fecha', $request->fecha)
                    ->where('estado', '!=', 'cancelada')
                    ->where('hora_inicio', '<', $horaFin)
                    ->where('hora_fin', '>', $horaInicio);
            };

            if ($solapa(Cita::where('barbero_id', $barbero->id))->exists()) {
                return 'barbero';
            }
            if ($solapa(Cita::where('cliente_id', $cliente->id))->exists()) {
                return 'cliente';
            }

            // Efectivo: el horario queda confirmado de inmediato. Transferencia: se confirma al validar el comprobante.
            $estado = $request->metodo_pago === 'transferencia' ? 'pendiente_validacion' : 'confirmada';

            $datos = [
                'cliente_id' => $cliente->id,
                'barbero_id' => $barbero->id,
                'sede_id' => $request->sede_id,
                'fecha' => $request->fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'estado' => $estado,
                'metodo_pago' => $request->metodo_pago,
                'monto_servicios' => $serviciosDb->sum('precio'),
                'monto_productos' => 0,
                'monto_total' => $serviciosDb->sum('precio'),
            ];

            if ($request->hasFile('comprobante_pago')) {
                $archivo = $request->file('comprobante_pago');
                // Se guarda en la base de datos: el disco del servidor no es permanente.
                $datos['comprobante_pago_data'] = base64_encode(file_get_contents($archivo->getRealPath()));
                $datos['comprobante_pago_mime'] = $archivo->getMimeType();
            }

            $cita = Cita::create($datos);

            foreach ($serviciosDb as $servicio) {
                $cita->servicios()->attach($servicio->id, ['precio_momento' => $servicio->precio]);
            }

            return $cita;
        });

        if ($cita === 'barbero') {
            return $this->rechazar('El barbero ya tiene una cita en este horario', 409);
        }
        if ($cita === 'cliente') {
            return $this->rechazar('Ya tienes otra cita en ese horario', 409);
        }

        if ($cita->estado === 'confirmada') {
            // Confirmación inmediata al cliente; el barbero recibe el aviso de cita nueva.
            NotificadorCitas::enviar($cita, 'confirmada', true);
        } else {
            NotificadorCitas::enviar($cita, 'recibida');
        }

        return response()->json([
            'message' => $cita->estado === 'confirmada'
                ? 'Cita confirmada. Te enviamos el detalle por correo'
                : 'Reserva recibida. Te confirmaremos por correo cuando validemos tu comprobante',
            'cita' => $cita->fresh()->load(self::RELACIONES),
            'enlace_calendario' => CalendarioCita::enlaceGoogle($cita),
        ], 201);
    }

    // ---------------------------------------------------------------- cambios de estado

    public function update(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $user = $request->user();

        $this->autorizarVer($user, $cita);

        $request->validate([
            'estado' => 'sometimes|in:pendiente,confirmada,en_proceso,completada,cancelada',
            'notas' => 'sometimes|nullable|string|max:1000',
        ], $this->mensajes(), $this->atributos());

        $nuevoEstado = $request->input('estado');
        $cambioEstado = $nuevoEstado !== null && $nuevoEstado !== $cita->estado;

        if ($cambioEstado) {
            if ($user->hasRole('cliente')) {
                if ($nuevoEstado !== 'cancelada') {
                    return $this->rechazar('Solo puedes cancelar tu cita', 403);
                }
                if (!in_array($cita->estado, ['pendiente', 'pendiente_validacion', 'confirmada'])) {
                    return $this->rechazar('Esta cita ya no se puede cancelar', 422);
                }
                $limite = $cita->inicio()->subHours(config('barberia.horas_min_cancelacion'));
                if (Carbon::now(config('app.timezone'))->gt($limite)) {
                    return $this->rechazar(
                        'Solo puedes cancelar con al menos ' . config('barberia.horas_min_cancelacion') . ' horas de anticipación. Comunícate con la barbería',
                        422
                    );
                }
            } else {
                $permitidos = self::TRANSICIONES[$cita->estado] ?? [];
                if (!in_array($nuevoEstado, $permitidos)) {
                    return $this->rechazar("No se puede pasar una cita de '{$cita->estado}' a '{$nuevoEstado}'", 422);
                }
            }
        }

        $cita->update($request->only(['estado', 'notas']));

        if ($cambioEstado) {
            if ($nuevoEstado === 'cancelada') {
                NotificadorCitas::enviar($cita, 'cancelada');
            } elseif ($nuevoEstado === 'confirmada') {
                NotificadorCitas::enviar($cita, 'confirmada');
            }
        }

        return response()->json([
            'message' => 'Cita actualizada',
            'cita' => $cita->fresh()->load('servicios', 'productos'),
        ]);
    }

    public function validarComprobante(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $this->autorizarSede($request->user(), $cita->sede_id);

        if ($cita->metodo_pago !== 'transferencia') {
            return $this->rechazar('Esta cita no requiere validación de comprobante', 400);
        }
        if ($cita->estado !== 'pendiente_validacion') {
            return $this->rechazar('Esta cita ya fue procesada', 422);
        }

        $request->validate([
            'estado' => 'required|in:confirmada,cancelada',
        ], $this->mensajes(), $this->atributos());

        $cita->update(['estado' => $request->estado]);
        NotificadorCitas::enviar($cita, $request->estado === 'confirmada' ? 'confirmada' : 'cancelada');

        return response()->json([
            'message' => $request->estado === 'confirmada'
                ? 'Comprobante validado y cita confirmada'
                : 'Comprobante rechazado y cita cancelada',
            'cita' => $cita->fresh(),
        ]);
    }

    // ---------------------------------------------------------------- productos (punto de venta)

    public function agregarProductos(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $this->autorizarSede($request->user(), $cita->sede_id);

        if (in_array($cita->estado, ['cancelada', 'pendiente_validacion'])) {
            return $this->rechazar('No se pueden agregar productos a una cita ' . str_replace('_', ' ', $cita->estado), 422);
        }

        // La pantalla envía producto_id; la API también acepta id.
        $items = collect($request->input('productos', []))->map(function ($p) {
            return ['id' => $p['id'] ?? $p['producto_id'] ?? null, 'cantidad' => $p['cantidad'] ?? null];
        })->all();
        $request->merge(['productos' => $items]);

        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|integer|min:1',
        ], $this->mensajes(), $this->atributos());

        $resultado = DB::transaction(function () use ($items, $cita) {
            $subtotal = 0;

            foreach ($items as $item) {
                $producto = Producto::whereKey($item['id'])->lockForUpdate()->first();

                if ((int) $producto->sede_id !== (int) $cita->sede_id) {
                    return "El producto '{$producto->nombre}' no pertenece a la sede de la cita";
                }
                if ($producto->stock_por_sede < $item['cantidad']) {
                    return "Stock insuficiente de '{$producto->nombre}' (disponible: {$producto->stock_por_sede})";
                }

                $existente = $cita->productos()->where('productos.id', $producto->id)->first();
                if ($existente) {
                    $cita->productos()->updateExistingPivot($producto->id, [
                        'cantidad' => $existente->pivot->cantidad + $item['cantidad'],
                    ]);
                } else {
                    $cita->productos()->attach($producto->id, [
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $producto->precio,
                    ]);
                }

                $producto->decrement('stock_por_sede', $item['cantidad']);
                $subtotal += $producto->precio * $item['cantidad'];
            }

            $montoProductos = (float) $cita->monto_productos + $subtotal;
            $cita->update([
                'monto_productos' => $montoProductos,
                'monto_total' => (float) $cita->monto_servicios + $montoProductos,
            ]);

            return true;
        });

        if ($resultado !== true) {
            return $this->rechazar($resultado, 422);
        }

        return response()->json([
            'message' => 'Productos agregados exitosamente',
            'cita' => $cita->fresh()->load('productos'),
        ]);
    }

    // ---------------------------------------------------------------- archivos

    public function comprobante(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);
        $this->autorizarVer($request->user(), $cita);

        if (!$cita->comprobante_pago_data) {
            return $this->rechazar('Esta cita no tiene comprobante', 404);
        }

        return response(base64_decode($cita->comprobante_pago_data), 200, [
            'Content-Type' => $cita->comprobante_pago_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="comprobante-cita-' . $cita->id . '"',
        ]);
    }

    public function calendario(Request $request, $id)
    {
        $cita = Cita::with(['sede', 'barbero', 'servicios'])->findOrFail($id);
        $this->autorizarVer($request->user(), $cita);

        return response(CalendarioCita::ics($cita), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="cita-' . $cita->id . '.ics"',
        ]);
    }

    // ---------------------------------------------------------------- métricas

    public function metricas()
    {
        // Ingresos por sede
        $ingresosPorSede = DB::table('citas')->join('sedes', 'citas.sede_id', '=', 'sedes.id')
            ->selectRaw('sedes.nombre as sede, SUM(citas.monto_total) as total')
            ->where('citas.estado', 'completada')
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        // Ingresos por barbero
        $ingresosPorBarbero = DB::table('citas')->join('users', 'citas.barbero_id', '=', 'users.id')
            ->selectRaw('users.name as barbero, SUM(citas.monto_total) as total')
            ->where('citas.estado', 'completada')
            ->groupBy('users.id', 'users.name')
            ->get();

        // Productos más vendidos
        $productosMasVendidos = DB::table('cita_producto')
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

    // ---------------------------------------------------------------- utilidades

    private function sedesDelSupervisor(User $user): array
    {
        $ids = $user->supervisoresSedes()->pluck('sedes.id')->all();

        if (empty($ids) && $user->sede_id) {
            $ids = [$user->sede_id];
        }

        return array_map('intval', $ids);
    }

    /** Lanza 403 si el usuario no es admin ni supervisor de esa sede. */
    private function autorizarSede(User $user, $sedeId): void
    {
        if ($user->hasRole('admin')) {
            return;
        }
        if ($user->hasRole('supervisor') && in_array((int) $sedeId, $this->sedesDelSupervisor($user), true)) {
            return;
        }

        abort(response()->json(['message' => 'No autorizado'], 403));
    }

    /** Cliente y barbero solo ven lo suyo; el supervisor, lo de su sede; el admin, todo. */
    private function autorizarVer(User $user, Cita $cita): void
    {
        if ($user->hasRole('admin')) {
            return;
        }
        if ($user->hasRole('supervisor') && in_array((int) $cita->sede_id, $this->sedesDelSupervisor($user), true)) {
            return;
        }
        if ($user->hasRole('barbero') && (int) $cita->barbero_id === (int) $user->id) {
            return;
        }
        if ($user->hasRole('cliente') && (int) $cita->cliente_id === (int) $user->id) {
            return;
        }

        abort(response()->json(['message' => 'No autorizado'], 403));
    }

    private function rechazar(string $mensaje, int $codigo)
    {
        return response()->json(['message' => $mensaje], $codigo);
    }

    private function mensajes(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio',
            'exists' => 'El valor de :attribute no es válido',
            'date_format' => 'El campo :attribute tiene un formato no válido',
            'after_or_equal' => 'La fecha no puede ser anterior a hoy',
            'array' => 'El campo :attribute no es válido',
            'min.array' => 'Selecciona al menos un servicio',
            'in' => 'El valor de :attribute no es válido',
            'integer' => 'El campo :attribute debe ser un número entero',
            'mimes' => 'El comprobante debe ser una imagen (jpg, png) o un PDF',
            'max.file' => 'El comprobante no puede superar 2 MB',
            'comprobante_pago.file' => 'El comprobante no es un archivo válido',
        ];
    }

    private function atributos(): array
    {
        return [
            'sede_id' => 'sede',
            'barbero_id' => 'barbero',
            'fecha' => 'fecha',
            'hora_inicio' => 'hora',
            'servicios' => 'servicios',
            'servicios.*.id' => 'servicio',
            'metodo_pago' => 'método de pago',
            'comprobante_pago' => 'comprobante',
            'productos' => 'productos',
            'productos.*.id' => 'producto',
            'productos.*.cantidad' => 'cantidad',
            'estado' => 'estado',
            'duracion' => 'duración',
        ];
    }
}
