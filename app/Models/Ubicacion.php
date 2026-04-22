<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = [
        'referencia',
        'url_maps',
        'longitud',
        'latitud'
    ];

    // Relación inversa 1 a 1: Esta ubicación pertenece a una propiedad
    public function propiedad()
    {
        return $this->hasOne(Propiedad::class, 'ubicacion_id');
    }
}