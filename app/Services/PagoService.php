<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\NotaVenta;
use App\Models\Cuota;
use Illuminate\Support\Facades\DB;

class PagoService
{
    /**
     * Registrar cuota inicial de una venta al crédito
     */
    public static function registrarCuotaInicial($notaVentaId, $monto, $fechaPago, $observaciones = null)
    {
        return DB::transaction(function () use ($notaVentaId, $monto, $fechaPago, $observaciones) {
            $notaVenta = NotaVenta::findOrFail($notaVentaId);

            if ($notaVenta->tipo_venta !== 'CREDITO') {
                throw new \Exception('Solo se puede registrar cuota inicial en ventas al crédito');
            }

            if ($monto > $notaVenta->cuota_inicial) {
                throw new \Exception('El monto no puede exceder la cuota inicial');
            }

            return Pago::create([
                'nota_venta_id' => $notaVentaId,
                'concepto_pago' => 'CUOTA_INICIAL',
                'fecha_pago' => $fechaPago,
                'monto' => $monto,
                'estado' => 'Registrado',
                'observaciones' => $observaciones
            ]);
        });
    }

    /**
     * Registrar pago de venta al contado
     */
    public static function registrarPagoContado($notaVentaId, $monto, $fechaPago, $observaciones = null)
    {
        return DB::transaction(function () use ($notaVentaId, $monto, $fechaPago, $observaciones) {
            $notaVenta = NotaVenta::findOrFail($notaVentaId);

            if ($notaVenta->tipo_venta !== 'CONTADO') {
                throw new \Exception('Solo se puede registrar pago al contado en ventas al contado');
            }

            if ($monto > $notaVenta->monto_liquido) {
                throw new \Exception('El monto no puede exceder el monto líquido');
            }

            return Pago::create([
                'nota_venta_id' => $notaVentaId,
                'concepto_pago' => 'VENTA_CONTADO',
                'fecha_pago' => $fechaPago,
                'monto' => $monto,
                'estado' => 'Registrado',
                'observaciones' => $observaciones
            ]);
        });
    }

    /**
     * Registrar pago de cuota
     */
    public static function registrarPagoCuota($cuotaId, $monto, $fechaPago, $observaciones = null)
    {
        return DB::transaction(function () use ($cuotaId, $monto, $fechaPago, $observaciones) {
            $cuota = Cuota::findOrFail($cuotaId);

            if ($cuota->estado === 'Pagada') {
                throw new \Exception('Esta cuota ya ha sido pagada');
            }

            if ($monto > $cuota->monto_cuota) {
                throw new \Exception('El monto no puede exceder la cuota');
            }

            // Obtener la nota de venta a través del plan de pago
            $notaVentaId = $cuota->planPago->nota_venta_id;

            $pago = Pago::create([
                'nota_venta_id' => $notaVentaId,
                'cuota_id' => $cuotaId,
                'concepto_pago' => 'CUOTA',
                'fecha_pago' => $fechaPago,
                'monto' => $monto,
                'estado' => 'Registrado',
                'observaciones' => $observaciones
            ]);

            // Actualizar estado de la cuota si está completamente pagada
            $totalPagado = Pago::where('cuota_id', $cuotaId)
                ->where('estado', 'Registrado')
                ->sum('monto');

            if ($totalPagado >= $cuota->monto_cuota) {
                $cuota->update(['estado' => 'Pagada']);
            }

            return $pago;
        });
    }

    /**
     * Obtener monto total pagado de una venta
     */
    public static function obtenerTotalPagado($notaVentaId)
    {
        return Pago::where('nota_venta_id', $notaVentaId)
            ->where('estado', 'Registrado')
            ->sum('monto');
    }

    /**
     * Obtener monto pendiente de una venta
     */
    public static function obtenerPendiente($notaVentaId)
    {
        $notaVenta = NotaVenta::findOrFail($notaVentaId);
        $totalPagado = self::obtenerTotalPagado($notaVentaId);

        if ($notaVenta->tipo_venta === 'CREDITO') {
            return max(0, $notaVenta->saldo_credito - $totalPagado);
        } else {
            return max(0, $notaVenta->monto_liquido - $totalPagado);
        }
    }

    /**
     * Obtener porcentaje de pago de una venta
     */
    public static function obtenerPorcentajePago($notaVentaId)
    {
        $notaVenta = NotaVenta::findOrFail($notaVentaId);
        $totalPagado = self::obtenerTotalPagado($notaVentaId);

        $total = $notaVenta->tipo_venta === 'CREDITO'
            ? $notaVenta->saldo_credito
            : $notaVenta->monto_liquido;

        return $total > 0 ? ($totalPagado / $total) * 100 : 0;
    }

    /**
     * Obtener resumen de cuotas pagadas/pendientes
     */
    public static function obtenerResumenCuotas($planPagoId)
    {
        $cuotas = Cuota::where('plan_pago_id', $planPagoId)->get();

        $pagadas = $cuotas->where('estado', 'Pagada')->count();
        $pendientes = $cuotas->where('estado', 'Pendiente')->count();
        $vencidas = $cuotas->where('estado', 'Vencida')->count();

        return [
            'total_cuotas' => $cuotas->count(),
            'pagadas' => $pagadas,
            'pendientes' => $pendientes,
            'vencidas' => $vencidas,
            'porcentaje_pagado' => ($pagadas / $cuotas->count()) * 100
        ];
    }

    /**
     * Cancelar un pago
     */
    public static function cancelarPago($pagoId, $observaciones = null)
    {
        return DB::transaction(function () use ($pagoId, $observaciones) {
            $pago = Pago::findOrFail($pagoId);

            if ($pago->estado === 'Cancelado') {
                throw new \Exception('El pago ya fue cancelado');
            }

            $pago->update([
                'estado' => 'Cancelado',
                'observaciones' => $observaciones ?? $pago->observaciones
            ]);

            // Si es pago de cuota, volver a pendiente
            if ($pago->concepto_pago === 'CUOTA' && $pago->cuota_id) {
                $cuota = Cuota::find($pago->cuota_id);
                $cuota->update(['estado' => 'Pendiente']);
            }

            return $pago;
        });
    }
}
