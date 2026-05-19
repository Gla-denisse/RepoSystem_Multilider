<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reprogramacion extends Model
{
    use HasFactory;

    protected $table = 'reprogramaciones';

    protected $fillable = [
        'plan_pago_id',
        'usuario_id',
        'motivo',
        'fecha_reprogramacion',
        'cuota_desde',
        'saldo_capital_momento',
        'nueva_tasa_interes',
        'nuevo_numero_cuotas',
        'nueva_fecha_inicio',
        'observaciones',
    ];

    protected $casts = [
        'fecha_reprogramacion' => 'date',
        'nueva_fecha_inicio'   => 'date',
        'saldo_capital_momento' => 'decimal:2',
        'nueva_tasa_interes'   => 'decimal:2',
    ];

    public function planPago()
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cuotas()
    {
        return $this->hasMany(Cuota::class, 'reprogramacion_id');
    }
}
