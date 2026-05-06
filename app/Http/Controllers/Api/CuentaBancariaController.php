<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use App\Models\MiEmpresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CuentaBancariaController extends Controller
{
    public function index(): JsonResponse
    {
        $cuentas = CuentaBancaria::where('estado', 'Activa')->get();
        return response()->json($cuentas);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:EFECTIVO,BANCARIA,DIGITAL,OTRA',
            'descripcion' => 'nullable|string',
            'banco' => 'nullable|string|max:100',
            'numero_cuenta' => 'nullable|string|max:50',
            'titular' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:50',
            'proveedor' => 'nullable|string|max:100',
            'codigo_integracion' => 'nullable|string|max:255',
            'saldo_inicial' => 'nullable|numeric|min:0',
            'estado' => 'in:Activa,Inactiva'
        ]);

        $empresa = MiEmpresa::first();
        $validated['mi_empresa_id'] = $empresa->id;

        $cuenta = CuentaBancaria::create($validated);

        return response()->json($cuenta, 201);
    }

    public function show($id): JsonResponse
    {
        $cuenta = CuentaBancaria::findOrFail($id);
        return response()->json($cuenta);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $cuenta = CuentaBancaria::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:EFECTIVO,BANCARIA,DIGITAL,OTRA',
            'descripcion' => 'nullable|string',
            'banco' => 'nullable|string|max:100',
            'numero_cuenta' => 'nullable|string|max:50',
            'titular' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:50',
            'proveedor' => 'nullable|string|max:100',
            'codigo_integracion' => 'nullable|string|max:255',
            'saldo_inicial' => 'nullable|numeric|min:0',
            'estado' => 'in:Activa,Inactiva'
        ]);

        $cuenta->update($validated);

        return response()->json($cuenta);
    }

    public function destroy($id): JsonResponse
    {
        $cuenta = CuentaBancaria::findOrFail($id);
        $cuenta->delete();

        return response()->json(['mensaje' => 'Cuenta bancaria eliminada'], 200);
    }
}
