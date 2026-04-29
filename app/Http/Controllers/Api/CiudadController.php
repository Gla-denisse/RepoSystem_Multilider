<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use Illuminate\Http\Request;

class CiudadController extends Controller
{
    // Listar todas las ciudades
    public function index()
    {
        return response()->json(Ciudad::all(), 200);
    }

    // Crear una nueva ciudad
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'departamento' => 'required|string|max:255',
        ]);

        $ciudad = Ciudad::create($validated);

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
        ]);

        $ciudad->update($validated);

        return response()->json([
            'message' => 'Ciudad actualizada',
            'data' => $ciudad
        ], 200);
    }

    // Eliminar una ciudad
    public function destroy($id)
    {
        $ciudad = Ciudad::findOrFail($id);
        $ciudad->delete();

        return response()->json([
            'message' => 'Ciudad eliminada correctamente'
        ], 200);
    }
}