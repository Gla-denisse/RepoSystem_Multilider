<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropiedadController extends Controller
{
    // 1. Listar todas con sus relaciones (CON BÚSQUEDA Y PAGINACIÓN)
    public function index(Request $request) {
        // Cargamos zona.ciudad para tener la ubicación completa y caracteristicas para los tags
        $query = Propiedad::with(['propietario', 'zona.ciudad', 'ubicacion', 'caracteristicas', 'imagenes']);

        // Búsqueda por Código de la propiedad o Nombre del Dueño
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhereHas('propietario', function($q) use ($search) {
                      $q->where('nombre_completo', 'LIKE', "%{$search}%");
                  });
        }

        // Filtro por Ciudad (Opcional, útil para el nuevo diseño)
        if ($request->has('ciudad_id')) {
            $query->whereHas('zona', function($q) use ($request) {
                $q->where('ciudad_id', $request->ciudad_id);
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
            'zona_id'        => 'required|exists:zonas,id',
            'ubicacion_id'   => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id',
            'tipo'           => 'required|string|max:100',
            'codigo'         => 'required|string|max:100|unique:propiedades,codigo',
            'precio_venta'   => 'required|numeric|min:0',
            'moneda'         => 'required|in:USD,BOB',
            'superficie_m2'  => 'required|numeric|min:0',
            'superficie_construida_m2' => 'nullable|numeric|min:0',
            'frente_mts'     => 'nullable|numeric|min:0',
            'fondo_mts'      => 'nullable|numeric|min:0',
            'habitaciones'   => 'nullable|integer|min:0',
            'banos'          => 'nullable|integer|min:0',
            'es_esquina'     => 'nullable|boolean',
            'direccion'      => 'nullable|string|max:255',
            'nro_lote'       => 'nullable|string|max:50',
            'colinda_norte'  => 'nullable|string|max:255',
            'colinda_sur'    => 'nullable|string|max:255',
            'colinda_este'   => 'nullable|string|max:255',
            'colinda_oeste'  => 'nullable|string|max:255',
            'estado'         => 'nullable|string|max:50',
            'activo'         => 'nullable|boolean',
            'caracteristicas'=> 'nullable|array', // Array de IDs de características
            'caracteristicas.*' => 'exists:caracteristicas,id'
        ]);

        try {
            return DB::transaction(function () use ($validatedData, $request) {
                $propiedad = Propiedad::create($validatedData);

                // Sincronizar las características (Muchos a Muchos)
                if ($request->has('caracteristicas')) {
                    $propiedad->caracteristicas()->sync($request->caracteristicas);
                }

                return response()->json([
                    'message' => 'Propiedad registrada correctamente', 
                    'data' => $propiedad->load(['propietario', 'zona.ciudad', 'caracteristicas', 'imagenes'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver detalle completo
    public function show($id) {
        $propiedad = Propiedad::with(['propietario', 'zona.ciudad', 'ubicacion', 'caracteristicas', 'imagenes'])->findOrFail($id);
        return response()->json($propiedad, 200);
    }

    // 4. Actualizar
    public function update(Request $request, $id) {
        $propiedad = Propiedad::findOrFail($id);

        $validatedData = $request->validate([
            'propietario_id' => 'required|exists:propietarios,id',
            'zona_id'        => 'required|exists:zonas,id',
            'ubicacion_id'   => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id,' . $propiedad->id,
            'tipo'           => 'required|string|max:100',
            'codigo'         => 'required|string|max:100|unique:propiedades,codigo,' . $propiedad->id,
            'precio_venta'   => 'required|numeric|min:0',
            'moneda'         => 'required|in:USD,BOB',
            'superficie_m2'  => 'required|numeric|min:0',
            'superficie_construida_m2' => 'nullable|numeric|min:0',
            'frente_mts'     => 'nullable|numeric|min:0',
            'fondo_mts'      => 'nullable|numeric|min:0',
            'habitaciones'   => 'nullable|integer|min:0',
            'banos'          => 'nullable|integer|min:0',
            'es_esquina'     => 'nullable|boolean',
            'direccion'      => 'nullable|string|max:255',
            'nro_lote'       => 'nullable|string|max:50',
            'colinda_norte'  => 'nullable|string|max:255',
            'colinda_sur'    => 'nullable|string|max:255',
            'colinda_este'   => 'nullable|string|max:255',
            'colinda_oeste'  => 'nullable|string|max:255',
            'estado'         => 'nullable|string|max:50',
            'activo'         => 'nullable|boolean',
            'caracteristicas'=> 'nullable|array',
            'caracteristicas.*' => 'exists:caracteristicas,id'
        ]);

        try {
            return DB::transaction(function () use ($propiedad, $validatedData, $request) {
                $propiedad->update($validatedData);

                // Sincronizar características (reemplaza las anteriores con las nuevas)
                if ($request->has('caracteristicas')) {
                    $propiedad->caracteristicas()->sync($request->caracteristicas);
                }

                return response()->json([
                    'message' => 'Propiedad actualizada', 
                    'data' => $propiedad->load(['propietario', 'zona.ciudad', 'caracteristicas', 'imagenes'])
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // 5. Toggle Activo
    public function destroy($id) {
        $propiedad = Propiedad::findOrFail($id);
        
        $propiedad->activo = !$propiedad->activo;
        $propiedad->save();

        $mensaje = $propiedad->activo ? 'Propiedad habilitada' : 'Propiedad inhabilitada';

        return response()->json(['message' => $mensaje, 'activo' => $propiedad->activo], 200);
    }

    // 6. Sincronizar Características (Endpoint Específico)
    public function syncCaracteristicas(Request $request, $id) {
        $propiedad = Propiedad::findOrFail($id);

        $validatedData = $request->validate([
            'caracteristica_ids'   => 'required|array',
            'caracteristica_ids.*' => 'exists:caracteristicas,id'
        ]);

        try {
            // sync() elimina las relaciones previas y deja solo las del array
            $propiedad->caracteristicas()->sync($validatedData['caracteristica_ids']);

            return response()->json([
                'message' => 'Características sincronizadas correctamente',
                'data'    => $propiedad->load('caracteristicas')
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }
}