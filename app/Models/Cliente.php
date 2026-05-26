<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'user_id',
        'ci',
        'lugar_expedicion',
        'nombre_completo',
        'telefono',
        'correo',
        'direccion',
        'estado'
    ];

    // Un cliente pertenece a un usuario (cuenta de acceso)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notasVentas()
    {
        return $this->hasMany(NotaVenta::class, 'cliente_id');
    }
}