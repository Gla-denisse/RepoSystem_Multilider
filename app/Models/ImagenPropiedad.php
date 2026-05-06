<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenPropiedad extends Model
{
    use HasFactory;

    protected $table = 'imagenes_propiedades';

    protected $fillable = [
        'propiedad_id',
        'url',
        'es_principal',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }
}