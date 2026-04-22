<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manzano;
use Illuminate\Http\Request;

class ManzanoController extends Controller
{
    // 1. Obtener todos los manzanos (CON BÚSQUEDA Y PAGINACIÓN)
    public function index(Request $request) {
        $query = Manzano::query();

        // Si el frontend envía una búsqueda (por código)
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhere('descripcion', 'LIKE', "%{$search}%");
        }

        // Paginamos de a 10 registros por página
        $perPage = $request->input('per_page', 10);
        $manzanos = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($manzanos, 200);
    }

    // 2. Crear un nuevo manzano
    public function store(Request $request) {
        $validatedData = $request->validate([
            // unique:manzanos,codigo -> Evita que se repita el código (ej. MZ-01)
            'codigo' => 'required|string|max:50|unique:manzanos,codigo',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        $manzano = Manzano::create($validatedData);
        
        return response()->json(['message' => 'Manzano registrado con éxito', 'data' => $manzano], 201);
    }

    // 3. Mostrar un manzano específico
    public function show($id) {
        $manzano = Manzano::findOrFail($id);
        return response()->json($manzano, 200);
    }

    // 4. Actualizar un manzano
    public function update(Request $request, $id) {
        $manzano = Manzano::findOrFail($id);

        $validatedData = $request->validate([
            // Ignoramos el ID actual para no dar error de código duplicado si no se modificó
            'codigo' => 'required|string|max:50|unique:manzanos,codigo,' . $manzano->id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        $manzano->update($validatedData);
        
        return response()->json(['message' => 'Datos del manzano actualizados', 'data' => $manzano], 200);
    }

    // 5. Activar / Desactivar manzano (Toggle)
    public function destroy($id) {
        $manzano = Manzano::findOrFail($id);
        
        // Invertimos el estado actual
        $manzano->estado = !$manzano->estado;
        $manzano->save();

        $mensaje = $manzano->estado ? 'Manzano activado correctamente' : 'Manzano desactivado correctamente';

        return response()->json(['message' => $mensaje, 'estado' => $manzano->estado], 200);
    }
}