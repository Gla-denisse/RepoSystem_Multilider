<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propietario;
use Illuminate\Http\Request;

class PropietarioController extends Controller
{
    // 1. Obtener todos los propietarios
    public function index(Request $request) {
        $query = Propietario::query();

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('ci', 'LIKE', "%{$search}%");
        }

        // Paginamos de a 10 registros por página
        $perPage = $request->input('per_page', 10);
        
        // Retorna un objeto de paginación (incluye links, current_page, y la 'data')
        $propietarios = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($propietarios, 200);
    }

    // 2. Crear un nuevo propietario
    public function store(Request $request) {
        $validatedData = $request->validate([
            'ci' => 'required|string|max:50|unique:propietarios,ci',
            'lugar_expedicion' => 'nullable|string|max:20',
            'nombre_completo' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        $propietario = Propietario::create($validatedData);
        
        return response()->json(['message' => 'Propietario registrado con éxito', 'data' => $propietario], 201);
    }

    // 3. Mostrar un propietario específico
    public function show($id) {
        $propietario = Propietario::findOrFail($id);
        return response()->json($propietario, 200);
    }

    // 4. Actualizar un propietario
    public function update(Request $request, $id) {
        $propietario = Propietario::findOrFail($id);

        $validatedData = $request->validate([
            // Ignoramos el CI actual para que no marque error de duplicado si no se modificó
            'ci' => 'required|string|max:50|unique:propietarios,ci,' . $propietario->id,
            'lugar_expedicion' => 'nullable|string|max:20',
            'nombre_completo' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        $propietario->update($validatedData);
        
        return response()->json(['message' => 'Datos del propietario actualizados', 'data' => $propietario], 200);
    }

    // 5. Activar / Desactivar propietario (Toggle)
    public function destroy($id) {
        $propietario = Propietario::findOrFail($id);
        
        // Invertimos el estado actual
        $propietario->estado = !$propietario->estado;
        $propietario->save();

        $mensaje = $propietario->estado ? 'Propietario activado correctamente' : 'Propietario desactivado correctamente';

        return response()->json(['message' => $mensaje, 'estado' => $propietario->estado], 200);
    }
}