<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\NotaVenta;
use App\Models\Ingreso;
use App\Models\Egreso;
use App\Models\Cuota;
use App\Models\Asesor;

class DashboardController extends Controller
{
    // -------------------------------------------------------
    // DASHBOARD ADMINISTRADOR
    // -------------------------------------------------------
    public function admin(Request $request)
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        // KPIs
        $ventasMonto = NotaVenta::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')->sum('monto_total');
        $ventasCantidad = NotaVenta::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')->count();

        $cobrosMonto = Ingreso::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'CONFIRMADO')
            ->whereIn('categoria', ['CUOTA', 'CUOTA_INICIAL', 'VENTA_CONTADO'])
            ->sum('monto');

        $moraTotalAcumulada = Cuota::where('estado', 'Vencida')->sum('monto_cuota');

        $carteraActiva = NotaVenta::where('tipo_venta', 'CREDITO')
            ->where('estado', 'Activa')->sum('saldo_credito');

        $clientesCredito = NotaVenta::where('tipo_venta', 'CREDITO')
            ->where('estado', 'Activa')
            ->distinct('cliente_id')->count('cliente_id');

        $comisionesPagadas = Egreso::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', 'PAGADO')->sum('monto');

        // Graficos: historial 6 meses
        $inicioHistorial = Carbon::parse($hasta)->subMonths(5)->startOfMonth()->toDateString();

        $ventasPorMes = NotaVenta::select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto_total) as monto')
            )
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$inicioHistorial, $hasta])
            ->groupBy('anio', 'mes')
            ->orderBy('anio')->orderBy('mes')
            ->get();

        $distribucionTipoVenta = NotaVenta::select(
                'tipo_venta',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto_total) as monto')
            )
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')
            ->groupBy('tipo_venta')
            ->get();

        $cobrosPorMes = Ingreso::select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('SUM(monto) as monto')
            )
            ->where('estado', 'CONFIRMADO')
            ->whereIn('categoria', ['CUOTA', 'CUOTA_INICIAL', 'VENTA_CONTADO'])
            ->whereBetween('fecha', [$inicioHistorial, $hasta])
            ->groupBy('anio', 'mes')
            ->orderBy('anio')->orderBy('mes')
            ->get();

        $topAsesores = NotaVenta::select('asesor_id',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto_total) as monto')
            )
            ->with('asesor:id,nombre_completo')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')
            ->groupBy('asesor_id')
            ->orderByDesc('monto')
            ->limit(5)
            ->get();

        // Listados
        $ultimasVentas = NotaVenta::with([
                'cliente:id,nombre_completo',
                'asesor:id,nombre_completo',
                'propiedad:id,codigo',
            ])
            ->where('estado', '!=', 'Anulada')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'fecha', 'cliente_id', 'asesor_id', 'propiedad_id', 'tipo_venta', 'monto_total', 'estado']);

        $cuotasVencidas = Cuota::with([
                'planPago:id,nota_venta_id',
                'planPago.notaVenta:id,cliente_id',
                'planPago.notaVenta.cliente:id,nombre_completo',
            ])
            ->where('estado', 'Vencida')
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get(['id', 'plan_pago_id', 'numero_cuota', 'fecha_vencimiento', 'monto_cuota', 'estado']);

        $topDeudores = NotaVenta::with(['cliente:id,nombre_completo'])
            ->where('tipo_venta', 'CREDITO')
            ->where('estado', 'Activa')
            ->where('saldo_credito', '>', 0)
            ->orderByDesc('saldo_credito')
            ->limit(10)
            ->get(['id', 'cliente_id', 'saldo_credito', 'fecha']);

        return response()->json([
            'kpis' => [
                'ventas_monto'       => round((float) $ventasMonto, 2),
                'ventas_cantidad'    => (int) $ventasCantidad,
                'cobros_monto'       => round((float) $cobrosMonto, 2),
                'mora_acumulada'     => round((float) $moraTotalAcumulada, 2),
                'cartera_activa'     => round((float) $carteraActiva, 2),
                'clientes_credito'   => (int) $clientesCredito,
                'comisiones_pagadas' => round((float) $comisionesPagadas, 2),
            ],
            'graficos' => [
                'ventas_por_mes'          => $ventasPorMes,
                'distribucion_tipo_venta' => $distribucionTipoVenta,
                'cobros_por_mes'          => $cobrosPorMes,
                'top_asesores'            => $topAsesores,
            ],
            'listados' => [
                'ultimas_ventas'  => $ultimasVentas,
                'cuotas_vencidas' => $cuotasVencidas,
                'top_deudores'    => $topDeudores,
            ],
        ]);
    }

    // -------------------------------------------------------
    // DASHBOARD ASESOR
    // -------------------------------------------------------
    public function asesor(Request $request)
    {
        $user   = $request->user();
        $asesor = Asesor::where('user_id', $user->id)->first();

        if (!$asesor) {
            return response()->json(['message' => 'No tienes un perfil de asesor asignado.'], 403);
        }

        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        // KPIs
        $misVentasMonto = NotaVenta::where('asesor_id', $asesor->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')->sum('monto_total');
        $misVentasCantidad = NotaVenta::where('asesor_id', $asesor->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')->count();
        $miComisionEstimada = NotaVenta::where('asesor_id', $asesor->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')->sum('monto_comision');

        $misCuotasVencidasCount = Cuota::whereHas('planPago.notaVenta', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            })->where('estado', 'Vencida')->count();

        $misProximasCuotasCount = Cuota::whereHas('planPago.notaVenta', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            })
            ->where('estado', 'Pendiente')
            ->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        // Graficos
        $inicioHistorial = Carbon::parse($hasta)->subMonths(5)->startOfMonth()->toDateString();

        $misVentasPorMes = NotaVenta::select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto_total) as monto')
            )
            ->where('asesor_id', $asesor->id)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$inicioHistorial, $hasta])
            ->groupBy('anio', 'mes')
            ->orderBy('anio')->orderBy('mes')
            ->get();

        $miCartera = [
            'saldo_vigente'         => round((float) NotaVenta::where('asesor_id', $asesor->id)
                ->where('tipo_venta', 'CREDITO')->where('estado', 'Activa')->sum('saldo_credito'), 2),
            'monto_cuotas_vencidas' => round((float) Cuota::whereHas('planPago.notaVenta', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                })->where('estado', 'Vencida')->sum('monto_cuota'), 2),
        ];

        // Listados
        $clientesConMora = Cuota::with([
                'planPago:id,nota_venta_id',
                'planPago.notaVenta:id,cliente_id',
                'planPago.notaVenta.cliente:id,nombre_completo',
            ])
            ->whereHas('planPago.notaVenta', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            })
            ->where('estado', 'Vencida')
            ->orderBy('fecha_vencimiento')
            ->limit(15)
            ->get(['id', 'plan_pago_id', 'numero_cuota', 'fecha_vencimiento', 'monto_cuota', 'estado']);

        $proximasCuotas = Cuota::with([
                'planPago:id,nota_venta_id',
                'planPago.notaVenta:id,cliente_id',
                'planPago.notaVenta.cliente:id,nombre_completo',
            ])
            ->whereHas('planPago.notaVenta', function ($q) use ($asesor) {
                $q->where('asesor_id', $asesor->id);
            })
            ->where('estado', 'Pendiente')
            ->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('fecha_vencimiento')
            ->limit(15)
            ->get(['id', 'plan_pago_id', 'numero_cuota', 'fecha_vencimiento', 'monto_cuota', 'estado']);

        return response()->json([
            'asesor' => [
                'id'              => $asesor->id,
                'nombre_completo' => $asesor->nombre_completo,
            ],
            'kpis' => [
                'ventas_monto'          => round((float) $misVentasMonto, 2),
                'ventas_cantidad'       => (int) $misVentasCantidad,
                'comision_estimada'     => round((float) $miComisionEstimada, 2),
                'cuotas_vencidas'       => (int) $misCuotasVencidasCount,
                'proximas_cuotas_7dias' => (int) $misProximasCuotasCount,
            ],
            'graficos' => [
                'ventas_por_mes' => $misVentasPorMes,
                'mi_cartera'     => $miCartera,
            ],
            'listados' => [
                'clientes_con_mora' => $clientesConMora,
                'proximas_cuotas'   => $proximasCuotas,
            ],
        ]);
    }
}
