<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrega extends Model
{
    use HasFactory;

    protected $table = 'entregas';

    protected $fillable = [
        'contrato_id',
        'fecha_programada',
        'fecha_entrega',
        'estado',
        'condicion_inmueble',
        'items_entregados',
        'observaciones',
        'entregado_por',
        'recibido_por',
        'url_acta',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
