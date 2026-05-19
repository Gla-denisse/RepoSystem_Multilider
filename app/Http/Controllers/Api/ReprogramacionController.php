<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanPago;
use App\Services\AmortizacionService;
use App\Services\ReprogramacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReprogramacionController extends Controller
{
    public function __construct(
        private ReprogramacionService $service,
        private AmortizacionService $amortizacionService,
    ) {}

    /**
     * POST /api/planes-pago/{planId}/reprogramar
     * Ejecuta una reprogramación sobre las cuotas pendientes del plan.
     */
    public function reprogramar(Request $request, int $planId)
    {
        $plan = PlanPago::with('cuotas')->findOrFail($planId);

        $validated = $request->validate([
            'motivo'              => 'required|string|max:255',
            'fecha_reprogramacion'=> 'required|date',
            'cuota_desde'         => 'required|integer|min:1',
            'nueva_tasa_interes'  => 'required|numeric|min:0|max:100',
            'nuevo_numero_cuotas' => 'required|integer|min:1',
            'nueva_fecha_inicio'  => 'required|date',
            'observaciones'       => 'nullable|string|max:1000',
        ]);

        // Verificar que cuota_desde tenga cuotas pendientes
        $cuotasPendientes = $plan->cuotas()
            ->where('numero_cuota', '>=', $validated['cuota_desde'])
            ->whereIn('estado', ['Pendiente', 'Vencida'])
            ->count();

        if ($cuotasPendientes === 0) {
            return response()->json([
                'message' => 'No existen cuotas pendientes o vencidas desde la cuota indicada.'
            ], 422);
        }

        try {
            $reprogramacion = $this->service->reprogramar($plan, $validated, Auth::id());

            return response()->json([
                'message'        => 'Reprogramación aplicada correctamente.',
                'reprogramacion' => $reprogramacion,
                'plan_pago'      => $plan->fresh()->load('cuotas', 'reprogramaciones.usuario'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al reprogramar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/planes-pago/{planId}/amortizar
     * Registra un pago extraordinario de capital y recalcula las cuotas pendientes.
     */
    public function amortizar(Request $request, int $planId)
    {
        $plan = PlanPago::with('cuotas')->findOrFail($planId);

        $validated = $request->validate([
            'monto'          => 'required|numeric|min:0.01',
            'metodo_pago_id' => 'required|exists:metodos_pago,id',
            'cuenta_id'      => 'required|exists:cuentas_bancarias,id',
            'fecha_pago'     => 'required|date',
            'observaciones'  => 'nullable|string|max:500',
        ]);

        try {
            $saldoActual = $this->amortizacionService->saldoCapitalActual($plan);

            if ($validated['monto'] >= $saldoActual) {
                return response()->json([
                    'message' => "El monto ({$validated['monto']}) debe ser menor al saldo capital actual ({$saldoActual}). Para cancelar la deuda completa use la opción de pago total."
                ], 422);
            }

            $pago = $this->amortizacionService->amortizar($plan, $validated, Auth::id());

            $planActualizado = $plan->fresh()->load('cuotas');
            $saldoNuevo      = $this->amortizacionService->saldoCapitalActual($planActualizado);

            return response()->json([
                'message'      => 'Amortización registrada. Las cuotas pendientes fueron recalculadas.',
                'pago'         => $pago,
                'saldo_anterior' => $saldoActual,
                'saldo_nuevo'    => $saldoNuevo,
                'plan_pago'    => $planActualizado,
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al amortizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/planes-pago/{planId}/reprogramaciones
     * Devuelve el historial de reprogramaciones de un plan.
     */
    public function historial(int $planId)
    {
        $plan = PlanPago::with(['notaVenta.cliente'])->findOrFail($planId);

        $historial = $this->service->historial($plan);

        return response()->json([
            'plan_pago' => $plan,
            'historial' => $historial,
        ], 200);
    }

    /**
     * GET /api/ventas/{ventaId}/plan-pago
     * Devuelve el plan de pagos completo de una venta con sus cuotas y reprogramaciones.
     */
    public function planPorVenta(int $ventaId)
    {
        $plan = PlanPago::with([
            'cuotas',
            'reprogramaciones' => function ($q) {
                $q->with('usuario:id,name')->orderBy('fecha_reprogramacion');
            }
        ])->where('nota_venta_id', $ventaId)->firstOrFail();

        $cuotasPagadas    = $plan->cuotas->where('estado', 'Pagada')->count();
        $cuotasPendientes = $plan->cuotas->whereIn('estado', ['Pendiente', 'Vencida'])->count();
        $totalPagado      = $plan->cuotas->where('estado', 'Pagada')->sum('monto_cuota');
        $totalPendiente   = $plan->cuotas->whereIn('estado', ['Pendiente', 'Vencida'])->sum('monto_cuota');
        $saldoCapital     = $this->amortizacionService->saldoCapitalActual($plan);

        return response()->json([
            'plan_pago'         => $plan,
            'resumen' => [
                'cuotas_pagadas'    => $cuotasPagadas,
                'cuotas_pendientes' => $cuotasPendientes,
                'total_pagado'      => round($totalPagado, 2),
                'total_pendiente'   => round($totalPendiente, 2),
                'saldo_capital'     => round((float) $saldoCapital, 2),
                'total_reprogramaciones' => $plan->reprogramaciones->count(),
            ],
        ], 200);
    }
}
