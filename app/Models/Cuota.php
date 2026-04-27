<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    use HasFactory;
    protected $table = 'cuotas';
    protected $fillable = ['plan_pago_id', 'numero_cuota', 'fecha_vencimiento', 'monto_cuota', 'monto_interes', 'monto_capital', 'saldo_capital', 'estado'];

    public function planPago() {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }
}