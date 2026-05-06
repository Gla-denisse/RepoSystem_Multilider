<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    use HasFactory;
    protected $table = 'cuotas';
    protected $fillable = ['plan_pago_id', 'numero_cuota', 'fecha_vencimiento', 'monto_cuota', 'monto_interes', 'monto_capital', 'saldo_capital', 'estado'];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_cuota' => 'decimal:2',
        'monto_interes' => 'decimal:2',
        'monto_capital' => 'decimal:2',
        'saldo_capital' => 'decimal:2',
    ];

    public function planPago() {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    public function pagos() {
        return $this->hasMany(Pago::class, 'cuota_id');
    }
}