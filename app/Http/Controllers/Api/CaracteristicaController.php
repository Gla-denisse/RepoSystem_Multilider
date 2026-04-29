<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caracteristica;
use Illuminate\Http\Request;

class CaracteristicaController extends Controller
{
    // 1. Listar características (Con Paginación, Búsqueda y Filtro)
    public function index(Request $request)
    {
        $query = Caracteristica::query();

        // Buscador por nombre
        if ($request->has('search') && $request->search !== '') {
            $query->where('nombre', 'LIKE', '%' . $request->search . '%');
        }

        // Filtro por tipo (Ej: Mostrar solo las características "Internas")
        if ($request->has('tipo') && $request->tipo !== '') {
            $query->where('tipo', $request->tipo);
        }

        // Paginación dinámica (por defecto 10)
        $perPage = $request->input('per_page', 10);
        
        // Ordenamos alfabéticamente para que sea más fácil buscar visualmente
        $caracteristicas = $query->orderBy('nombre', 'asc')->paginate($perPage);

        return response()->json($caracteristicas, 200);
    }

    // 2. Crear nueva característica
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:caracteristicas,nombre',
            'tipo'   => 'nullable|string|max:100',
        ]);

        $caracteristica = Caracteristica::create($validated);

        return response()->json([
            'message' => 'Característica registrada',
            'data'    => $caracteristica
        ], 201);
    }

    // 3. Mostrar una específica
    public function show($id)
    {
        $caracteristica = Caracteristica::findOrFail($id);
        return response()->json($caracteristica, 200);
    }

    // 4. Actualizar
    public function update(Request $request, $id)
    {
        $caracteristica = Caracteristica::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:caracteristicas,nombre,' . $id,
            'tipo'   => 'nullable|string|max:100',
        ]);

        $caracteristica->update($validated);

        return response()->json([
            'message' => 'Característica actualizada',
            'data'    => $caracteristica
        ], 200);
    }

    // 5. Eliminar
    public function destroy($id)
    {
        $caracteristica = Caracteristica::findOrFail($id);
        $caracteristica->delete();

        return response()->json([
            'message' => 'Característica eliminada'
        ], 200);
    }
}