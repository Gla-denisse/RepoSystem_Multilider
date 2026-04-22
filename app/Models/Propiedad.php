<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    use HasFactory;

    protected $table = 'propiedades';

    protected $fillable = [
        'propietario_id',
        'manzano_id',
        'ubicacion_id',
        'tipo',
        'codigo',
        'precio_venta',
        'direccion',
        'nro_lote',
        'superficie_m2',
        'colinda_norte',
        'colinda_sur',
        'colinda_este',
        'colinda_oeste',
        'estado',
        'activo'
    ];

    // Relaciones: Muchas propiedades pertenecen a un Propietario
    public function propietario()
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    // Relaciones: Muchas propiedades pertenecen a un Manzano
    public function manzano()
    {
        return $this->belongsTo(Manzano::class, 'manzano_id');
    }

    // Relación 1 a 1: Una propiedad tiene una ubicación
    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }
}