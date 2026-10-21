<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'comprobante_pago_data',
        'comprobante_pago_mime',
        'recordatorio_24h_enviado_at',
        'recordatorio_2h_enviado_at',
        'secuencia_calendario',
    ];

    // El archivo del comprobante no viaja en los listados (puede pesar varios MB).
    protected $hidden = [
        'comprobante_pago_data',
    ];

    protected $appends = [
        'tiene_comprobante',
        'enlace_calendario',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'monto_servicios' => 'decimal:2',
        'monto_productos' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    public function getTieneComprobanteAttribute()
    {
        return !empty($this->attributes['comprobante_pago_mime']);
    }

    /** Enlace de Google Calendar (no aplica a citas canceladas ni si faltan relaciones). */
    public function getEnlaceCalendarioAttribute()
    {
        if ($this->estado === 'cancelada' || !$this->relationLoaded('sede') || !$this->relationLoaded('barbero') || !$this->relationLoaded('servicios')) {
            return null;
        }

        return \App\Services\CalendarioCita::enlaceGoogle($this);
    }

    public function getHoraInicioAttribute($value)
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getHoraFinAttribute($value)
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    /** Fecha y hora de inicio como Carbon en la zona horaria de la aplicación. */
    public function inicio()
    {
        return Carbon::parse($this->fecha->format('Y-m-d') . ' ' . $this->hora_inicio, config('app.timezone'));
    }

    public function fin()
    {
        return Carbon::parse($this->fecha->format('Y-m-d') . ' ' . $this->hora_fin, config('app.timezone'));
    }

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
