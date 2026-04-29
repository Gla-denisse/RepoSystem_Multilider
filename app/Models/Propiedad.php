<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    use HasFactory;

    protected $table = 'propiedades';

    protected $fillable = [
        'propietario_id', 'zona_id', 'ubicacion_id', 'codigo', 'tipo',
        'precio_venta', 'moneda', 'superficie_m2', 'superficie_construida_m2',
        'frente_mts', 'fondo_mts', 'habitaciones', 'banos', 'es_esquina',
        'direccion', 'nro_lote', 'colinda_norte', 'colinda_sur', 
        'colinda_este', 'colinda_oeste', 'estado', 'activo'
    ];

    // Relación: Muchas propiedades pertenecen a una Zona
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    // Relación: Muchas propiedades pertenecen a un Propietario
    public function propietario()
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    // Relación 1 a 1 con Ubicación (GPS)
    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function caracteristicas()
    {
        return $this->belongsToMany(Caracteristica::class, 'caracteristica_propiedad');
    }
}