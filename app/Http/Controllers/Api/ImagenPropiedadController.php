<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImagenPropiedad;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ImagenPropiedadController extends Controller
{
    // Subir imágenes para una propiedad
    public function store(Request $request, $propiedadId)
    {
        $request->validate([
            'imagenes' => 'required|array',
            'imagenes.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $propiedad = Propiedad::findOrFail($propiedadId);
        $imagenesGuardadas = [];

        foreach ($request->file('imagenes') as $file) {
            $path = $file->store('propiedades/' . $propiedad->id, 'public');
            
            $imagen = ImagenPropiedad::create([
                'propiedad_id' => $propiedad->id,
                'url' => Storage::url($path),
                'es_principal' => $propiedad->imagenes()->count() == 0 // La primera es la principal por defecto
            ]);

            $imagenesGuardadas[] = $imagen;
        }

        return response()->json([
            'message' => 'Imágenes subidas correctamente',
            'imagenes' => $imagenesGuardadas
        ], 201);
    }

    // Eliminar una imagen
    public function destroy($id)
    {
        $imagen = ImagenPropiedad::findOrFail($id);
        
        // Eliminar el archivo del storage
        $path = str_replace('/storage/', '', $imagen->url);
        Storage::disk('public')->delete($path);
        
        $imagen->delete();

        return response()->json(['message' => 'Imagen eliminada correctamente'], 200);
    }

    // Establecer una imagen como principal
    public function setPrincipal($id)
    {
        $imagen = ImagenPropiedad::findOrFail($id);
        
        DB::transaction(function () use ($imagen) {
            // Quitar principal a todas las demás imágenes de esta propiedad
            ImagenPropiedad::where('propiedad_id', $imagen->propiedad_id)
                ->update(['es_principal' => false]);
            
            // Establecer la nueva principal
            $imagen->update(['es_principal' => true]);
        });

        return response()->json(['message' => 'Imagen principal actualizada'], 200);
    }
}