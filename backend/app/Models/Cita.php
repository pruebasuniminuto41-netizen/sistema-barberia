<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'barbero_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'metodo_pago',
        'comprobante_pago_url',
        'monto_servicios',
        'monto_productos',
        'monto_total',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_servicios' => 'decimal:2',
        'monto_productos' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function barbero()
    {
        return $this->belongsTo(User::class, 'barbero_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'cita_servicio', 'cita_id', 'servicio_id')
                    ->withPivot('precio_momento');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'cita_producto', 'cita_id', 'producto_id')
                    ->withPivot('cantidad', 'precio_unitario');
    }
}
