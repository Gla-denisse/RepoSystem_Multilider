<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    use HasFactory;

    protected $table = 'zonas';

    protected $fillable = [
        'ciudad_id',
        'nombre'
    ];

    // Relación Inversa: Una zona pertenece a una Ciudad
    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    // Relación: Una zona tendrá muchas propiedades (Se usará más adelante)
    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'zona_id');
    }
}