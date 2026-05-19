<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    use HasFactory;

    protected $table = 'ingresos';

    protected $fillable = [
        'fecha',
        'concepto',
        'categoria',
        'monto',
        'moneda',
        'origen',
        'pago_id',
        'nota_venta_id',
        'cuenta_bancaria_id',
        'user_id',
        'comprobante',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'monto'  => 'decimal:2',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function notaVenta()
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
