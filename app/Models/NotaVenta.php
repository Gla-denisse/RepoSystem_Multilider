<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaVenta extends Model
{
    use HasFactory;

    protected $table = 'notas_ventas';

    protected $fillable = [
        'asesor_id',
        'cliente_id',
        'propiedad_id',
        'fecha',
        'monto_total',
        'monto_comision',
        'tipo_venta',
        'descuento',
        'monto_liquido',
        'cuota_inicial',
        'saldo_credito',
        'estado'
    ];

    // Relaciones
    public function asesor() {
        return $this->belongsTo(Asesor::class, 'asesor_id');
    }

    public function cliente() {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function propiedad() {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public function planPago() {
        return $this->hasOne(PlanPago::class, 'nota_venta_id');
    }
}