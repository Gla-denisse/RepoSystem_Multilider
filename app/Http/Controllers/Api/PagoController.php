<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\NotaVenta;
use App\Models\Cuota;
use App\Models\Ingreso;
use App\Models\MiEmpresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class PagoController extends Controller
{
    // 1. Listar pagos con filtros
    public function index(Request $request)
    {
        $query = Pago::with(['notaVenta.cliente', 'cuota.planPago', 'metodoPago', 'cuenta']);

        // Filtro por Fechas
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha_pago', [$request->fecha_inicio, $request->fecha_fin]);
        }

        // Filtro por Nota de Venta
        if ($request->filled('nota_venta_id')) {
            $query->where('nota_venta_id', $request->nota_venta_id);
        }

        // Filtro por Cuota
        if ($request->filled('cuota_id')) {
            $query->where('cuota_id', $request->cuota_id);
        }

        // Filtro por Concepto de Pago
        if ($request->filled('concepto_pago')) {
            $query->where('concepto_pago', $request->concepto_pago);
        }

        // Filtro por Estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por Cliente (relación indirecta)
        if ($request->filled('cliente_id')) {
            $query->whereHas('notaVenta', function ($q) use ($request) {
                $q->where('cliente_id', $request->cliente_id);
            });
        }

        // Búsqueda por nombre o CI del cliente
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('notaVenta.cliente', function ($q) use ($search) {
                $q->where('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('ci', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $pagos = $query->orderBy('fecha_pago', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($pagos, 200);
    }

    // 2. Registrar nuevo pago
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nota_venta_id'  => 'required|exists:notas_ventas,id',
            'cuota_id'       => 'nullable|exists:cuotas,id',
            'concepto_pago'  => 'required|in:CUOTA_INICIAL,CUOTA,VENTA_CONTADO,OTRO',
            'fecha_pago'     => 'required|date',
            'monto'          => 'required|numeric|min:0.01',
            'estado'         => 'nullable|in:PAGADO,CANCELADO,RECHAZADO,PENDIENTE_PAGO',
            'observaciones'  => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Validar que la nota de venta existe
            $notaVenta = NotaVenta::findOrFail($validatedData['nota_venta_id']);

            // Validaciones según el concepto de pago
            switch ($validatedData['concepto_pago']) {
                case 'CUOTA_INICIAL':
                    if ($notaVenta->tipo_venta !== 'CREDITO') {
                        return response()->json(['message' => 'No se puede registrar cuota inicial en una venta al contado'], 400);
                    }
                    if ($validatedData['monto'] > $notaVenta->cuota_inicial) {
                        return response()->json(['message' => 'El monto no puede exceder la cuota inicial'], 400);
                    }
                    break;

                case 'VENTA_CONTADO':
                    if ($notaVenta->tipo_venta !== 'CONTADO') {
                        return response()->json(['message' => 'No se puede registrar pago al contado en una venta al crédito'], 400);
                    }
                    if ($validatedData['monto'] > $notaVenta->monto_liquido) {
                        return response()->json(['message' => 'El monto no puede exceder el monto líquido de la venta'], 400);
                    }
                    break;

                case 'CUOTA':
                    if (!$validatedData['cuota_id']) {
                        return response()->json(['message' => 'Debe especificar una cuota para pago de cuota'], 400);
                    }
                    $cuota = Cuota::findOrFail($validatedData['cuota_id']);
                    if ($validatedData['monto'] > $cuota->monto_cuota) {
                        return response()->json(['message' => 'El monto no puede exceder la cuota'], 400);
                    }
                    if ($cuota->estado === 'Pagada') {
                        return response()->json(['message' => 'Esta cuota ya ha sido pagada'], 400);
                    }
                    break;
            }

            // Crear el pago
            $pago = Pago::create([
                'nota_venta_id'  => $validatedData['nota_venta_id'],
                'cuota_id'       => $validatedData['cuota_id'] ?? null,
                'concepto_pago'  => $validatedData['concepto_pago'],
                'fecha_pago'     => $validatedData['fecha_pago'],
                'monto'          => $validatedData['monto'],
                'estado'         => $validatedData['estado'] ?? 'PAGADO',
                'observaciones'  => $validatedData['observaciones'] ?? null
            ]);

            // Si es pago de cuota, actualizar el estado de la cuota
            if ($validatedData['concepto_pago'] === 'CUOTA' && $validatedData['cuota_id']) {
                $cuota = Cuota::find($validatedData['cuota_id']);
                // Verificar si la cuota está completamente pagada
                $totalPagado = Pago::where('cuota_id', $cuota->id)
                    ->where('estado', 'PAGADO')
                    ->sum('monto');

                if ($totalPagado >= $cuota->monto_cuota) {
                    $cuota->update(['estado' => 'Pagada']);
                }
            }

            // Si el pago se crea directamente como PAGADO, generar ingreso automático
            if ($pago->estado === 'PAGADO') {
                $pago->load('notaVenta.propiedad');
                $this->crearIngresoDesde($pago);
            }

            DB::commit();
            return response()->json([
                'message' => 'Pago registrado con éxito',
                'data' => $pago->load(['notaVenta.cliente', 'cuota'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar el pago: ' . $e->getMessage()], 500);
        }
    }

    // Registrar cobro múltiple de cuotas
    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'nota_venta_id'  => 'required|exists:notas_ventas,id',
            'cuotas'         => 'required|array|min:1',
            'cuotas.*.id'    => 'required|exists:cuotas,id',
            'cuotas.*.monto' => 'required|numeric|min:0.01',
            'metodo_pago_id' => 'required|exists:metodos_pago,id',
            'cuenta_id'      => 'required|exists:cuentas_bancarias,id',
            'fecha_pago'     => 'required|date',
            'observaciones'  => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['cuotas'] as $item) {
                $cuota = Cuota::findOrFail($item['id']);
                
                if ($cuota->estado === 'Pagada') {
                    throw new \Exception("La cuota {$cuota->numero_cuota} ya ha sido pagada.");
                }

                $pago = Pago::create([
                    'nota_venta_id'  => $validated['nota_venta_id'],
                    'cuota_id'       => $cuota->id,
                    'metodo_pago_id' => $validated['metodo_pago_id'],
                    'cuenta_id'      => $validated['cuenta_id'],
                    'concepto_pago'  => 'CUOTA',
                    'fecha_pago'     => $validated['fecha_pago'],
                    'monto'          => $item['monto'],
                    'estado'         => 'PAGADO',
                    'observaciones'  => $validated['observaciones']
                ]);

                // Actualizar estado de la cuota
                $cuota->update(['estado' => 'Pagada']);

                // Ingreso automático por cada cuota cobrada en bulk
                $pago->load('notaVenta.propiedad');
                $this->crearIngresoDesde($pago);
            }

            DB::commit();
            return response()->json(['message' => 'Cobros registrados con éxito'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // 3. Ver detalle de un pago
    public function show($id)
    {
        $pago = Pago::with([
            'notaVenta.cliente',
            'notaVenta.asesor',
            'notaVenta.propiedad',
            'cuota.planPago'
        ])->findOrFail($id);

        return response()->json($pago, 200);
    }

    // 4. Actualizar pago
    public function update(Request $request, $id)
    {
        $pago = Pago::findOrFail($id);

        $validatedData = $request->validate([
            'concepto_pago'  => 'sometimes|in:CUOTA_INICIAL,CUOTA,VENTA_CONTADO,OTRO',
            'fecha_pago'     => 'sometimes|date',
            'monto'          => 'sometimes|numeric|min:0.01',
            'estado'         => 'sometimes|in:PAGADO,CANCELADO,RECHAZADO,PENDIENTE_PAGO',
            'observaciones'  => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $pago->update($validatedData);

            DB::commit();
            return response()->json([
                'message' => 'Pago actualizado con éxito',
                'data' => $pago->load(['notaVenta.cliente', 'cuota'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar el pago: ' . $e->getMessage()], 500);
        }
    }

    // 5. Eliminar pago (soft delete sería mejor, pero aquí delete físico)
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $pago = Pago::findOrFail($id);
            $pago->delete();

            DB::commit();
            return response()->json(['message' => 'Pago eliminado con éxito'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar el pago: ' . $e->getMessage()], 500);
        }
    }

    // 6. Obtener pagos por nota de venta
    public function pagosPorVenta($notaVentaId)
    {
        $notaVenta = NotaVenta::findOrFail($notaVentaId);
        $pagos = Pago::where('nota_venta_id', $notaVentaId)
            ->with(['cuota'])
            ->orderBy('fecha_pago', 'desc')
            ->get();

        $totalPagado = $pagos->where('estado', 'PAGADO')->sum('monto');
        $totalPendiente = $notaVenta->tipo_venta === 'CREDITO'
            ? $notaVenta->saldo_credito - $totalPagado
            : $notaVenta->monto_liquido - $totalPagado;

        return response()->json([
            'nota_venta' => $notaVenta,
            'pagos' => $pagos,
            'resumen' => [
                'total_pagado' => $totalPagado,
                'total_pendiente' => max(0, $totalPendiente),
                'porcentaje_pagado' => ($totalPagado / ($notaVenta->tipo_venta === 'CREDITO' ? $notaVenta->saldo_credito : $notaVenta->monto_liquido)) * 100
            ]
        ], 200);
    }

    // 7. Obtener resumen de pagos por cliente
    public function resumenPorCliente($clienteId)
    {
        $pagos = Pago::whereHas('notaVenta', function ($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId);
        })->with(['notaVenta', 'cuota'])
            ->orderBy('fecha_pago', 'desc')
            ->get();

        $totalPagado = $pagos->where('estado', 'PAGADO')->sum('monto');
        $ventasCliente = NotaVenta::where('cliente_id', $clienteId)->count();
        $ventasCredito = NotaVenta::where('cliente_id', $clienteId)->where('tipo_venta', 'CREDITO')->count();

        return response()->json([
            'cliente_id' => $clienteId,
            'total_pagado' => $totalPagado,
            'total_ventas' => $ventasCliente,
            'ventas_credito' => $ventasCredito,
            'pagos' => $pagos,
        ], 200);
    }

    // 8. Cancelar pago
    public function cancelar($id)
    {
        try {
            DB::beginTransaction();

            $pago = Pago::findOrFail($id);

            if ($pago->estado === 'CANCELADO') {
                return response()->json(['message' => 'El pago ya fue cancelado'], 400);
            }

            $pago->update(['estado' => 'CANCELADO']);

            // Si es pago de cuota, volver a pendiente
            if ($pago->concepto_pago === 'CUOTA' && $pago->cuota_id) {
                $cuota = Cuota::find($pago->cuota_id);
                $cuota->update(['estado' => 'Pendiente']);
            }

            DB::commit();
            return response()->json([
                'message' => 'Pago cancelado con éxito',
                'data' => $pago->load(['notaVenta.cliente', 'cuota'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al cancelar el pago: ' . $e->getMessage()], 500);
        }
    }

    // 9. Reportes de pagos por período
    public function reportePeriodo(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        $pagos = Pago::whereBetween('fecha_pago', [$request->fecha_inicio, $request->fecha_fin])
            ->with(['notaVenta.cliente', 'cuota'])
            ->orderBy('fecha_pago', 'asc')
            ->get();

        $resumen = [
            'total_periodo' => $pagos->where('estado', 'PAGADO')->sum('monto'),
            'total_contado' => $pagos->where('concepto_pago', 'VENTA_CONTADO')->where('estado', 'PAGADO')->sum('monto'),
            'total_cuota_inicial' => $pagos->where('concepto_pago', 'CUOTA_INICIAL')->where('estado', 'PAGADO')->sum('monto'),
            'total_cuotas' => $pagos->where('concepto_pago', 'CUOTA')->where('estado', 'PAGADO')->sum('monto'),
            'cantidad_pagos' => $pagos->count(),
            'pagos_registrados' => $pagos->where('estado', 'PAGADO')->count(),
            'pagos_cancelados' => $pagos->where('estado', 'CANCELADO')->count()
        ];

        return response()->json([
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'resumen' => $resumen,
            'pagos' => $pagos
        ], 200);
    }

    // Procesar pago pendiente
    public function procesarPagoPendiente(Request $request, $id)
    {
        $pago = Pago::findOrFail($id);

        if ($pago->estado !== 'PENDIENTE_PAGO') {
            return response()->json(['message' => 'Este pago ya ha sido procesado'], 400);
        }

        $validated = $request->validate([
            'metodo_pago_id' => 'required|exists:metodos_pago,id',
            'cuenta_id' => 'required|exists:cuentas_bancarias,id',
            'fecha_pago' => 'required|date',
            'observaciones' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Actualizar el pago con los datos
            $pago->update([
                'metodo_pago_id' => $validated['metodo_pago_id'],
                'cuenta_id' => $validated['cuenta_id'],
                'fecha_pago' => $validated['fecha_pago'],
                'observaciones' => $validated['observaciones'] ?? $pago->observaciones,
                'estado' => 'PAGADO'
            ]);

            // Si es pago de cuota, actualizar el estado de la cuota
            if ($pago->concepto_pago === 'CUOTA' && $pago->cuota_id) {
                $cuota = Cuota::find($pago->cuota_id);
                $totalPagado = Pago::where('cuota_id', $cuota->id)
                    ->where('estado', 'PAGADO')
                    ->sum('monto');

                if ($totalPagado >= $cuota->monto_cuota) {
                    $cuota->update(['estado' => 'Pagada']);
                }
            }

            // Ingreso automático al confirmar el pago pendiente
            $pago->load('notaVenta.propiedad');
            $this->crearIngresoDesde($pago);

            DB::commit();
            return response()->json(['message' => 'Pago procesado exitosamente', 'data' => $pago->load('metodoPago', 'cuenta')], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al procesar el pago: ' . $e->getMessage()], 500);
        }
    }

    // Helper: crea un Ingreso automático a partir de un Pago confirmado
    private function crearIngresoDesde(Pago $pago): void
    {
        // Derivar moneda desde la propiedad (BOB→Bs, USD→$)
        $monedaMap = ['BOB' => 'Bs', 'USD' => '$'];
        $moneda = $monedaMap[$pago->notaVenta?->propiedad?->moneda] ?? 'Bs';

        $categoriaMap = [
            'VENTA_CONTADO' => 'VENTA_CONTADO',
            'CUOTA_INICIAL'  => 'CUOTA_INICIAL',
            'CUOTA'          => 'CUOTA',
            'OTRO'           => 'OTRO',
        ];

        Ingreso::create([
            'fecha'              => $pago->fecha_pago,
            'concepto'           => 'Pago ' . str_replace('_', ' ', $pago->concepto_pago) . ' - Venta #' . $pago->nota_venta_id,
            'categoria'          => $categoriaMap[$pago->concepto_pago] ?? 'OTRO',
            'monto'              => $pago->monto,
            'moneda'             => $moneda,
            'origen'             => 'AUTOMATICO',
            'pago_id'            => $pago->id,
            'nota_venta_id'      => $pago->nota_venta_id,
            'cuenta_bancaria_id' => $pago->cuenta_id,
            'estado'             => 'CONFIRMADO',
        ]);
    }

    // Listar pagos pendientes
    public function pagosPendientes(Request $request)
    {
        $query = Pago::where('estado', 'PENDIENTE_PAGO')
            ->with(['notaVenta.cliente', 'notaVenta.asesor', 'cuota.planPago', 'metodoPago'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('concepto_pago')) {
            $query->where('concepto_pago', $request->concepto_pago);
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('created_at', [
                $request->fecha_inicio . ' 00:00:00',
                $request->fecha_fin   . ' 23:59:59',
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('notaVenta.cliente', function ($q) use ($search) {
                $q->where('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('ci', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->paginate($perPage), 200);
    }

    // Generar comprobante PDF de un pago procesado
    public function comprobante($id)
    {
        $pago = Pago::with([
            'notaVenta.cliente',
            'notaVenta.asesor',
            'notaVenta.propiedad.sectorUrbano.distrito.ciudad',
            'metodoPago',
            'cuenta',
        ])->findOrFail($id);

        if ($pago->estado !== 'PAGADO') {
            return response()->json(['message' => 'Solo se puede generar comprobante de pagos confirmados.'], 422);
        }

        $empresa  = MiEmpresa::first();
        $html     = $this->buildComprobanteHtml($pago, $empresa);

        $mpdf = new Mpdf([
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'format'        => 'A5',
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'comprobante-pago-' . str_pad($pago->id, 5, '0', STR_PAD_LEFT) . '.pdf';

        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildComprobanteHtml(Pago $pago, ?MiEmpresa $empresa): string
    {
        // Pre-calcular todos los valores para evitar expresiones complejas en el heredoc
        $empresa_nombre   = $empresa ? $empresa->nombre    : 'Sistema Multilider';
        $empresa_telefono = $empresa ? ($empresa->telefono  ?? '') : '';
        $empresa_email    = $empresa ? ($empresa->email     ?? '') : '';
        $empresa_dir      = $empresa ? ($empresa->direccion ?? '') : '';

        $notaVenta    = $pago->notaVenta;
        $cliente      = $notaVenta ? $notaVenta->cliente   : null;
        $propiedad    = $notaVenta ? $notaVenta->propiedad : null;

        $cli_nombre   = $cliente   ? $cliente->nombre_completo      : '-';
        $cli_ci       = $cliente   ? $cliente->ci                   : '-';
        $cli_telefono = $cliente   ? ($cliente->telefono ?? '-')     : '-';

        $prop_codigo  = $propiedad ? ($propiedad->codigo ?? '-')     : '-';
        $prop_tipo    = $propiedad ? ($propiedad->tipo   ?? '-')     : '-';
        $sector       = ($propiedad && $propiedad->sectorUrbano)
                            ? $propiedad->sectorUrbano->nombre
                            : '-';
        $ciudad       = ($propiedad && $propiedad->sectorUrbano && $propiedad->sectorUrbano->distrito && $propiedad->sectorUrbano->distrito->ciudad)
                            ? $propiedad->sectorUrbano->distrito->ciudad->nombre
                            : '';
        $ubicacion    = $ciudad ? "$sector, $ciudad" : $sector;

        $conceptoMap  = [
            'VENTA_CONTADO' => 'Pago al Contado',
            'CUOTA_INICIAL' => 'Cuota Inicial',
            'CUOTA'         => 'Cuota de Crédito',
            'OTRO'          => 'Otro',
        ];
        $concepto     = isset($conceptoMap[$pago->concepto_pago]) ? $conceptoMap[$pago->concepto_pago] : $pago->concepto_pago;
        $nroComp      = 'COMP-' . str_pad($pago->id, 5, '0', STR_PAD_LEFT);
        $nroVenta     = 'VTA-'  . str_pad($pago->nota_venta_id, 5, '0', STR_PAD_LEFT);
        $fecha        = $pago->fecha_pago ? \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') : '-';
        $metodo       = ($pago->metodoPago && $pago->metodoPago->nombre_metodo) ? $pago->metodoPago->nombre_metodo : '-';
        $cuenta       = ($pago->cuenta    && $pago->cuenta->nombre)             ? $pago->cuenta->nombre             : '-';
        $observ       = $pago->observaciones ? $pago->observaciones : '-';
        $monto        = $this->fmt($pago->monto);
        $generado     = $this->hoy();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; margin: 0; }
  .header { background: #1a3a5c; color: white; padding: 14px 20px; border-radius: 6px 6px 0 0; }
  .header h1 { margin: 0 0 2px; font-size: 17px; letter-spacing: 1px; }
  .header p  { margin: 0; font-size: 9px; opacity: 0.85; }
  .comp-num  { text-align: right; background: #f0f4f8; padding: 8px 20px; border-bottom: 2px solid #1a3a5c; }
  .comp-num span { font-size: 14px; font-weight: bold; color: #1a3a5c; }
  .section   { padding: 10px 20px; border-bottom: 1px solid #e0e0e0; }
  .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; font-weight: bold; }
  .row { display: flex; gap: 20px; }
  .col { flex: 1; }
  .label { font-size: 9px; color: #666; margin-bottom: 2px; }
  .value { font-size: 11px; font-weight: bold; color: #222; }
  .monto-box { background: #e8f5e9; border: 1.5px solid #27ae60; border-radius: 6px; padding: 10px 20px; text-align: center; margin: 12px 20px; }
  .monto-box .monto-label { font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
  .monto-box .monto-val   { font-size: 22px; font-weight: bold; color: #1b5e20; margin-top: 4px; }
  .footer { padding: 12px 20px; text-align: center; font-size: 9px; color: #999; }
  .badge-pagado { display: inline-block; background: #27ae60; color: white; padding: 2px 10px; border-radius: 20px; font-size: 9px; font-weight: bold; letter-spacing: 1px; }
</style>
</head>
<body>
<div class="header">
  <h1>$empresa_nombre</h1>
  <p>$empresa_dir &nbsp;|&nbsp; $empresa_telefono &nbsp;|&nbsp; $empresa_email</p>
</div>

<div class="comp-num">
  <span>$nroComp</span> &nbsp;&nbsp;
  <span class="badge-pagado">PAGADO</span>
</div>

<div class="section">
  <div class="section-title">Datos del Cliente</div>
  <div class="row">
    <div class="col">
      <div class="label">Nombre Completo</div>
      <div class="value">$cli_nombre</div>
    </div>
    <div class="col">
      <div class="label">C.I.</div>
      <div class="value">$cli_ci</div>
    </div>
    <div class="col">
      <div class="label">Teléfono</div>
      <div class="value">$cli_telefono</div>
    </div>
  </div>
</div>

<div class="section">
  <div class="section-title">Detalle de la Venta</div>
  <div class="row">
    <div class="col">
      <div class="label">N° Venta</div>
      <div class="value">$nroVenta</div>
    </div>
    <div class="col">
      <div class="label">Propiedad</div>
      <div class="value">$prop_codigo – $prop_tipo</div>
    </div>
    <div class="col">
      <div class="label">Ubicación</div>
      <div class="value">$ubicacion</div>
    </div>
  </div>
</div>

<div class="section">
  <div class="section-title">Detalle del Pago</div>
  <div class="row">
    <div class="col">
      <div class="label">Concepto</div>
      <div class="value">$concepto</div>
    </div>
    <div class="col">
      <div class="label">Fecha de Pago</div>
      <div class="value">$fecha</div>
    </div>
  </div>
  <div class="row" style="margin-top:8px;">
    <div class="col">
      <div class="label">Método de Pago</div>
      <div class="value">$metodo</div>
    </div>
    <div class="col">
      <div class="label">Cuenta / Destino</div>
      <div class="value">$cuenta</div>
    </div>
    <div class="col">
      <div class="label">Observaciones</div>
      <div class="value">$observ</div>
    </div>
  </div>
</div>

<div class="monto-box">
  <div class="monto-label">Monto Pagado</div>
  <div class="monto-val">Bs. $monto</div>
</div>

<div class="footer">
  Este comprobante es válido como constancia de pago. &nbsp;|&nbsp; Generado el $generado
</div>
</body>
</html>
HTML;
    }

    private function pad($n, $len = 5): string { return str_pad((int)$n, $len, '0', STR_PAD_LEFT); }
    private function fmt($v): string { return number_format((float)$v, 2, '.', ','); }
    private function hoy(): string { return \Carbon\Carbon::now()->format('d/m/Y H:i'); }
}
