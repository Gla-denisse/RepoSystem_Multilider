<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propietario extends Model
{
    use HasFactory;

    protected $table = 'propietarios';

    protected $fillable = [
        'tipo',
        'ci',
        'lugar_expedicion',
        'nombre_completo',
        'nombre_empresa',
        'nit',
        'telefono',
        'correo',
        'direccion',
        'estado',
    ];

    public function propiedades()
    {
        return $this->belongsToMany(Propiedad::class, 'propiedad_propietario');
    }
}
