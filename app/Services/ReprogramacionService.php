<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\PlanPago;
use App\Models\Reprogramacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReprogramacionService
{
    /**
     * Ejecuta una reprogramación sobre un plan de pagos.
     *
     * Cancela las cuotas pendientes desde cuota_desde en adelante y genera
     * nuevas cuotas con los parámetros indicados. Registra el evento en
     * la tabla reprogramaciones para auditoría.
     */
    public function reprogramar(PlanPago $plan, array $datos, int $usuarioId): Reprogramacion
    {
        return DB::transaction(function () use ($plan, $datos, $usuarioId) {

            $cuotaDesde      = (int) $datos['cuota_desde'];
            $nuevaTasa       = (float) $datos['nueva_tasa_interes'];
            $nuevasCuotas    = (int) $datos['nuevo_numero_cuotas'];
            $nuevaFechaInicio = Carbon::parse($datos['nueva_fecha_inicio']);

            // 1. Calcular saldo capital: saldo_capital de la cuota ANTERIOR a cuota_desde
            //    Si se reprograma desde la cuota 1, el saldo es el monto original del plan.
            $saldoCapital = $this->calcularSaldoCapital($plan, $cuotaDesde);

            // 2. Marcar como Reprogramada todas las cuotas pendientes desde cuota_desde
            $plan->cuotas()
                ->where('numero_cuota', '>=', $cuotaDesde)
                ->whereIn('estado', ['Pendiente', 'Vencida'])
                ->update(['estado' => 'Reprogramada', 'updated_at' => now()]);

            // 3. Crear el registro de reprogramación
            $reprogramacion = Reprogramacion::create([
                'plan_pago_id'          => $plan->id,
                'usuario_id'            => $usuarioId,
                'motivo'                => $datos['motivo'],
                'fecha_reprogramacion'  => $datos['fecha_reprogramacion'] ?? now()->toDateString(),
                'cuota_desde'           => $cuotaDesde,
                'saldo_capital_momento' => $saldoCapital,
                'nueva_tasa_interes'    => $nuevaTasa,
                'nuevo_numero_cuotas'   => $nuevasCuotas,
                'nueva_fecha_inicio'    => $nuevaFechaInicio->toDateString(),
                'observaciones'         => $datos['observaciones'] ?? null,
            ]);

            // 4. Generar las nuevas cuotas con amortización francesa
            $this->generarNuevasCuotas($plan, $reprogramacion, $saldoCapital, $nuevaTasa, $nuevasCuotas, $nuevaFechaInicio, $cuotaDesde);

            // 5. Actualizar metadatos del plan de pagos
            $ultimaFecha = $nuevaFechaInicio->copy()->addMonths($nuevasCuotas - 1);
            $plan->update([
                'numero_cuotas' => $plan->cuotas()->count(),
                'tasa_interes'  => $nuevaTasa,
                'fecha_final'   => $ultimaFecha->toDateString(),
            ]);

            return $reprogramacion->load('cuotas', 'usuario');
        });
    }

    private function calcularSaldoCapital(PlanPago $plan, int $cuotaDesde): float
    {
        if ($cuotaDesde === 1) {
            return (float) $plan->monto;
        }

        // El saldo capital correcto es el saldo_capital de la última cuota PAGADA
        // justo antes de cuota_desde, o el de la cuota anterior si no hay pagadas.
        $cuotaAnterior = $plan->cuotas()
            ->where('numero_cuota', $cuotaDesde - 1)
            ->first();

        if ($cuotaAnterior) {
            // Si está pagada el saldo es el registrado; si está pendiente usamos el saldo también
            return (float) $cuotaAnterior->saldo_capital;
        }

        return (float) $plan->monto;
    }

    private function generarNuevasCuotas(
        PlanPago $plan,
        Reprogramacion $reprogramacion,
        float $capital,
        float $tasaAnual,
        int $meses,
        Carbon $fechaInicio,
        int $numeracionDesde
    ): void {
        $tasaMensual   = ($tasaAnual / 100) / 12;
        $saldoSobrante = $capital;
        $fecha         = $fechaInicio->copy();

        if ($tasaMensual > 0) {
            $cuotaFija = $capital * ($tasaMensual * pow(1 + $tasaMensual, $meses))
                       / (pow(1 + $tasaMensual, $meses) - 1);
        } else {
            $cuotaFija = $capital / $meses;
        }

        $insert = [];
        for ($i = 0; $i < $meses; $i++) {
            $interesMes = $saldoSobrante * $tasaMensual;
            $capitalMes = $cuotaFija - $interesMes;

            if ($i === $meses - 1) {
                $capitalMes = $saldoSobrante;
                $cuotaFija  = $capitalMes + $interesMes;
            }

            $saldoSobrante -= $capitalMes;

            $insert[] = [
                'plan_pago_id'      => $plan->id,
                'reprogramacion_id' => $reprogramacion->id,
                'numero_cuota'      => $numeracionDesde + $i,
                'fecha_vencimiento' => $fecha->format('Y-m-d'),
                'monto_cuota'       => round($cuotaFija, 2),
                'monto_interes'     => round($interesMes, 2),
                'monto_capital'     => round($capitalMes, 2),
                'saldo_capital'     => round(max(0, $saldoSobrante), 2),
                'estado'            => 'Pendiente',
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $fecha->addMonth();
        }

        Cuota::insert($insert);
    }

    /**
     * Devuelve el historial de reprogramaciones de un plan con sus cuotas nuevas.
     */
    public function historial(PlanPago $plan): \Illuminate\Database\Eloquent\Collection
    {
        return $plan->reprogramaciones()
            ->with(['usuario:id,name', 'cuotas'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
