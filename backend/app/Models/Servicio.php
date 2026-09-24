<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'duracion_minutos',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
    ];

    // Relaciones
    public function citas()
    {
        return $this->belongsToMany(Cita::class, 'cita_servicio', 'servicio_id', 'cita_id')
                    ->withPivot('precio_momento');
    }
}
