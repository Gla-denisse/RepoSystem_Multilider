<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetodoPagoCuentaDefault;
use App\Models\MetodoPago;
use App\Models\CuentaBancaria;
use App\Models\MiEmpresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MetodoPagoCuentaDefaultController extends Controller
{
    public function index(): JsonResponse
    {
        $empresa = MiEmpresa::first();
        $mapeos = MetodoPagoCuentaDefault::where('mi_empresa_id', $empresa->id)
            ->with(['metodoPago', 'cuenta'])
            ->get();

        return response()->json($mapeos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metodo_pago_id' => 'required|exists:metodos_pago,id',
            'cuenta_bancaria_id' => 'required|exists:cuentas_bancarias,id'
        ]);

        $empresa = MiEmpresa::first();

        // Verificar si ya existe este mapeo
        $existe = MetodoPagoCuentaDefault::where('mi_empresa_id', $empresa->id)
            ->where('metodo_pago_id', $validated['metodo_pago_id'])
            ->first();

        if ($existe) {
            // Actualizar si ya existe
            $existe->update(['cuenta_bancaria_id' => $validated['cuenta_bancaria_id']]);
            return response()->json($existe, 200);
        }

        // Crear nuevo mapeo
        $mapeo = MetodoPagoCuentaDefault::create([
            'mi_empresa_id' => $empresa->id,
            'metodo_pago_id' => $validated['metodo_pago_id'],
            'cuenta_bancaria_id' => $validated['cuenta_bancaria_id']
        ]);

        return response()->json($mapeo, 201);
    }

    public function show($id): JsonResponse
    {
        $mapeo = MetodoPagoCuentaDefault::with(['metodoPago', 'cuenta'])->findOrFail($id);
        return response()->json($mapeo);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $mapeo = MetodoPagoCuentaDefault::findOrFail($id);

        $validated = $request->validate([
            'cuenta_bancaria_id' => 'required|exists:cuentas_bancarias,id'
        ]);

        $mapeo->update($validated);

        return response()->json($mapeo);
    }

    public function destroy($id): JsonResponse
    {
        $mapeo = MetodoPagoCuentaDefault::findOrFail($id);
        $mapeo->delete();

        return response()->json(['mensaje' => 'Mapeo eliminado'], 200);
    }

    public function obtenerCuentaPorMetodo($metodoId): JsonResponse
    {
        $empresa = MiEmpresa::first();

        $mapeo = MetodoPagoCuentaDefault::where('mi_empresa_id', $empresa->id)
            ->where('metodo_pago_id', $metodoId)
            ->with('cuenta')
            ->first();

        if (!$mapeo) {
            return response()->json(['cuenta' => null, 'mensaje' => 'No hay cuenta asignada para este método']);
        }

        return response()->json($mapeo->cuenta);
    }
}
