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
        // 1. Guardamos los datos validados en una variable
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean' // Por si el frontend envía el estado
        ]);

        // 2. Usamos SOLO los datos validados (Seguridad contra inyecciones)
        $rol = Rol::create($validatedData);
        
        return response()->json(['message' => 'Rol creado', 'data' => $rol], 201);
    }

    public function show($id) {
        return response()->json(Rol::findOrFail($id), 200);
    }

    public function update(Request $request, $id) {
        $rol = Rol::findOrFail($id);

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:100|unique:roles,nombre,' . $rol->id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        $rol->update($validatedData);
        
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
        return response()->json($rol->permisos->pluck('id')); 
    }

    public function syncPermisos(Request $request, $id) {
        // CORRECCIÓN VITAL: Validación profunda del array
        $request->validate([
            // 'present' permite que se envíe un array vacío [] si se quieren quitar todos los permisos
            'permisos' => 'present|array', 
            
            // Verifica que CADA ítem dentro del array sea un número entero y exista en la BD
            'permisos.*' => 'integer|exists:permisos,id' 
        ]);

        $rol = Rol::findOrFail($id);
        
        $rol->permisos()->sync($request->permisos);

        return response()->json(['message' => 'Permisos sincronizados correctamente']);
    }
}