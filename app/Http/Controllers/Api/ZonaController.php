<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use Illuminate\Http\Request;

class ZonaController extends Controller
{
    // Listar todas las zonas (opcionalmente filtradas por ciudad)
    public function index(Request $request)
    {
        $query = Zona::with('ciudad');

        if ($request->has('ciudad_id')) {
            $query->where('ciudad_id', $request->ciudad_id);
        }

        return response()->json($query->get(), 200);
    }

    // Crear una nueva zona
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ciudad_id' => 'required|exists:ciudades,id',
            'nombre'    => 'required|string|max:255',
        ]);

        $zona = Zona::create($validated);

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
        ]);

        $zona->update($validated);

        return response()->json([
            'message' => 'Zona actualizada',
            'data'    => $zona->load('ciudad')
        ], 200);
    }

    // Eliminar una zona
    public function destroy($id)
    {
        $zona = Zona::findOrFail($id);
        $zona->delete();

        return response()->json([
            'message' => 'Zona eliminada correctamente'
        ], 200);
    }
}