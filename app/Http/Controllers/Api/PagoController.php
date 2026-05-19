<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\NotaVenta;
use App\Models\Cuota;
use App\Models\Ingreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    // 1. Listar pagos con filtros
    public function index(Request $request)
    {
        $query = Pago::with(['notaVenta.cliente', 'cuota.planPago']);

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
        // Derivar moneda desde la propiedad de la venta
        $moneda = $pago->notaVenta?->propiedad?->moneda ?? 'Bs';

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
            ->with(['notaVenta.cliente', 'notaVenta.asesor', 'cuota.planPago'])
            ->orderBy('created_at', 'desc');

        // Filtro por concepto de pago (para separar ventas al contado de cuotas)
        if ($request->filled('concepto_pago')) {
            $query->where('concepto_pago', $request->concepto_pago);
        }

        $perPage = $request->input('per_page', 10);
        $pagos = $query->paginate($perPage);

        return response()->json($pagos, 200);
    }
}
