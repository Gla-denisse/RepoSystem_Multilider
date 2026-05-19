<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Egreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EgresoController extends Controller
{
    // 1. Listar con filtros
    public function index(Request $request)
    {
        $query = Egreso::with([
            'notaVenta.cliente',
            'asesor',
            'cuentaBancaria',
            'registradoPor',
        ]);

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->origen);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('moneda')) {
            $query->where('moneda', $request->moneda);
        }

        if ($request->filled('asesor_id')) {
            $query->where('asesor_id', $request->asesor_id);
        }

        if ($request->filled('cuenta_bancaria_id')) {
            $query->where('cuenta_bancaria_id', $request->cuenta_bancaria_id);
        }

        if ($request->filled('nota_venta_id')) {
            $query->where('nota_venta_id', $request->nota_venta_id);
        }

        $perPage = $request->input('per_page', 15);
        $egresos = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        // Totales confirmados del período
        $baseQuery = Egreso::when($request->filled('fecha_inicio') && $request->filled('fecha_fin'), fn($q) =>
                $q->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            )
            ->whereIn('estado', ['PENDIENTE', 'PAGADO'])
            ->selectRaw('moneda, estado, SUM(monto) as total')
            ->groupBy('moneda', 'estado')
            ->get();

        return response()->json([
            'data'    => $egresos->items(),
            'meta'    => [
                'current_page' => $egresos->currentPage(),
                'last_page'    => $egresos->lastPage(),
                'per_page'     => $egresos->perPage(),
                'total'        => $egresos->total(),
            ],
            'totales' => $baseQuery,
        ], 200);
    }

    // 2. Registrar egreso manual
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha'              => 'required|date',
            'concepto'           => 'required|string|max:255',
            'categoria'          => 'required|in:COMISION_ASESOR,GASTO_ADMINISTRATIVO,GASTO_OPERATIVO,GASTO_MARKETING,PAGO_PROPIETARIO,OTRO',
            'monto'              => 'required|numeric|min:0.01',
            'moneda'             => 'required|in:Bs,$',
            'nota_venta_id'      => 'nullable|exists:notas_ventas,id',
            'asesor_id'          => 'nullable|exists:asesores,id',
            'cuenta_bancaria_id' => 'nullable|exists:cuentas_bancarias,id',
            'beneficiario'       => 'nullable|string|max:255',
            'comprobante'        => 'nullable|string|max:500',
            'observaciones'      => 'nullable|string',
            'estado'             => 'nullable|in:PENDIENTE,PAGADO',
        ]);

        try {
            $egreso = Egreso::create([
                ...$validated,
                'origen'  => 'MANUAL',
                'user_id' => auth()->id(),
                'estado'  => $validated['estado'] ?? 'PENDIENTE',
            ]);

            return response()->json([
                'message' => 'Egreso registrado con éxito',
                'data'    => $egreso->load('asesor', 'notaVenta.cliente', 'cuentaBancaria', 'registradoPor'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar el egreso: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver detalle
    public function show($id)
    {
        $egreso = Egreso::with([
            'notaVenta.cliente',
            'notaVenta.asesor',
            'asesor',
            'cuentaBancaria',
            'registradoPor',
        ])->findOrFail($id);

        return response()->json($egreso, 200);
    }

    // 4. Editar egreso
    public function update(Request $request, $id)
    {
        $egreso = Egreso::findOrFail($id);

        if ($egreso->estado === 'ANULADO') {
            return response()->json(['message' => 'No se puede editar un egreso anulado.'], 400);
        }

        $validated = $request->validate([
            'fecha'              => 'sometimes|date',
            'concepto'           => 'sometimes|string|max:255',
            'categoria'          => 'sometimes|in:COMISION_ASESOR,GASTO_ADMINISTRATIVO,GASTO_OPERATIVO,GASTO_MARKETING,PAGO_PROPIETARIO,OTRO',
            'monto'              => 'sometimes|numeric|min:0.01',
            'moneda'             => 'sometimes|in:Bs,$',
            'asesor_id'          => 'nullable|exists:asesores,id',
            'cuenta_bancaria_id' => 'nullable|exists:cuentas_bancarias,id',
            'beneficiario'       => 'nullable|string|max:255',
            'comprobante'        => 'nullable|string|max:500',
            'observaciones'      => 'nullable|string',
        ]);

        $egreso->update($validated);

        return response()->json([
            'message' => 'Egreso actualizado con éxito',
            'data'    => $egreso->load('asesor', 'notaVenta.cliente', 'cuentaBancaria', 'registradoPor'),
        ], 200);
    }

    // 5. Marcar egreso como PAGADO (desembolso real)
    public function pagar(Request $request, $id)
    {
        $egreso = Egreso::findOrFail($id);

        if ($egreso->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden pagar egresos en estado PENDIENTE.'], 400);
        }

        $validated = $request->validate([
            'fecha'              => 'required|date',
            'cuenta_bancaria_id' => 'nullable|exists:cuentas_bancarias,id',
            'comprobante'        => 'nullable|string|max:500',
            'observaciones'      => 'nullable|string',
        ]);

        $egreso->update([
            'estado'             => 'PAGADO',
            'fecha'              => $validated['fecha'],
            'cuenta_bancaria_id' => $validated['cuenta_bancaria_id'] ?? $egreso->cuenta_bancaria_id,
            'comprobante'        => $validated['comprobante'] ?? $egreso->comprobante,
            'observaciones'      => $validated['observaciones'] ?? $egreso->observaciones,
            'user_id'            => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Egreso marcado como pagado correctamente.',
            'data'    => $egreso->load('asesor', 'notaVenta.cliente', 'cuentaBancaria'),
        ], 200);
    }

    // 6. Anular egreso
    public function anular($id)
    {
        $egreso = Egreso::findOrFail($id);

        if ($egreso->estado === 'ANULADO') {
            return response()->json(['message' => 'El egreso ya está anulado.'], 400);
        }

        $egreso->update(['estado' => 'ANULADO']);

        return response()->json(['message' => 'Egreso anulado correctamente.', 'data' => $egreso], 200);
    }

    // 7. Resumen por período (para dashboard)
    public function resumen(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $egresos = Egreso::whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->whereIn('estado', ['PENDIENTE', 'PAGADO'])
            ->get();

        $pagados   = $egresos->where('estado', 'PAGADO');
        $pendientes = $egresos->where('estado', 'PENDIENTE');

        return response()->json([
            'pagado_bs'           => $pagados->where('moneda', 'Bs')->sum('monto'),
            'pagado_usd'          => $pagados->where('moneda', '$')->sum('monto'),
            'pendiente_bs'        => $pendientes->where('moneda', 'Bs')->sum('monto'),
            'pendiente_usd'       => $pendientes->where('moneda', '$')->sum('monto'),
            'por_categoria'       => $egresos->groupBy('categoria')->map(fn($g) => [
                'cantidad'      => $g->count(),
                'total_bs'      => $g->where('moneda', 'Bs')->sum('monto'),
                'total_usd'     => $g->where('moneda', '$')->sum('monto'),
                'pendiente_bs'  => $g->where('estado', 'PENDIENTE')->where('moneda', 'Bs')->sum('monto'),
                'pendiente_usd' => $g->where('estado', 'PENDIENTE')->where('moneda', '$')->sum('monto'),
            ]),
            'comisiones_pendientes' => $egresos->where('categoria', 'COMISION_ASESOR')->where('estado', 'PENDIENTE')->count(),
            'periodo'             => ['inicio' => $request->fecha_inicio, 'fin' => $request->fecha_fin],
        ], 200);
    }
}
