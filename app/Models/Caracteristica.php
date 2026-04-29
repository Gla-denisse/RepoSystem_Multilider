<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caracteristica extends Model
{
    use HasFactory;

    protected $table = 'caracteristicas';

    protected $fillable = [
        'nombre',
        'tipo'
    ];

    // Relación de Muchos a Muchos con Propiedades (La usaremos en el próximo paso)
    public function propiedades()
    {
        return $this->belongsToMany(Propiedad::class, 'caracteristica_propiedad');
    }
}