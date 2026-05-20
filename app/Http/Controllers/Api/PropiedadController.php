<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropiedadController extends Controller
{
    public function index(Request $request)
    {
        $query = Propiedad::with([
            'propietarios',
            'sectorUrbano.distrito.ciudad',
            'ubicacion',
            'caracteristicas',
            'imagenes',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhereHas('propietarios', fn($s) =>
                      $s->where('nombre_completo', 'LIKE', "%{$search}%")
                        ->orWhere('nombre_empresa', 'LIKE', "%{$search}%")
                  );
            });
        }

        if ($request->filled('ciudad_id')) {
            $query->whereHas('sectorUrbano.distrito', fn($q) => $q->where('ciudad_id', $request->ciudad_id));
        }

        if ($request->filled('distrito_id')) {
            $query->whereHas('sectorUrbano', fn($q) => $q->where('distrito_id', $request->distrito_id));
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'propietario_ids'         => 'required|array|min:1',
            'propietario_ids.*'       => 'integer|exists:propietarios,id',
            'sector_urbano_id'        => 'required|exists:sectores_urbanos,id',
            'ubicacion_id'            => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id',
            'tipo'                    => 'required|string|max:100',
            'precio_venta'            => 'required|numeric|min:0',
            'moneda'                  => 'required|in:USD,BOB',
            'superficie_m2'           => 'required|numeric|min:0',
            'superficie_construida_m2'=> 'nullable|numeric|min:0',
            'frente_mts'              => 'nullable|numeric|min:0',
            'fondo_mts'               => 'nullable|numeric|min:0',
            'habitaciones'            => 'nullable|integer|min:0',
            'banos'                   => 'nullable|integer|min:0',
            'es_esquina'              => 'nullable|boolean',
            'direccion'               => 'nullable|string|max:255',
            'nro_lote'                => 'nullable|string|max:50',
            'colinda_norte'           => 'nullable|string|max:255',
            'colinda_sur'             => 'nullable|string|max:255',
            'colinda_este'            => 'nullable|string|max:255',
            'colinda_oeste'           => 'nullable|string|max:255',
            'estado'                  => 'nullable|string|max:50',
            'activo'                  => 'nullable|boolean',
            'caracteristicas'         => 'nullable|array',
            'caracteristicas.*'       => 'exists:caracteristicas,id',
        ]);

        try {
            return DB::transaction(function () use ($validatedData, $request) {
                $propiedad = Propiedad::create($validatedData);

                $propiedad->codigo = Propiedad::siguienteCodigo();
                $propiedad->save();

                $propiedad->propietarios()->sync($request->propietario_ids);

                if ($request->has('caracteristicas')) {
                    $propiedad->caracteristicas()->sync($request->caracteristicas);
                }

                return response()->json([
                    'message' => 'Propiedad registrada correctamente',
                    'data'    => $propiedad->load(['propietarios', 'sectorUrbano.distrito.ciudad', 'caracteristicas', 'imagenes']),
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $propiedad = Propiedad::with([
            'propietarios',
            'sectorUrbano.distrito.ciudad',
            'ubicacion',
            'caracteristicas',
            'imagenes',
        ])->findOrFail($id);

        return response()->json($propiedad, 200);
    }

    public function update(Request $request, $id)
    {
        $propiedad = Propiedad::findOrFail($id);

        $validatedData = $request->validate([
            'propietario_ids'         => 'required|array|min:1',
            'propietario_ids.*'       => 'integer|exists:propietarios,id',
            'sector_urbano_id'        => 'required|exists:sectores_urbanos,id',
            'ubicacion_id'            => 'nullable|exists:ubicaciones,id|unique:propiedades,ubicacion_id,' . $propiedad->id,
            'tipo'                    => 'required|string|max:100',
            'codigo'                  => 'nullable|string|max:100|unique:propiedades,codigo,' . $propiedad->id,
            'precio_venta'            => 'required|numeric|min:0',
            'moneda'                  => 'required|in:USD,BOB',
            'superficie_m2'           => 'required|numeric|min:0',
            'superficie_construida_m2'=> 'nullable|numeric|min:0',
            'frente_mts'              => 'nullable|numeric|min:0',
            'fondo_mts'               => 'nullable|numeric|min:0',
            'habitaciones'            => 'nullable|integer|min:0',
            'banos'                   => 'nullable|integer|min:0',
            'es_esquina'              => 'nullable|boolean',
            'direccion'               => 'nullable|string|max:255',
            'nro_lote'                => 'nullable|string|max:50',
            'colinda_norte'           => 'nullable|string|max:255',
            'colinda_sur'             => 'nullable|string|max:255',
            'colinda_este'            => 'nullable|string|max:255',
            'colinda_oeste'           => 'nullable|string|max:255',
            'estado'                  => 'nullable|string|max:50',
            'activo'                  => 'nullable|boolean',
            'caracteristicas'         => 'nullable|array',
            'caracteristicas.*'       => 'exists:caracteristicas,id',
        ]);

        try {
            return DB::transaction(function () use ($propiedad, $validatedData, $request) {
                $propiedad->update($validatedData);

                $propiedad->propietarios()->sync($request->propietario_ids);

                if ($request->has('caracteristicas')) {
                    $propiedad->caracteristicas()->sync($request->caracteristicas);
                }

                return response()->json([
                    'message' => 'Propiedad actualizada',
                    'data'    => $propiedad->load(['propietarios', 'sectorUrbano.distrito.ciudad', 'caracteristicas', 'imagenes']),
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $propiedad = Propiedad::findOrFail($id);
        $propiedad->activo = !$propiedad->activo;
        $propiedad->save();

        $mensaje = $propiedad->activo ? 'Propiedad habilitada' : 'Propiedad inhabilitada';
        return response()->json(['message' => $mensaje, 'activo' => $propiedad->activo], 200);
    }

    public function syncCaracteristicas(Request $request, $id)
    {
        $propiedad = Propiedad::findOrFail($id);

        $validatedData = $request->validate([
            'caracteristica_ids'   => 'required|array',
            'caracteristica_ids.*' => 'exists:caracteristicas,id',
        ]);

        try {
            $propiedad->caracteristicas()->sync($validatedData['caracteristica_ids']);
            return response()->json([
                'message' => 'Características sincronizadas correctamente',
                'data'    => $propiedad->load('caracteristicas'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }
}
