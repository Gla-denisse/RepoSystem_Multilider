<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Propietario extends Model
{
    use HasFactory;

    protected $table = 'propietarios';

    protected $fillable = [
        'ci',
        'lugar_expedicion',
        'nombre_completo',
        'telefono',
        'correo',
        'direccion',
        'estado'
    ];

    // Relación: Un propietario tiene muchas propiedades
    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'propietario_id');
    }
}