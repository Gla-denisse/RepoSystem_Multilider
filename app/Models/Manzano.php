<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manzano extends Model
{
    use HasFactory;

    protected $table = 'manzanos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado'
    ];

    // Relación: Un manzano contiene muchas propiedades
    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'manzano_id');
    }
}