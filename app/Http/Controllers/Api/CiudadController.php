<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use Illuminate\Http\Request;

class CiudadController extends Controller
{
    // Listar las ciudades con paginación y filtro de búsqueda (Solo activas por defecto)
    public function index(Request $request)
    {
        // 1. Iniciamos la consulta base del modelo Ciudad (Solo activos por defecto)
        // $query = Ciudad::where('estado', true);
        $query = Ciudad::query();

        // 2. Verificamos si la petición incluye un parámetro de búsqueda
        if ($request->has('search') && $request->search != '') {
            $searchTerm = $request->search;
            
            // 3. Filtramos las ciudades donde el nombre o departamento contenga el término
            $query->where(function($q) use ($searchTerm) {
                $q->where('nombre', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('departamento', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        // 4. Definimos cuántos registros por página queremos (por defecto 10)
        $perPage = $request->input('per_page', 10);

        // 5. Aplicamos el ordenamiento y la paginación
        $ciudades = $query->orderBy('id', 'desc')->paginate($perPage);

        // 6. Retornamos los datos al frontend en formato JSON
        return response()->json($ciudades, 200);
    }
    
    // Crear una nueva ciudad
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'departamento' => 'required|string|max:255',
            'estado' => 'nullable|boolean',
        ]);

        $ciudad = Ciudad::create([
            'nombre' => $validated['nombre'],
            'departamento' => $validated['departamento'],
            'estado' => $validated['estado'] ?? true,
        ]);

        return response()->json([
            'message' => 'Ciudad creada exitosamente',
            'data' => $ciudad
        ], 201);
    }

    // Mostrar una ciudad específica
    public function show($id)
    {
        $ciudad = Ciudad::findOrFail($id);
        return response()->json($ciudad, 200);
    }

    // Actualizar una ciudad
    public function update(Request $request, $id)
    {
        $ciudad = Ciudad::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'departamento' => 'required|string|max:255',
            'estado' => 'nullable|boolean',
        ]);

        $ciudad->update([
            'nombre' => $validated['nombre'],
            'departamento' => $validated['departamento'],
            'estado' => $validated['estado'] ?? $ciudad->estado,
        ]);

        return response()->json([
            'message' => 'Ciudad actualizada',
            'data' => $ciudad
        ], 200);
    }

    // Eliminar una ciudad (Eliminación lógica)
    public function destroy($id)
    {
        $ciudad = Ciudad::findOrFail($id);
        
        // Cambio de estado (Eliminación lógica)
        $ciudad->estado = !$ciudad->estado;
        $ciudad->save();

        $mensaje = $ciudad->estado ? 'Ciudad habilitada correctamente' : 'Ciudad eliminada (inhabilitada) correctamente';

        return response()->json([
            'message' => $mensaje,
            'estado' => $ciudad->estado
        ], 200);
    }
}
