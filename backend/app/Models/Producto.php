<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'stock_por_sede',
        'sede_id',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'stock_por_sede' => 'integer',
    ];

    // Relaciones
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function citas()
    {
        return $this->belongsToMany(Cita::class, 'cita_producto', 'producto_id', 'cita_id')
                    ->withPivot('cantidad', 'precio_unitario');
    }
}
