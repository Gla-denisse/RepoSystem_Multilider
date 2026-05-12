<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\NotaVenta;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PagoPublicoController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PASO 1 → Buscar cliente por CI y retornar sus pagos pendientes
    // ─────────────────────────────────────────────────────────────────────────
    public function buscarCliente(Request $request)
    {
        $request->validate(['ci' => 'required|string|max:20']);

        $cliente = Cliente::where('ci', $request->ci)->first();

        if (!$cliente) {
            return response()->json([
                'found'   => false,
                'message' => 'No se encontró ningún cliente registrado con ese número de CI.',
            ], 404);
        }

        $ventaIds = NotaVenta::where('cliente_id', $cliente->id)
            ->where('estado', '!=', 'Anulada')
            ->pluck('id');

        // Pagos de contado o cuota inicial pendientes de pago
        $pagosContado = Pago::whereIn('nota_venta_id', $ventaIds)
            ->whereIn('concepto_pago', ['VENTA_CONTADO', 'CUOTA_INICIAL'])
            ->where('estado', 'PENDIENTE_PAGO')
            ->with(['notaVenta.propiedad:id,codigo,tipo,moneda'])
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'tipo'        => $p->concepto_pago === 'VENTA_CONTADO' ? 'Pago al Contado' : 'Cuota Inicial',
                'monto'       => $p->monto,
                'moneda'      => $p->notaVenta->propiedad->moneda ?? 'Bs',
                'propiedad'   => $p->notaVenta->propiedad->codigo ?? '-',
                'tipo_prop'   => $p->notaVenta->propiedad->tipo ?? '-',
                'nota_venta_id' => $p->nota_venta_id,
            ]);

        // Cuotas de planes de crédito pendientes
        $cuotasPendientes = Cuota::whereHas('planPago.notaVenta', function ($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id)->where('estado', '!=', 'Anulada');
            })
            ->where('estado', 'Pendiente')
            ->with(['planPago.notaVenta.propiedad:id,codigo,tipo,moneda'])
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(fn($c) => [
                'cuota_id'          => $c->id,
                'numero_cuota'      => $c->numero_cuota,
                'fecha_vencimiento' => $c->fecha_vencimiento,
                'monto_cuota'       => $c->monto_cuota,
                'moneda'            => $c->planPago->notaVenta->propiedad->moneda ?? 'Bs',
                'propiedad'         => $c->planPago->notaVenta->propiedad->codigo ?? '-',
                'tipo_prop'         => $c->planPago->notaVenta->propiedad->tipo ?? '-',
                'nota_venta_id'     => $c->planPago->nota_venta_id,
                'plan_pago_id'      => $c->plan_pago_id,
            ]);

        return response()->json([
            'found'            => true,
            'cliente'          => $cliente->only(['id', 'nombre_completo', 'ci', 'telefono', 'correo']),
            'pagos_contado'    => $pagosContado,
            'cuotas_pendientes' => $cuotasPendientes,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASO 3 → Procesar pago: crear transacción en Libélula
    // ─────────────────────────────────────────────────────────────────────────
    public function procesarPago(Request $request)
    {
        $request->validate([
            'pago_ids'          => 'nullable|array',
            'pago_ids.*'        => 'integer|exists:pagos,id',
            'cuota_ids'         => 'nullable|array',
            'cuota_ids.*'       => 'integer|exists:cuotas,id',
            'ci_pagador'        => 'required|string|max:20',
            'telefono_pagador'  => 'required|string|max:20',
            'nombres_pagador'   => 'required|string|max:100',
            'apellidos_pagador' => 'required|string|max:100',
            'correo_pagador'    => 'required|email|max:150',
            'cliente_id'        => 'required|integer|exists:clientes,id',
        ]);

        if (empty($request->pago_ids) && empty($request->cuota_ids)) {
            return response()->json(['message' => 'Debe seleccionar al menos un pago.'], 422);
        }

        $cliente = Cliente::findOrFail($request->cliente_id);
        $pagadorNombreCompleto = trim($request->nombres_pagador . ' ' . $request->apellidos_pagador);

        try {
            DB::beginTransaction();

            $idTransaccion = 'LIB-' . strtoupper(Str::random(10)) . '-' . time();
            $lineasDetalle = [];
            $pagoIdsAfectados = collect($request->pago_ids ?? []);
            $cuotaIdsAfectados = collect($request->cuota_ids ?? []);

            // ── 1. Pagos de contado/cuota inicial existentes ─────────────────
            if ($pagoIdsAfectados->isNotEmpty()) {
                $pagosExistentes = Pago::whereIn('id', $pagoIdsAfectados)
                    ->where('estado', 'PENDIENTE_PAGO')
                    ->with('notaVenta.propiedad:id,codigo,tipo')
                    ->get();

                foreach ($pagosExistentes as $pago) {
                    $lineasDetalle[] = [
                        'descripcion' => $pago->concepto_pago === 'VENTA_CONTADO'
                            ? 'Pago contado - ' . ($pago->notaVenta->propiedad->codigo ?? 'Propiedad')
                            : 'Cuota inicial - ' . ($pago->notaVenta->propiedad->codigo ?? 'Propiedad'),
                        'monto_bs'   => (float) $pago->monto,
                        'cantidad'   => 1,
                    ];

                    $pago->update([
                        'id_transaccion_libelula' => $idTransaccion,
                        'ci_pagador'              => $request->ci_pagador,
                        'telefono_pagador'        => $request->telefono_pagador,
                        'nombres_pagador'         => $request->nombres_pagador,
                        'apellidos_pagador'       => $request->apellidos_pagador,
                        'correo_pagador'          => $request->correo_pagador,
                    ]);
                }
            }

            // ── 2. Cuotas de crédito seleccionadas → crear Pago por cada una ──
            if ($cuotaIdsAfectados->isNotEmpty()) {
                $cuotas = Cuota::whereIn('id', $cuotaIdsAfectados)
                    ->where('estado', 'Pendiente')
                    ->with('planPago.notaVenta.propiedad:id,codigo,tipo')
                    ->get();

                foreach ($cuotas as $cuota) {
                    $lineasDetalle[] = [
                        'descripcion' => 'Cuota ' . $cuota->numero_cuota . ' - ' . ($cuota->planPago->notaVenta->propiedad->codigo ?? 'Propiedad'),
                        'monto_bs'   => (float) $cuota->monto_cuota,
                        'cantidad'   => 1,
                    ];

                    $nuevoPago = Pago::create([
                        'nota_venta_id'           => $cuota->planPago->nota_venta_id,
                        'cuota_id'                => $cuota->id,
                        'concepto_pago'           => 'CUOTA',
                        'monto'                   => $cuota->monto_cuota,
                        'estado'                  => 'PENDIENTE_PAGO',
                        'id_transaccion_libelula' => $idTransaccion,
                        'ci_pagador'              => $request->ci_pagador,
                        'telefono_pagador'        => $request->telefono_pagador,
                        'nombres_pagador'         => $request->nombres_pagador,
                        'apellidos_pagador'       => $request->apellidos_pagador,
                        'correo_pagador'          => $request->correo_pagador,
                        'observaciones'           => 'Pago iniciado vía portal público',
                    ]);

                    $pagoIdsAfectados->push($nuevoPago->id);
                }
            }

            DB::commit();

            // ── 3. Llamar a Libélula ──────────────────────────────────────────
            $libelulaUrl   = rtrim(env('LIBELULA_API_URL', 'https://api.libelula.bo'), '/');
            $callbackUrl   = env('APP_URL', 'http://localhost:8000') . '/api/public/pagos/callback';
            $urlRetorno    = env('FRONTEND_URL', 'http://localhost:5173') . '/pagar?estado=completado';

            $libelulaResp = Http::timeout(15)->post($libelulaUrl . '/rest/deuda/registrar', [
                'appkey'               => env('LIBELULA_API_KEY'),
                'email_cliente'        => $cliente->correo ?? $request->correo_pagador,
                'identificador'        => $idTransaccion,
                'callback_url'         => $callbackUrl,
                'url_retorno'          => $urlRetorno,
                'descripcion'          => 'Pago de obligaciones - ' . $cliente->nombre_completo,
                'nombre_cliente'       => $pagadorNombreCompleto,
                'lineas_detalle_deuda' => $lineasDetalle,
                'emite_factura'        => 0,
                'codigo_tipo_documento'=> '5', // CI
                'razon_social'         => $pagadorNombreCompleto,
                'nit'                  => $request->ci_pagador,
            ]);

            $respData = $libelulaResp->json();

            if ($libelulaResp->failed() || ($respData['error'] ?? true) === true) {
                Log::error('Libélula error', ['resp' => $respData]);
                return response()->json([
                    'message' => 'Error al conectar con la pasarela de pagos. Intente nuevamente.',
                ], 502);
            }

            return response()->json([
                'id_transaccion' => $idTransaccion,
                'url_pago'       => $respData['url'] ?? null,
                'pago_ids'       => $pagoIdsAfectados->values(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('procesarPago error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al procesar el pago.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASO 4 → Polling: verificar si la transacción ya fue confirmada
    // ─────────────────────────────────────────────────────────────────────────
    public function verificarEstado($transaccionId)
    {
        $pagos = Pago::where('id_transaccion_libelula', $transaccionId)->get();

        if ($pagos->isEmpty()) {
            return response()->json(['estado' => 'NO_ENCONTRADO'], 404);
        }

        $todosAprobados = $pagos->every(fn($p) => $p->estado === 'PAGADO');
        $alguno = $pagos->first();

        return response()->json([
            'estado'       => $todosAprobados ? 'PAGADO' : $alguno->estado,
            'total_pagos'  => $pagos->count(),
            'confirmados'  => $pagos->where('estado', 'PAGADO')->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook de Libélula → confirmar pago
    // ─────────────────────────────────────────────────────────────────────────
    public function callbackLibelula(Request $request)
    {
        $idTransaccion = $request->input('id_transaccion')
            ?? $request->input('identificador')
            ?? null;

        $estado = $request->input('estado', '');

        Log::info('Callback Libélula recibido', $request->all());

        if (!$idTransaccion) {
            return response()->json(['ok' => false, 'message' => 'Sin id_transaccion'], 400);
        }

        $esAprobado = in_array(strtoupper($estado), ['APROBADO', 'COMPLETADO', 'PAGADO', 'SUCCESS', 'OK']);

        if (!$esAprobado) {
            return response()->json(['ok' => true, 'message' => 'Estado no aprobado, ignorado.']);
        }

        try {
            DB::beginTransaction();

            $pagos = Pago::where('id_transaccion_libelula', $idTransaccion)
                ->where('estado', 'PENDIENTE_PAGO')
                ->get();

            foreach ($pagos as $pago) {
                $pago->update([
                    'estado'     => 'PAGADO',
                    'fecha_pago' => now()->toDateString(),
                ]);

                // Marcar la cuota asociada como Pagada
                if ($pago->cuota_id) {
                    Cuota::where('id', $pago->cuota_id)->update(['estado' => 'Pagada']);
                }
            }

            DB::commit();
            return response()->json(['ok' => true, 'pagos_actualizados' => $pagos->count()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Callback Libélula error', ['msg' => $e->getMessage()]);
            return response()->json(['ok' => false], 500);
        }
    }
}
