<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\Http\Request;

class PropiedadController extends Controller
{
    // 1. Listar todas con sus relaciones
    // 1. Listar todas con sus relaciones (CON BÚSQUEDA Y PAGINACIÓN)
    public function index(Request $request) {
        $query = Propiedad::with(['propietario', 'manzano', 'ubicacion']);

        // Búsqueda por Código de la propiedad o Nombre del Dueño
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhereHas('propietario', function($q) use ($search) {
                      $q->where('nombre_completo', 'LIKE', "%{$search}%");
                  });
        }

        $perPage = $request->input('per_page', 10);
        $propiedades = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($propiedades, 200);
    }

    // 2. Crear Propiedad
    public function store(Request $request) {
        $validatedData = $request->validate([
            'propietario_id' => 'required|exists:propietarios,id',
            'manzano_id'     => 'required|exists:manzanos,id',
            'ubicacion_id'   => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id',
            'tipo'           => 'required|string|max:100',
            'codigo'         => 'required|string|max:100|unique:propiedades,codigo',
            'precio_venta'   => 'required|numeric|min:0',
            'direccion'      => 'nullable|string|max:255',
            'nro_lote'       => 'nullable|string|max:50',
            'superficie_m2'  => 'required|numeric|min:0',
            'colinda_norte'  => 'nullable|string|max:255',
            'colinda_sur'    => 'nullable|string|max:255',
            'colinda_este'   => 'nullable|string|max:255',
            'colinda_oeste'  => 'nullable|string|max:255',
            'estado'         => 'nullable|string|max:50',
            'activo'         => 'nullable|boolean'
        ]);

        $propiedad = Propiedad::create($validatedData);
        
        return response()->json([
            'message' => 'Propiedad registrada correctamente', 
            'data' => $propiedad->load(['propietario', 'manzano'])
        ], 201);
    }

    // 3. Ver una sola
    public function show($id) {
        $propiedad = Propiedad::with(['propietario', 'manzano', 'ubicacion'])->findOrFail($id);
        return response()->json($propiedad, 200);
    }

    // 4. Actualizar
    public function update(Request $request, $id) {
        $propiedad = Propiedad::findOrFail($id);

        $validatedData = $request->validate([
            'propietario_id' => 'required|exists:propietarios,id',
            'manzano_id'     => 'required|exists:manzanos,id',
            'ubicacion_id'   => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id,' . $propiedad->id,
            'tipo'           => 'required|string|max:100',
            'codigo'         => 'required|string|max:100|unique:propiedades,codigo,' . $propiedad->id,
            'precio_venta'   => 'required|numeric|min:0',
            'direccion'      => 'nullable|string|max:255',
            'nro_lote'       => 'nullable|string|max:50',
            'superficie_m2'  => 'required|numeric|min:0',
            'estado'         => 'nullable|string|max:50',
            'activo'         => 'nullable|boolean'
        ]);

        $propiedad->update($validatedData);
        
        return response()->json([
            'message' => 'Propiedad actualizada', 
            'data' => $propiedad->load(['propietario', 'manzano'])
        ], 200);
    }

    // 5. El "Toggle" (Activar/Desactivar)
    public function destroy($id) {
        $propiedad = Propiedad::findOrFail($id);
        
        $propiedad->activo = !$propiedad->activo;
        $propiedad->save();

        $mensaje = $propiedad->activo ? 'Propiedad habilitada en el catálogo' : 'Propiedad inhabilitada del catálogo';

        return response()->json(['message' => $mensaje, 'activo' => $propiedad->activo], 200);
    }
}