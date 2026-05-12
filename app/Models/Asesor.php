<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // Relación Inversa: Un asesor pertenece a un usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}