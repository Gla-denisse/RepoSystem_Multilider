<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distrito;
use Illuminate\Http\Request;

class DistritoController extends Controller
{
    public function index(Request $request)
    {
        $query = Distrito::with('ciudad');

        if ($request->filled('ciudad_id')) {
            $query->where('ciudad_id', $request->ciudad_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', "%{$term}%")
                  ->orWhereHas('ciudad', fn($s) => $s->where('nombre', 'LIKE', "%{$term}%"));
            });
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ciudad_id' => 'required|exists:ciudades,id',
            'nombre'    => 'required|string|max:255',
            'estado'    => 'nullable|boolean',
        ]);

        $distrito = Distrito::create([
            'ciudad_id' => $validated['ciudad_id'],
            'nombre'    => $validated['nombre'],
            'estado'    => $validated['estado'] ?? true,
        ]);

        return response()->json([
            'message' => 'Distrito registrado exitosamente',
            'data'    => $distrito->load('ciudad')
        ], 201);
    }

    public function show($id)
    {
        return response()->json(Distrito::with('ciudad')->findOrFail($id), 200);
    }

    public function update(Request $request, $id)
    {
        $distrito = Distrito::findOrFail($id);

        $validated = $request->validate([
            'ciudad_id' => 'required|exists:ciudades,id',
            'nombre'    => 'required|string|max:255',
            'estado'    => 'nullable|boolean',
        ]);

        $distrito->update([
            'ciudad_id' => $validated['ciudad_id'],
            'nombre'    => $validated['nombre'],
            'estado'    => $validated['estado'] ?? $distrito->estado,
        ]);

        return response()->json([
            'message' => 'Distrito actualizado',
            'data'    => $distrito->load('ciudad')
        ], 200);
    }

    public function destroy($id)
    {
        $distrito = Distrito::findOrFail($id);
        $distrito->estado = !$distrito->estado;
        $distrito->save();

        $mensaje = $distrito->estado ? 'Distrito habilitado correctamente' : 'Distrito inhabilitado correctamente';
        return response()->json(['message' => $mensaje, 'estado' => $distrito->estado], 200);
    }
}
