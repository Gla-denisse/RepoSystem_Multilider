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

            // ── MODO PRUEBAS: monto fijo 0.20 Bs para no cargar importes reales ──
            // TODO: eliminar esta constante al pasar a producción
            $montoPrueba = 0.2;

            // ── 1. Pagos de contado/cuota inicial existentes ─────────────────
            if ($pagoIdsAfectados->isNotEmpty()) {
                $pagosExistentes = Pago::whereIn('id', $pagoIdsAfectados)
                    ->where('estado', 'PENDIENTE_PAGO')
                    ->with('notaVenta.propiedad:id,codigo,tipo')
                    ->get();

                foreach ($pagosExistentes as $pago) {
                    $etiqueta = $pago->concepto_pago === 'VENTA_CONTADO' ? 'Pago contado' : 'Cuota inicial';
                    $propiedad = $pago->notaVenta->propiedad->codigo ?? 'Propiedad';
                    $montoReal = number_format((float) $pago->monto, 2);

                    $lineasDetalle[] = [
                        'concepto'       => "[PRUEBA] {$etiqueta} - {$propiedad} (Real: Bs {$montoReal})",
                        'costo_unitario' => $montoPrueba,
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
                    $propiedad = $cuota->planPago->notaVenta->propiedad->codigo ?? 'Propiedad';
                    $montoReal = number_format((float) $cuota->monto_cuota, 2);

                    $lineasDetalle[] = [
                        'concepto'       => "[PRUEBA] Cuota {$cuota->numero_cuota} - {$propiedad} (Real: Bs {$montoReal})",
                        'costo_unitario' => $montoPrueba,
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
            // Incluimos nuestro identificador en el callback_url (igual que el plugin de WooCommerce
            // incluye todotix_order_id), para poder asociar el callback con nuestra transacción
            // sin depender únicamente del libelula_uuid.
            $callbackUrl = env('APP_URL', 'http://localhost:8000')
                . '/api/public/pagos/callback'
                . '?identificador=' . urlencode($idTemp);
            // url_retorno hace que Libélula redirija el navegador del usuario al frontend tras el pago
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

            $libelulaUuid        = $respData['id_transaccion']    ?? null;
            $codigoRecaudacion   = $respData['codigo_recaudacion'] ?? null;
            Log::info('Libélula registrar ids', [
                'libelula_uuid'       => $libelulaUuid,
                'codigo_recaudacion'  => $codigoRecaudacion,
                'nuestro_id'          => $idTemp,
                'respuesta_completa'  => $respData,
            ]);

            DB::commit();

            // Persistir UUID y código de recaudación de Libélula para el endpoint consultar
            $camposLibelula = [];
            if ($libelulaUuid)      $camposLibelula['libelula_uuid']             = $libelulaUuid;
            if ($codigoRecaudacion) $camposLibelula['codigo_recaudacion_libelula'] = $codigoRecaudacion;
            if ($camposLibelula) {
                Pago::whereIn('id', $pagoIdsAfectados->all())->update($camposLibelula);
            }

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
    // PASO 4 → Polling: retorna el estado de la transacción desde nuestra BD.
    // El callback de Libélula actualiza la BD; este endpoint solo la consulta.
    // (El endpoint /rest/deuda/consultar de Libélula no funciona con el API key
    // de testing — eliminado para evitar 3 peticiones fallidas por cada poll.)
    // ─────────────────────────────────────────────────────────────────────────
    public function verificarEstado($transaccionId)
    {
        $pagos = Pago::where('id_transaccion_libelula', $transaccionId)->get();

        if ($pagos->isEmpty()) {
            return response()->json(['estado' => 'NO_ENCONTRADO', 'message' => 'Transacción no encontrada.'], 404);
        }

        $totalPagado = $pagos->where('estado', 'PAGADO')->count();
        $estado      = $pagos->every(fn($p) => $p->estado === 'PAGADO') ? 'PAGADO' : $pagos->first()->estado;

        return response()->json([
            'estado'      => $estado,
            'total_pagos' => $pagos->count(),
            'confirmados' => $totalPagado,
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
    // Libélula envía: transaction_id (su UUID), error ('0'=ok), message, cancel_order
    // ─────────────────────────────────────────────────────────────────────────
    public function callbackLibelula(Request $request)
    {
        // Libélula envía estos campos (confirmado en su plugin oficial WooCommerce)
        $libelulaTransactionId = $request->input('transaction_id');   // UUID de Libélula
        $error                 = (string) $request->input('error', '1');
        $cancelOrder           = (string) $request->input('cancel_order', '');

        // Nuestro identificador viene como GET param en la URL del callback
        // (lo incluimos en callback_url: .../callback?identificador=TEMP-xxx)
        $nuestroId = $request->query('identificador')
            ?? $request->input('identificador')
            ?? null;

        Log::info('Callback Libélula recibido', [
            'query'          => $request->query(),
            'body'           => $request->all(),
            'transaction_id' => $libelulaTransactionId,
            'error'          => $error,
            'cancel_order'   => $cancelOrder,
            'nuestro_id'     => $nuestroId,
        ]);

        // Cancelación explícita — ignorar sin error
        if ($cancelOrder === '1') {
            Log::info('Callback: orden cancelada', compact('nuestroId', 'libelulaTransactionId'));
            return response()->json(['ok' => true, 'message' => 'Orden cancelada.']);
        }

        // Libélula usa error='0' para indicar éxito (string, igual que el plugin WooCommerce)
        if ($error !== '0') {
            Log::info('Callback: pago no aprobado', ['error' => $error, 'msg' => $request->input('message')]);
            return response()->json(['ok' => true, 'message' => 'Pago no aprobado, ignorado.']);
        }

        // Buscar pagos pendientes:
        // 1) Por nuestro identificador interno (viene en el query param del callback_url)
        // 2) Por libelula_uuid (transaction_id que Libélula nos envía)
        $pagos = collect();

        if ($nuestroId) {
            $pagos = Pago::where('id_transaccion_libelula', $nuestroId)
                ->where('estado', 'PENDIENTE_PAGO')
                ->get();
        }

        if ($pagos->isEmpty() && $libelulaTransactionId) {
            $pagos = Pago::where('libelula_uuid', $libelulaTransactionId)
                ->where('estado', 'PENDIENTE_PAGO')
                ->get();
        }

        if ($pagos->isEmpty()) {
            Log::warning('Callback: no se encontraron pagos pendientes', [
                'nuestro_id'     => $nuestroId,
                'transaction_id' => $libelulaTransactionId,
            ]);
            // Retornar 200 para que Libélula no reintente
            return response()->json(['ok' => true, 'message' => 'Sin pagos pendientes.']);
        }

        try {
            DB::beginTransaction();

            foreach ($pagos as $pago) {
                $updates = ['estado' => 'PAGADO', 'fecha_pago' => now()->toDateString()];
                if ($libelulaTransactionId && !$pago->libelula_uuid) {
                    $updates['libelula_uuid'] = $libelulaTransactionId;
                }
                $pago->update($updates);

                if ($pago->cuota_id) {
                    Cuota::where('id', $pago->cuota_id)->update(['estado' => 'Pagada']);
                }
            }

            DB::commit();
            Log::info('Callback: pagos confirmados', ['count' => $pagos->count(), 'nuestro_id' => $nuestroId]);
            return response()->json(['ok' => true, 'pagos_actualizados' => $pagos->count()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Callback Libélula error', ['msg' => $e->getMessage()]);
            return response()->json(['ok' => false], 500);
        }
    }
}
