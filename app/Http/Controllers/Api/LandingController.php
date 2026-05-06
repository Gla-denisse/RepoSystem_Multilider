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