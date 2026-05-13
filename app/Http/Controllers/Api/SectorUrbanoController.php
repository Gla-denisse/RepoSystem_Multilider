<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SectorUrbano;
use Illuminate\Http\Request;

class SectorUrbanoController extends Controller
{
    public function index(Request $request)
    {
        $query = SectorUrbano::with('distrito.ciudad');

        if ($request->filled('distrito_id')) {
            $query->where('distrito_id', $request->distrito_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', "%{$term}%")
                  ->orWhereHas('distrito', fn($s) => $s->where('nombre', 'LIKE', "%{$term}%"));
            });
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'distrito_id' => 'required|exists:distritos,id',
            'nombre'      => 'required|string|max:150',
            'tipo'        => 'required|in:Barrio,Urbanización,Condominio',
            'uv'          => 'nullable|string|max:20',
            'manzano'     => 'nullable|string|max:20',
            'estado'      => 'nullable|boolean',
        ]);

        $sector = SectorUrbano::create($validated + ['estado' => $validated['estado'] ?? true]);

        return response()->json([
            'message' => 'Sector urbano registrado exitosamente',
            'data'    => $sector->load('distrito.ciudad')
        ], 201);
    }

    public function show($id)
    {
        return response()->json(SectorUrbano::with('distrito.ciudad')->findOrFail($id), 200);
    }

    public function update(Request $request, $id)
    {
        $sector = SectorUrbano::findOrFail($id);

        $validated = $request->validate([
            'distrito_id' => 'required|exists:distritos,id',
            'nombre'      => 'required|string|max:150',
            'tipo'        => 'required|in:Barrio,Urbanización,Condominio',
            'uv'          => 'nullable|string|max:20',
            'manzano'     => 'nullable|string|max:20',
            'estado'      => 'nullable|boolean',
        ]);

        $sector->update($validated);

        return response()->json([
            'message' => 'Sector urbano actualizado',
            'data'    => $sector->load('distrito.ciudad')
        ], 200);
    }

    public function destroy($id)
    {
        $sector = SectorUrbano::findOrFail($id);
        $sector->estado = !$sector->estado;
        $sector->save();

        $mensaje = $sector->estado ? 'Sector habilitado correctamente' : 'Sector inhabilitado correctamente';
        return response()->json(['message' => $mensaje, 'estado' => $sector->estado], 200);
    }

    // Endpoint para obtener sectores de un distrito (usado en selectores dependientes)
    public function porDistrito($distritoId)
    {
        $sectores = SectorUrbano::where('distrito_id', $distritoId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($sectores, 200);
    }
}
