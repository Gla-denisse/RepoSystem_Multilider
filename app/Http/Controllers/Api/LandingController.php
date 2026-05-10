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
        
        $propiedadesDestacadas = Propiedad::with(['zona.ciudad', 'imagenes'])
            ->where('activo', true)
            ->where('es_destacado', true)
            ->limit(6)
            ->get();

        $ultimasPropiedades = Propiedad::with(['zona.ciudad', 'imagenes'])
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
        $query = Propiedad::with(['zona.ciudad', 'imagenes'])
            ->where('activo', true)
            ->where('estado', '!=', 'Vendido'); // Solo mostrar disponibles

        // Filtro por Tipo (Casa, Lote)
        if ($request->filled('tipo') && $request->tipo !== 'Todos') {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por Ciudad
        if ($request->filled('ciudad_id')) {
            $query->whereHas('zona', function($q) use ($request) {
                $q->where('ciudad_id', $request->ciudad_id);
            });
        }

        // Filtro por Rango de Precio
        if ($request->filled('precio_min')) {
            $query->where('precio_venta', '>=', $request->precio_min);
        }
        if ($request->filled('precio_max')) {
            $query->where('precio_venta', '<=', $request->precio_max);
        }

        // Filtro por Habitaciones (Casa)
        if ($request->filled('habitaciones')) {
            $query->where('habitaciones', '>=', $request->habitaciones);
        }

        // Búsqueda general (Código o Zona)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo', 'LIKE', "%{$search}%")
                  ->orWhereHas('zona', function($z) use ($search) {
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
        $propiedad = Propiedad::with(['zona.ciudad', 'imagenes', 'caracteristicas', 'ubicacion'])
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

        $similares = Propiedad::with(['zona.ciudad', 'imagenes', 'caracteristicas'])
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
        $imageFields = ['logo', 'hero_image_1', 'hero_image_2', 'hero_image_3'];

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