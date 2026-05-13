<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'nota_venta_id',
        'cuota_id',
        'metodo_pago_id',
        'cuenta_id',
        'concepto_pago',
        'fecha_pago',
        'monto',
        'estado',
        'observaciones',
        'ci_pagador',
        'telefono_pagador',
        'nombres_pagador',
        'apellidos_pagador',
        'correo_pagador',
        'id_transaccion_libelula',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto' => 'decimal:2',
    ];

    // Relaciones
    public function notaVenta()
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class, 'cuota_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_id');
    }
}
