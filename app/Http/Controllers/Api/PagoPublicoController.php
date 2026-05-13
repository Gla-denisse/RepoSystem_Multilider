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

            // ID temporal para asociar pagos antes de llamar a Libélula
            $idTemp = 'TEMP-' . Str::uuid();
            $lineasDetalle    = [];
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
                        'descripcion'    => $pago->concepto_pago === 'VENTA_CONTADO'
                            ? 'Pago contado - ' . ($pago->notaVenta->propiedad->codigo ?? 'Propiedad')
                            : 'Cuota inicial - ' . ($pago->notaVenta->propiedad->codigo ?? 'Propiedad'),
                        'costo_unitario' => (float) $pago->monto,
                        'cantidad'       => 1,
                    ];

                    $pago->update([
                        'id_transaccion_libelula' => $idTemp,
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
                        'descripcion'    => 'Cuota ' . $cuota->numero_cuota . ' - ' . ($cuota->planPago->notaVenta->propiedad->codigo ?? 'Propiedad'),
                        'costo_unitario' => (float) $cuota->monto_cuota,
                        'cantidad'       => 1,
                    ];

                    $nuevoPago = Pago::create([
                        'nota_venta_id'           => $cuota->planPago->nota_venta_id,
                        'cuota_id'                => $cuota->id,
                        'concepto_pago'           => 'CUOTA',
                        'monto'                   => $cuota->monto_cuota,
                        'estado'                  => 'PENDIENTE_PAGO',
                        'id_transaccion_libelula' => $idTemp,
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

            // ── 3. Llamar a Libélula ──────────────────────────────────────────
            $libelulaUrl = rtrim(env('LIBELULA_API_URL', 'https://api.libelula.bo'), '/');
            $callbackUrl = env('APP_URL', 'http://localhost:8000') . '/api/public/pagos/callback';
            // url_retorno lleva el id_transaccion para que el frontend pueda confirmar el pago
            // cuando Libélula redirija al cliente de vuelta (funciona aunque el callback no llegue)
            $urlRetorno  = env('FRONTEND_URL', 'http://localhost:5173') . '/pagar?txn=' . urlencode($idTemp);

            $libelulaResp = Http::timeout(15)->post($libelulaUrl . '/rest/deuda/registrar', [
                'appkey'               => env('LIBELULA_API_KEY'),
                'email_cliente'        => $cliente->correo ?? $request->correo_pagador,
                'identificador'        => $idTemp,
                'callback_url'         => $callbackUrl,
                'url_retorno'          => $urlRetorno,
                'descripcion'          => 'Pago de obligaciones - ' . $cliente->nombre_completo,
                'nombre_cliente'       => $pagadorNombreCompleto,
                'lineas_detalle_deuda' => $lineasDetalle,
                'emite_factura'        => 0,
                'codigo_tipo_documento'=> '5',
                'razon_social'         => $pagadorNombreCompleto,
                'nit'                  => $request->ci_pagador,
            ]);

            $respData = $libelulaResp->json();
            Log::info('Libélula response', ['status' => $libelulaResp->status(), 'body' => $respData]);

            // error:0 = éxito, error:1 = fallo
            $esError = $libelulaResp->failed() || (($respData['error'] ?? 1) !== 0);
            $urlPasarela = $respData['url_pasarela_pagos'] ?? null;
            $qrImagenUrl = $respData['qr_simple_url']      ?? null;

            if ($esError || !$urlPasarela) {
                DB::rollBack();
                $msg = $respData['mensaje'] ?? $respData['message'] ?? 'Error en pasarela de pagos.';
                Log::error('Libélula error', ['resp' => $respData]);
                return response()->json(['message' => $msg], 502);
            }

            // Nuestro $idTemp permanece en la BD como identificador único de esta transacción.
            // El UUID de Libélula solo se registra en el log para reconciliación.
            Log::info('Libélula id_transaccion', ['libelula_uuid' => $respData['id_transaccion'] ?? null, 'nuestro_id' => $idTemp]);

            DB::commit();

            return response()->json([
                'id_transaccion' => $idTemp,
                'url_pago'       => $urlPasarela,
                'qr_url'         => $qrImagenUrl,
                'pago_ids'       => $pagoIdsAfectados->values(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('procesarPago error', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno al procesar el pago.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASO 4 → Polling: verificar estado consultando primero Libélula y luego BD
    // ─────────────────────────────────────────────────────────────────────────
    public function verificarEstado($transaccionId)
    {
        $pagos = Pago::where('id_transaccion_libelula', $transaccionId)->get();

        if ($pagos->isEmpty()) {
            return response()->json(['estado' => 'NO_ENCONTRADO'], 404);
        }

        // Si ya está pagado en nuestra BD, retornar directo
        if ($pagos->every(fn($p) => $p->estado === 'PAGADO')) {
            return response()->json(['estado' => 'PAGADO', 'total_pagos' => $pagos->count(), 'confirmados' => $pagos->count()]);
        }

        // Consultar estado en Libélula para no depender del webhook
        try {
            $libelulaUrl = rtrim(env('LIBELULA_API_URL', 'https://api.libelula.bo'), '/');
            $resp = Http::timeout(8)->post($libelulaUrl . '/rest/deuda/consultar', [
                'appkey'       => env('LIBELULA_API_KEY'),
                'identificador' => $transaccionId,
            ]);

            Log::info('Libélula consultar response', ['status' => $resp->status(), 'body' => $resp->json()]);

            $data = $resp->json();
            $estadoLib = strtoupper($data['estado'] ?? $data['status'] ?? $data['estado_pago'] ?? '');

            if (in_array($estadoLib, ['PAGADO', 'APROBADO', 'COMPLETADO', 'SUCCESS', 'OK', 'PAID'])) {
                // Actualizar BD y retornar PAGADO
                DB::beginTransaction();
                foreach ($pagos as $pago) {
                    $pago->update(['estado' => 'PAGADO', 'fecha_pago' => now()->toDateString()]);
                    if ($pago->cuota_id) {
                        Cuota::where('id', $pago->cuota_id)->update(['estado' => 'Pagada']);
                    }
                }
                DB::commit();

                return response()->json(['estado' => 'PAGADO', 'total_pagos' => $pagos->count(), 'confirmados' => $pagos->count()]);
            }
        } catch (\Exception $e) {
            // El endpoint de consulta puede no existir — loguear y continuar con BD
            Log::warning('Libélula consultar falló', ['error' => $e->getMessage()]);
        }

        $alguno = $pagos->first();
        return response()->json([
            'estado'      => $alguno->estado,
            'total_pagos' => $pagos->count(),
            'confirmados' => $pagos->where('estado', 'PAGADO')->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASO 4b → Confirmar pago vía redirección url_retorno (cuando callback no llega)
    // ─────────────────────────────────────────────────────────────────────────
    public function confirmarRetorno(Request $request)
    {
        $request->validate(['id_transaccion' => 'required|string']);

        $idTransaccion = $request->id_transaccion;

        $pagos = Pago::where('id_transaccion_libelula', $idTransaccion)
            ->where('estado', 'PENDIENTE_PAGO')
            ->get();

        if ($pagos->isEmpty()) {
            // Si ya están pagados o no existen, retornamos el estado actual
            $estadoActual = Pago::where('id_transaccion_libelula', $idTransaccion)->value('estado');
            return response()->json([
                'ok'     => true,
                'estado' => $estadoActual ?? 'NO_ENCONTRADO',
            ]);
        }

        try {
            DB::beginTransaction();

            foreach ($pagos as $pago) {
                $pago->update([
                    'estado'     => 'PAGADO',
                    'fecha_pago' => now()->toDateString(),
                ]);

                if ($pago->cuota_id) {
                    Cuota::where('id', $pago->cuota_id)->update(['estado' => 'Pagada']);
                }
            }

            DB::commit();

            Log::info('Pago confirmado vía url_retorno', ['id_transaccion' => $idTransaccion, 'pagos' => $pagos->count()]);

            return response()->json(['ok' => true, 'estado' => 'PAGADO', 'pagos_confirmados' => $pagos->count()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('confirmarRetorno error', ['msg' => $e->getMessage()]);
            return response()->json(['ok' => false], 500);
        }
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
