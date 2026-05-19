<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Reprogramacion;

class PlanPago extends Model
{
    use HasFactory;
    protected $table = 'planes_pagos';
    protected $fillable = ['nota_venta_id', 'monto', 'numero_cuotas', 'fecha_inicio', 'fecha_final', 'plazo', 'tasa_interes', 'tasa_mora'];

    public function notaVenta() {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function cuotas() {
        return $this->hasMany(Cuota::class, 'plan_pago_id');
    }

    public function reprogramaciones() {
        return $this->hasMany(Reprogramacion::class, 'plan_pago_id')->orderBy('fecha_reprogramacion');
    }

    public function saldoCapitalPendiente(): float {
        return (float) $this->cuotas()
            ->whereIn('estado', ['Pendiente', 'Vencida'])
            ->min('saldo_capital') ?? 0;
    }
}