<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        $query = MetodoPago::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } else {
            $query->where('estado', 'Activo');
        }

        $perPage = $request->input('per_page', 100);
        $metodos = $query->paginate($perPage);

        return response()->json($metodos, 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre_metodo' => 'required|string|max:100|unique:metodos_pago',
            'estado' => 'required|in:Activo,Inactivo'
        ]);

        $metodo = MetodoPago::create($validatedData);
        return response()->json(['message' => 'Método de pago creado con éxito', 'data' => $metodo], 201);
    }

    public function show($id)
    {
        $metodo = MetodoPago::findOrFail($id);
        return response()->json($metodo, 200);
    }

    public function update(Request $request, $id)
    {
        $metodo = MetodoPago::findOrFail($id);

        $validatedData = $request->validate([
            'nombre_metodo' => 'sometimes|string|max:100|unique:metodos_pago,nombre_metodo,' . $id,
            'estado' => 'sometimes|in:Activo,Inactivo'
        ]);

        $metodo->update($validatedData);
        return response()->json(['message' => 'Método de pago actualizado con éxito', 'data' => $metodo], 200);
    }

    public function destroy($id)
    {
        $metodo = MetodoPago::findOrFail($id);
        $metodo->delete();
        return response()->json(['message' => 'Método de pago eliminado con éxito'], 200);
    }
}
