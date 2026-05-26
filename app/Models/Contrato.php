<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'nota_venta_id',
        'codigo_contrato',
        'fecha_emision',
        'fecha_firma',
        'tipo_venta',
        'url_doc',
        'estado',
    ];

    public function notaVenta()
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function entrega()
    {
        return $this->hasOne(Entrega::class, 'contrato_id');
    }
}
