<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ubicacion;
use Illuminate\Http\Request;

class UbicacionController extends Controller
{
    // 1. Obtener todas las ubicaciones (Rara vez se usará directamente, pero es buena práctica tenerlo)
    public function index() {
        return response()->json(Ubicacion::all(), 200);
    }

    // 2. Crear una nueva ubicación
    public function store(Request $request) {
        $validatedData = $request->validate([
            'referencia' => 'nullable|string|max:255',
            // Validamos que si envían url_maps, tenga formato de enlace válido
            'url_maps' => 'nullable|url', 
            'longitud'   => 'nullable|numeric', 
            'latitud'    => 'nullable|numeric'
        ]);

        // 🛡️ LIMPIEZA DE SEGURIDAD: 
        // Si viene la URL, le quitamos cualquier símbolo '$' accidental antes de guardar.
        if (isset($validatedData['url_maps'])) {
            $validatedData['url_maps'] = str_replace('$', '', $validatedData['url_maps']);
        }

        $ubicacion = Ubicacion::create($validatedData);
        
        return response()->json(['message' => 'Ubicación registrada', 'data' => $ubicacion], 201);
    }

    public function update(Request $request, $id) {
        $ubicacion = Ubicacion::findOrFail($id);

        $validatedData = $request->validate([
            'referencia' => 'nullable|string|max:255',
            'url_maps' => 'nullable|url',
            'longitud'   => 'nullable|numeric', 
            'latitud'    => 'nullable|numeric'
        ]);

        // 🛡️ LIMPIEZA DE SEGURIDAD: 
        // Aplicamos el mismo filtro al actualizar.
        if (isset($validatedData['url_maps'])) {
            $validatedData['url_maps'] = str_replace('$', '', $validatedData['url_maps']);
        }

        $ubicacion->update($validatedData);
        
        return response()->json(['message' => 'Ubicación actualizada', 'data' => $ubicacion], 200);
    }

    // 3. Mostrar una ubicación específica
    public function show($id) {
        $ubicacion = Ubicacion::findOrFail($id);
        return response()->json($ubicacion, 200);
    }

    // 5. Borrado Físico (Hard Delete)
    public function destroy($id) {
        $ubicacion = Ubicacion::findOrFail($id);
        
        // Aquí SÍ eliminamos el registro permanentemente.
        // Gracias al nullOnDelete() de la migración, la propiedad no sufrirá daños, solo perderá las coordenadas.
        $ubicacion->delete();

        return response()->json(['message' => 'Ubicación eliminada permanentemente del mapa'], 200);
    }
}