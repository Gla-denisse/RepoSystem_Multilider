<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaBancaria extends Model
{
    use HasFactory;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'mi_empresa_id',
        'nombre',
        'tipo',
        'descripcion',
        'banco',
        'numero_cuenta',
        'titular',
        'iban',
        'proveedor',
        'codigo_integracion',
        'saldo_inicial',
        'estado'
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(MiEmpresa::class, 'mi_empresa_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cuenta_id');
    }
}
