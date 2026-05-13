<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    use HasFactory;

    protected $table = 'distritos';

    protected $fillable = ['ciudad_id', 'nombre', 'estado'];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    public function sectoresUrbanos()
    {
        return $this->hasMany(SectorUrbano::class, 'distrito_id');
    }
}
