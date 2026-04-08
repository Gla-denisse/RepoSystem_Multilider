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
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255'
        ]);

        $permiso = Permiso::create($request->all());
        return response()->json(['message' => 'Permiso creado', 'data' => $permiso], 201);
    }

    public function show($id) {
        return response()->json(Permiso::findOrFail($id), 200);
    }

    public function update(Request $request, $id) {
        $permiso = Permiso::findOrFail($id);
        $permiso->update($request->all());
        return response()->json(['message' => 'Permiso actualizado', 'data' => $permiso], 200);
    }

    public function destroy($id) {
        Permiso::destroy($id); // Eliminación física, ya que no tiene campo de estado
        return response()->json(['message' => 'Permiso eliminado'], 200);
    }
}
