<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    public function index() {
        return response()->json(Permiso::all(), 200);
    }

    public function store(Request $request) {
        // 1. Validamos y guardamos en una variable segura
        $validatedData = $request->validate([
            // unique:permisos,nombre -> Evita duplicados en la tabla permisos
            'nombre' => 'required|string|max:100|unique:permisos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        // 2. Creamos usando SOLO los datos validados
        $permiso = Permiso::create($validatedData);
        
        return response()->json(['message' => 'Permiso creado', 'data' => $permiso], 201);
    }

    public function show($id) {
        return response()->json(Permiso::findOrFail($id), 200);
    }

    public function update(Request $request, $id) {
        $permiso = Permiso::findOrFail($id);

        // 1. Validamos (ignorando el ID actual para que deje guardar si solo se editó la descripción)
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:100|unique:permisos,nombre,' . $permiso->id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean'
        ]);

        // 2. Actualizamos usando SOLO los datos validados
        $permiso->update($validatedData);
        
        return response()->json(['message' => 'Permiso actualizado', 'data' => $permiso], 200);
    }

    public function destroy($id) {
        Permiso::destroy($id); // Eliminación física, ya que no tiene campo de estado
        return response()->json(['message' => 'Permiso eliminado'], 200);
    }
}
