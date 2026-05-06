<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPagoCuentaDefault extends Model
{
    use HasFactory;

    protected $table = 'metodo_pago_cuenta_default';

    protected $fillable = [
        'mi_empresa_id',
        'metodo_pago_id',
        'cuenta_bancaria_id'
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(MiEmpresa::class, 'mi_empresa_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }
}
