<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Egreso;

class Asesor extends Model
{
    use HasFactory;

    protected $table = 'asesores';

    protected $fillable = [
        'user_id',
        'nombre_completo',
        'telefono',
        'correo',
        'direccion',
        'foto',
        'estado',
        'porcentaje_comision'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function egresos()
    {
        return $this->hasMany(Egreso::class, 'asesor_id');
    }
}