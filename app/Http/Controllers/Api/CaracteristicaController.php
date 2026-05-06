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
        // Iniciamos con los activos por defecto
        $query = Caracteristica::where('estado', true);

        // Buscador por nombre (usando filled para asegurar que no sea nulo ni vacío)
        if ($request->filled('search')) {
            $query->where('nombre', 'LIKE', '%' . $request->search . '%');
        }

        // Filtro por tipo
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Paginación dinámica (por defecto 10)
        $perPage = $request->input('per_page', 10);
        
        // Ordenamos alfabéticamente
        $caracteristicas = $query->orderBy('nombre', 'asc')->paginate($perPage);

        return response()->json($caracteristicas, 200);
    }

    // 2. Crear nueva característica
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:caracteristicas,nombre',
            'tipo'   => 'nullable|string|max:100',
            'estado' => 'nullable|boolean',
        ]);

        $caracteristica = Caracteristica::create([
            'nombre' => $validated['nombre'],
            'tipo'   => $validated['tipo'] ?? null,
            'estado' => $validated['estado'] ?? true,
        ]);

        return response()->json([
            'message' => 'Característica registrada exitosamente',
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
            'estado' => 'nullable|boolean',
        ]);

        $caracteristica->update([
            'nombre' => $validated['nombre'],
            'tipo'   => $validated['tipo'] ?? $caracteristica->tipo,
            'estado' => $validated['estado'] ?? $caracteristica->estado,
        ]);

        return response()->json([
            'message' => 'Característica actualizada correctamente',
            'data'    => $caracteristica
        ], 200);
    }

    // 5. Eliminar (Eliminación Lógica)
    public function destroy($id)
    {
        $caracteristica = Caracteristica::findOrFail($id);
        
        // Cambio de estado (Toggle)
        $caracteristica->estado = !$caracteristica->estado;
        $caracteristica->save();

        $mensaje = $caracteristica->estado ? 'Característica habilitada' : 'Característica inhabilitada correctamente';

        return response()->json([
            'message' => $mensaje,
            'estado'  => $caracteristica->estado
        ], 200);
    }
}
