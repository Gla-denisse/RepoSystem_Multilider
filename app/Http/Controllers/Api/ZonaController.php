<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    // Listar todas las zonas (Solo activas por defecto)
    public function index(Request $request)
    {
        // 1. Iniciamos la consulta cargando la relación 'ciudad' y filtrando por estado activo
        // $query = Zona::with('ciudad')->where('estado', true);
        $query = Zona::query();

        // 2. Filtro exacto por ciudad_id
        if ($request->has('ciudad_id') && $request->ciudad_id != '') {
            $query->where('ciudad_id', $request->ciudad_id);
        }

        // 3. Filtro dinámico de búsqueda (search)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = $request->search;
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('nombre', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhereHas('ciudad', function($subQuery) use ($searchTerm) {
                      $subQuery->where('nombre', 'LIKE', '%' . $searchTerm . '%');
                  });
            });
        }

        // 4. Capturamos la cantidad de registros por página (10 por defecto)
        $perPage = $request->input('per_page', 10);

        // 5. Ordenamos y aplicamos la paginación
        $zonas = $query->orderBy('id', 'desc')->paginate($perPage);

        // 6. Retornamos la respuesta
        return response()->json($zonas, 200);
    }

    // Crear una nueva zona
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ciudad_id' => 'required|exists:ciudades,id',
            'nombre'    => 'required|string|max:255',
            'estado'    => 'nullable|boolean',
        ]);

        $zona = Zona::create([
            'ciudad_id' => $validated['ciudad_id'],
            'nombre'    => $validated['nombre'],
            'estado'    => $validated['estado'] ?? true,
        ]);

        return response()->json([
            'message' => 'Zona registrada exitosamente',
            'data'    => $zona->load('ciudad')
        ], 201);
    }

    // Mostrar una zona específica
    public function show($id)
    {
        $zona = Zona::with('ciudad')->findOrFail($id);
        return response()->json($zona, 200);
    }

    // Actualizar una zona
    public function update(Request $request, $id)
    {
        $zona = Zona::findOrFail($id);

        $validated = $request->validate([
            'ciudad_id' => 'required|exists:ciudades,id',
            'nombre'    => 'required|string|max:255',
            'estado'    => 'nullable|boolean',
        ]);

        $zona->update([
            'ciudad_id' => $validated['ciudad_id'],
            'nombre'    => $validated['nombre'],
            'estado'    => $validated['estado'] ?? $zona->estado,
        ]);

        return response()->json([
            'message' => 'Zona actualizada',
            'data'    => $zona->load('ciudad')
        ], 200);
    }

    // Eliminar una zona (Eliminación lógica)
    public function destroy($id)
    {
        $zona = Zona::findOrFail($id);
        
        // Cambio de estado (Eliminación lógica)
        $zona->estado = !$zona->estado;
        $zona->save();

        $mensaje = $zona->estado ? 'Zona habilitada correctamente' : 'Zona eliminada (inhabilitada) correctamente';

        return response()->json([
            'message' => $mensaje,
            'estado'  => $zona->estado
        ], 200);
    }
}
