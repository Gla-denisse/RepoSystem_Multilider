<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    use HasFactory;

    protected $table = 'propiedades';

    protected $fillable = [
        'sector_urbano_id', 'ubicacion_id', 'codigo', 'tipo',
        'precio_venta', 'moneda', 'superficie_m2', 'superficie_construida_m2',
        'frente_mts', 'fondo_mts', 'habitaciones', 'banos', 'es_esquina',
        'direccion', 'nro_lote', 'colinda_norte', 'colinda_sur',
        'colinda_este', 'colinda_oeste', 'estado', 'activo', 'es_destacado',
    ];

    public function propietarios()
    {
        return $this->belongsToMany(Propietario::class, 'propiedad_propietario');
    }

    public function sectorUrbano()
    {
        return $this->belongsTo(SectorUrbano::class, 'sector_urbano_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function caracteristicas()
    {
        return $this->belongsToMany(Caracteristica::class, 'caracteristica_propiedad');
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenPropiedad::class, 'propiedad_id');
    }
}
