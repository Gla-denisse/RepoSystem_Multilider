<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectorUrbano extends Model
{
    use HasFactory;

    protected $table = 'sectores_urbanos';

    protected $fillable = ['distrito_id', 'nombre', 'tipo', 'uv', 'manzano', 'estado'];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public function propiedades()
    {
        return $this->hasMany(Propiedad::class, 'sector_urbano_id');
    }
}
