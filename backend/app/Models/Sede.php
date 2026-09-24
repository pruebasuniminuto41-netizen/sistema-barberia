<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
    ];

    // Relaciones
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }

    public function barberos()
    {
        return $this->belongsToMany(User::class, 'barberos_sedes', 'sede_id', 'user_id');
    }

    public function supervisores()
    {
        return $this->belongsToMany(User::class, 'supervisores_sedes', 'sede_id', 'user_id');
    }
}
