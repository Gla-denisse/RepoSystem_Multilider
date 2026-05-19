<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Egreso extends Model
{
    use HasFactory;

    protected $table = 'egresos';

    protected $fillable = [
        'fecha',
        'concepto',
        'categoria',
        'monto',
        'moneda',
        'origen',
        'nota_venta_id',
        'asesor_id',
        'cuenta_bancaria_id',
        'user_id',
        'beneficiario',
        'comprobante',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'monto'  => 'decimal:2',
    ];

    public function notaVenta()
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function asesor()
    {
        return $this->belongsTo(Asesor::class, 'asesor_id');
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
