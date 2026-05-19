<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Ingreso;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MoraService
{
    /**
     * Calcula la mora de una cuota a una fecha de pago dada.
     *
     * Fórmula: mora = monto_cuota × (tasa_mora_mensual / 100 / 30) × días_atraso
     *
     * @return array{dias_atraso: int, tasa_mora_mensual: float, monto_mora: float}
     */
    public function calcularMora(Cuota $cuota, string $fechaPago): array
    {
        $plan      = $cuota->planPago;
        $tasaMora  = (float) ($plan->tasa_mora ?? 0);

        $base = [
            'dias_atraso'       => 0,
            'tasa_mora_mensual' => $tasaMora,
            'monto_mora'        => 0.0,
        ];

        if ($tasaMora <= 0) {
            return $base;
        }

        $fechaVenc     = Carbon::parse($cuota->fecha_vencimiento)->startOfDay();
        $fechaPagoDate = Carbon::parse($fechaPago)->startOfDay();

        if ($fechaPagoDate->lte($fechaVenc)) {
            return $base;
        }

        $diasAtraso  = (int) $fechaVenc->diffInDays($fechaPagoDate);
        $tasaDiaria  = $tasaMora / 100 / 30;
        $montoMora   = round((float) $cuota->monto_cuota * $tasaDiaria * $diasAtraso, 2);

        return [
            'dias_atraso'       => $diasAtraso,
            'tasa_mora_mensual' => $tasaMora,
            'monto_mora'        => $montoMora,
        ];
    }

    /**
     * Registra el pago de mora de una cuota y genera el ingreso automático.
     */
    public function registrarMoraPago(Cuota $cuota, float $montoMora, array $datos, int $usuarioId): Pago
    {
        return DB::transaction(function () use ($cuota, $montoMora, $datos, $usuarioId) {
            $plan = $cuota->planPago;

            $pago = Pago::create([
                'nota_venta_id'  => $plan->nota_venta_id,
                'cuota_id'       => $cuota->id,
                'metodo_pago_id' => $datos['metodo_pago_id'],
                'cuenta_id'      => $datos['cuenta_id'],
                'concepto_pago'  => 'MORA',
                'fecha_pago'     => $datos['fecha_pago'],
                'monto'          => $montoMora,
                'estado'         => 'PAGADO',
                'observaciones'  => 'Mora por atraso - Cuota #' . $cuota->numero_cuota,
            ]);

            Ingreso::create([
                'fecha'              => $datos['fecha_pago'],
                'concepto'           => 'Mora cuota #' . $cuota->numero_cuota . ' - VTA #' . $plan->nota_venta_id,
                'categoria'          => 'MORA',
                'monto'              => $montoMora,
                'moneda'             => 'Bs',
                'origen'             => 'AUTOMATICO',
                'pago_id'            => $pago->id,
                'nota_venta_id'      => $plan->nota_venta_id,
                'cuenta_bancaria_id' => $datos['cuenta_id'],
                'user_id'            => $usuarioId,
                'estado'             => 'CONFIRMADO',
            ]);

            return $pago;
        });
    }
}
