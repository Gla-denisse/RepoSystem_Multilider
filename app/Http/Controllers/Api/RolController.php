<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index() {
        return response()->json(Rol::all(), 200);
    }

    public function store(Request $request) {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255'
        ]);

        $rol = Rol::create($request->all());
        return response()->json(['message' => 'Rol creado', 'data' => $rol], 201);
    }

    public function show($id) {
        return response()->json(Rol::findOrFail($id), 200);
    }

    public function update(Request $request, $id) {
        $rol = Rol::findOrFail($id);
        $rol->update($request->all());
        return response()->json(['message' => 'Rol actualizado', 'data' => $rol], 200);
    }

    public function destroy($id) {
        $rol = Rol::findOrFail($id);
        $rol->estado = false; // Desactivación lógica
        $rol->save();
        return response()->json(['message' => 'Rol desactivado'], 200);
    }

    public function getPermisos($id) {
        $rol = Rol::with('permisos')->findOrFail($id);
        // Devolvemos un array simple de IDs, ej: [1, 3, 5]
        return response()->json($rol->permisos->pluck('id')); 
    }

    // Sincronizar todos los permisos de golpe
    public function syncPermisos(Request $request, $id) {
        $request->validate([
            'permisos' => 'array' // Esperamos un array de IDs
        ]);

        $rol = Rol::findOrFail($id);
        
        // El método sync() de Laravel borra los que ya no están en el array y agrega los nuevos automáticamente
        $rol->permisos()->sync($request->permisos);

        return response()->json(['message' => 'Permisos sincronizados correctamente']);
    }
}
