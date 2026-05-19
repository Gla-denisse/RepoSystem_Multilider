<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngresoController extends Controller
{
    // 1. Listar con filtros
    public function index(Request $request)
    {
        $query = Ingreso::with([
            'notaVenta.cliente',
            'notaVenta.asesor',
            'cuentaBancaria',
            'registradoPor',
            'pago',
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

        if ($request->filled('cuenta_bancaria_id')) {
            $query->where('cuenta_bancaria_id', $request->cuenta_bancaria_id);
        }

        if ($request->filled('nota_venta_id')) {
            $query->where('nota_venta_id', $request->nota_venta_id);
        }

        $perPage = $request->input('per_page', 15);
        $ingresos = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        // Totales del período filtrado (sin paginar)
        $totales = Ingreso::when($request->filled('fecha_inicio') && $request->filled('fecha_fin'), fn($q) =>
                $q->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            )
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->estado))
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('moneda, SUM(monto) as total')
            ->groupBy('moneda')
            ->get();

        return response()->json([
            'data'    => $ingresos->items(),
            'meta'    => [
                'current_page' => $ingresos->currentPage(),
                'last_page'    => $ingresos->lastPage(),
                'per_page'     => $ingresos->perPage(),
                'total'        => $ingresos->total(),
            ],
            'totales' => $totales,
        ], 200);
    }

    // 2. Registrar ingreso manual
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha'              => 'required|date',
            'concepto'           => 'required|string|max:255',
            'categoria'          => 'required|in:VENTA_CONTADO,CUOTA_INICIAL,CUOTA,OTRO',
            'monto'              => 'required|numeric|min:0.01',
            'moneda'             => 'required|in:Bs,$',
            'cuenta_bancaria_id' => 'nullable|exists:cuentas_bancarias,id',
            'nota_venta_id'      => 'nullable|exists:notas_ventas,id',
            'comprobante'        => 'nullable|string|max:500',
            'observaciones'      => 'nullable|string',
        ]);

        try {
            $ingreso = Ingreso::create([
                ...$validated,
                'origen'  => 'MANUAL',
                'user_id' => auth()->id(),
                'estado'  => 'CONFIRMADO',
            ]);

            return response()->json([
                'message' => 'Ingreso registrado con éxito',
                'data'    => $ingreso->load('cuentaBancaria', 'notaVenta.cliente', 'registradoPor'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar el ingreso: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver detalle
    public function show($id)
    {
        $ingreso = Ingreso::with([
            'pago.notaVenta.cliente',
            'notaVenta.cliente',
            'notaVenta.asesor',
            'cuentaBancaria',
            'registradoPor',
        ])->findOrFail($id);

        return response()->json($ingreso, 200);
    }

    // 4. Editar ingreso (solo manuales o campos permitidos)
    public function update(Request $request, $id)
    {
        $ingreso = Ingreso::findOrFail($id);

        if ($ingreso->estado === 'ANULADO') {
            return response()->json(['message' => 'No se puede editar un ingreso anulado.'], 400);
        }

        $validated = $request->validate([
            'fecha'              => 'sometimes|date',
            'concepto'           => 'sometimes|string|max:255',
            'categoria'          => 'sometimes|in:VENTA_CONTADO,CUOTA_INICIAL,CUOTA,OTRO',
            'monto'              => 'sometimes|numeric|min:0.01',
            'moneda'             => 'sometimes|in:Bs,$',
            'cuenta_bancaria_id' => 'nullable|exists:cuentas_bancarias,id',
            'comprobante'        => 'nullable|string|max:500',
            'observaciones'      => 'nullable|string',
        ]);

        $ingreso->update($validated);

        return response()->json([
            'message' => 'Ingreso actualizado con éxito',
            'data'    => $ingreso->load('cuentaBancaria', 'notaVenta.cliente', 'registradoPor'),
        ], 200);
    }

    // 5. Anular ingreso
    public function anular($id)
    {
        $ingreso = Ingreso::findOrFail($id);

        if ($ingreso->estado === 'ANULADO') {
            return response()->json(['message' => 'El ingreso ya está anulado.'], 400);
        }

        $ingreso->update(['estado' => 'ANULADO']);

        return response()->json(['message' => 'Ingreso anulado correctamente.', 'data' => $ingreso], 200);
    }

    // 6. Resumen por período (para dashboard)
    public function resumen(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $ingresos = Ingreso::whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->where('estado', 'CONFIRMADO')
            ->get();

        return response()->json([
            'total_bs'            => $ingresos->where('moneda', 'Bs')->sum('monto'),
            'total_usd'           => $ingresos->where('moneda', '$')->sum('monto'),
            'por_categoria'       => $ingresos->groupBy('categoria')->map(fn($g) => [
                'cantidad' => $g->count(),
                'total_bs' => $g->where('moneda', 'Bs')->sum('monto'),
                'total_usd'=> $g->where('moneda', '$')->sum('monto'),
            ]),
            'automaticos'         => $ingresos->where('origen', 'AUTOMATICO')->count(),
            'manuales'            => $ingresos->where('origen', 'MANUAL')->count(),
            'periodo'             => ['inicio' => $request->fecha_inicio, 'fin' => $request->fecha_fin],
        ], 200);
    }
}
