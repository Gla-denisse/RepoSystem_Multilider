<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampanaCorreo extends Model
{
    protected $table = 'campanas_correo';

    protected $fillable = [
        'user_id',
        'asunto',
        'mensaje',
        'tipo_destinatario',
        'filtros',
        'total_destinatarios',
        'total_enviados',
        'total_fallidos',
        'estado',
    ];

    protected $casts = [
        'filtros' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
