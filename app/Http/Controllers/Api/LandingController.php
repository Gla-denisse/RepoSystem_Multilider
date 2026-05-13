<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MiEmpresa;
use App\Models\Propiedad;
use App\Models\Asesor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingController extends Controller
{
    /**
     * Obtiene toda la información necesaria para la Landing Page en una sola petición.
     */
    public function getLandingData()
    {
        $empresa = MiEmpresa::first();

        $propiedadesDestacadas = Propiedad::with(['sectorUrbano.distrito.ciudad', 'imagenes', 'caracteristicas'])
            ->where('activo', true)
            ->where('es_destacado', true)
            ->limit(6)
            ->get();

        $ultimasPropiedades = Propiedad::with(['sectorUrbano.distrito.ciudad', 'imagenes', 'caracteristicas'])
            ->where('activo', true)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        $asesores = Asesor::where('estado', true)->get();

        return response()->json([
            'empresa' => $empresa,
            'propiedades_destacadas' => $propiedadesDestacadas,
            'ultimas_propiedades' => $ultimasPropiedades,
            'asesores' => $asesores
        ], 200);
    }

    /**
     * Obtiene el listado de propiedades para la landing con filtros y paginación.
     */
    public function getPropiedades(Request $request)
    {
        $query = Propiedad::with(['sectorUrbano.distrito.ciudad', 'imagenes', 'caracteristicas'])
            ->where('activo', true)
            ->where('estado', '!=', 'Vendido');

        if ($request->filled('tipo') && $request->tipo !== 'Todos') {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('ciudad_id')) {
            $query->whereHas('sectorUrbano.distrito', function($q) use ($request) {
                $q->where('ciudad_id', $request->ciudad_id);
            });
        }

        if ($request->filled('distrito_id')) {
            $query->whereHas('sectorUrbano', function($q) use ($request) {
                $q->where('distrito_id', $request->distrito_id);
            });
        }

        if ($request->filled('sector_urbano_id')) {
            $query->where('sector_urbano_id', $request->sector_urbano_id);
        }

        if ($request->filled('precio_min')) {
            $query->where('precio_venta', '>=', $request->precio_min);
        }
        if ($request->filled('precio_max')) {
            $query->where('precio_venta', '<=', $request->precio_max);
        }

        if ($request->filled('habitaciones')) {
            $query->where('habitaciones', '>=', $request->habitaciones);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhereHas('sectorUrbano', function($z) use ($search) {
                      $z->where('nombre', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = $request->input('per_page', 12);
        $propiedades = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($propiedades, 200);
    }

    /**
     * Obtiene el detalle completo de una propiedad pública.
     */
    public function getPropiedad($id)
    {
        $propiedad = Propiedad::with(['sectorUrbano.distrito.ciudad', 'imagenes', 'caracteristicas', 'ubicacion'])
            ->where('activo', true)
            ->findOrFail($id);

        return response()->json($propiedad, 200);
    }

    /**
     * Obtiene propiedades similares (mismo tipo) a una propiedad dada.
     */
    public function getSimilares($id)
    {
        $propiedad = Propiedad::findOrFail($id);

        $similares = Propiedad::with(['sectorUrbano.distrito.ciudad', 'imagenes', 'caracteristicas'])
            ->where('activo', true)
            ->where('tipo', $propiedad->tipo)
            ->where('id', '!=', $id)
            ->where('estado', '!=', 'Vendido')
            ->orderBy('es_destacado', 'desc')
            ->limit(4)
            ->get();

        return response()->json($similares, 200);
    }

    /**
     * Obtiene listado de ciudades activas para los filtros de la landing.
     */
    public function getCiudades()
    {
        $ciudades = \App\Models\Ciudad::where('estado', true)->get();
        return response()->json($ciudades, 200);
    }

    /**
     * Obtiene listado de distritos activos (opcionalmente filtrado por ciudad).
     */
    public function getDistritos(Request $request)
    {
        $query = \App\Models\Distrito::where('estado', true)->with('ciudad');

        if ($request->filled('ciudad_id')) {
            $query->where('ciudad_id', $request->ciudad_id);
        }

        return response()->json($query->orderBy('nombre')->get(), 200);
    }

    /**
     * Obtiene sectores urbanos activos de un distrito para filtros en cascada.
     */
    public function getSectoresUrbanos($distritoId)
    {
        $sectores = \App\Models\SectorUrbano::where('distrito_id', $distritoId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($sectores, 200);
    }

    /**
     * Actualizar la información de la empresa (Requiere Auth)
     */
    public function updateEmpresa(Request $request)
    {
        $empresa = MiEmpresa::first();
        if (!$empresa) {
            $empresa = new MiEmpresa();
        }

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'eslogan' => 'nullable|string|max:255',
            'descripcion_nosotros' => 'nullable|string',
            'mision' => 'nullable|string',
            'vision' => 'nullable|string',
            'valores' => 'nullable|string',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'facebook' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'tiktok' => 'nullable|url|max:255',
            'youtube' => 'nullable|url|max:255',
            'mapa_iframe' => 'nullable|string',
            'color_primario' => 'nullable|string|max:20',
            'color_secundario' => 'nullable|string|max:20',
            'hero_title_1' => 'nullable|string|max:255',
            'hero_subtitle_1' => 'nullable|string|max:255',
            'hero_title_2' => 'nullable|string|max:255',
            'hero_subtitle_2' => 'nullable|string|max:255',
            'hero_title_3' => 'nullable|string|max:255',
            'hero_subtitle_3' => 'nullable|string|max:255',
        ]);

        // Procesar Archivos de Imagen
        $imageFields = ['logo', 'logo_login', 'logo_sidebar', 'logo_sidebar_compact', 'hero_image_1', 'hero_image_2', 'hero_image_3'];

        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                // Eliminar anterior si existe
                if ($empresa->$field) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $empresa->$field));
                }
                $path = $request->file($field)->store('empresa', 'public');
                $validatedData[$field] = Storage::url($path);
            }
        }

        $empresa->fill($validatedData);
        $empresa->save();

        return response()->json([
            'message' => 'Información de la empresa actualizada correctamente',
            'data' => $empresa
        ], 200);
    }
}