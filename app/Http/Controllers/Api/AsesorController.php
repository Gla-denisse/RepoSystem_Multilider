<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\User;
use App\Models\Rol;
use App\Models\RolPermiso;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AsesorController extends Controller
{
    // 1. Listar con paginación, búsqueda y relación de usuario
    public function index(Request $request) {
        $query = Asesor::with('usuario');

        // Búsqueda por nombre o correo
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('correo', 'LIKE', "%{$search}%");
        }

        $perPage = $request->input('per_page', 10);
        $asesores = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($asesores, 200);
    }

    // 2. Crear Asesor + Usuario Integrado
    public function store(Request $request) {
        $validatedData = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'telefono'        => 'nullable|string|max:50',
            'correo'          => 'required|email|max:255|unique:users,correo', // El correo debe ser único para el usuario
            'password' => [
                'required',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols()
            ],
            'direccion'       => 'nullable|string|max:255',
            'foto'            => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado'          => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear el Usuario
            $usuario = User::create([
                'nombre'   => $validatedData['nombre_completo'],
                'correo'   => $validatedData['correo'],
                'password' => bcrypt($validatedData['password']),
                'estado'   => $validatedData['estado'] ?? true
            ]);

            // 2. 🌟 LA MAGIA: Asignar Permisos del Rol "Asesor de Ventas" automáticamente
            $rolAsesor = Rol::where('nombre', 'Asesor de Ventas')->first();
            
            if ($rolAsesor) {
                // Obtenemos todos los IDs de la tabla intermedia rol_permiso para este rol
                $rolPermisoIds = RolPermiso::where('rol_id', $rolAsesor->id)->pluck('id');
                $usuario->asignaciones()->sync($rolPermisoIds);
            }

            // Manejo de la foto
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('asesores', 'public');
                $fotoPath = Storage::url($path);
            }

            // 3. Crear el Asesor vinculado
            $asesor = Asesor::create([
                'user_id'      => $usuario->id,
                'nombre_completo' => $validatedData['nombre_completo'],
                'telefono'        => $validatedData['telefono'],
                'correo'          => $validatedData['correo'],
                'direccion'       => $validatedData['direccion'],
                'foto'            => $fotoPath,
                'estado'          => $validatedData['estado'] ?? true
            ]);

            DB::commit(); // Si todo salió bien, guardamos ambos en la BD

            return response()->json([
                'message' => 'Asesor y cuenta de usuario creados con éxito', 
                'data' => $asesor->load('usuario')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Si falla algo, deshacemos la creación del usuario
            return response()->json(['message' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // 4. Actualizar Asesor + Usuario
    public function update(Request $request, $id) {
        $asesor = Asesor::with('usuario')->findOrFail($id);

        $validatedData = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'telefono'        => 'nullable|string|max:50',
            // Validamos el correo ignorando el del usuario actual
            'correo'          => 'required|email|max:255|unique:users,correo,' . $asesor->user_id,
            'password' => [
                'nullable', // <- Puede venir vacío
                Password::min(8)->mixedCase()->letters()->numbers()->symbols()
            ],
            'direccion'       => 'nullable|string|max:255',
            'foto'            => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado'          => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            // 1. Actualizamos el Usuario
            $usuarioData = [
                'nombre' => $validatedData['nombre_completo'],
                'correo' => $validatedData['correo'],
                'estado' => $validatedData['estado'] ?? true
            ];
            
            // Solo actualizamos la contraseña si escribieron una nueva
            if (!empty($validatedData['password'])) {
                $usuarioData['password'] = bcrypt($validatedData['password']);
            }
            
            $asesor->usuario->update($usuarioData);

            // Manejo de la foto
            $fotoPath = $asesor->foto;
            if ($request->hasFile('foto')) {
                // Eliminar foto anterior si existe
                if ($asesor->foto) {
                    $oldPath = str_replace('/storage/', '', $asesor->foto);
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file('foto')->store('asesores', 'public');
                $fotoPath = Storage::url($path);
            }

            // 2. Actualizamos el Asesor
            $asesor->update([
                'nombre_completo' => $validatedData['nombre_completo'],
                'telefono'        => $validatedData['telefono'],
                'correo'          => $validatedData['correo'],
                'direccion'       => $validatedData['direccion'],
                'foto'            => $fotoPath,
                'estado'          => $validatedData['estado'] ?? true
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Datos actualizados correctamente', 
                'data' => $asesor->fresh('usuario')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver uno específico
    public function show($id) {
        $asesor = Asesor::with('usuario')->findOrFail($id);
        return response()->json($asesor, 200);
    }


    // 5. Activar / Desactivar (Toggle Seguro)
    public function destroy($id) {
        $asesor = Asesor::findOrFail($id);
        
        $asesor->estado = !$asesor->estado;
        $asesor->save();

        $mensaje = $asesor->estado ? 'Asesor habilitado en el sistema' : 'Asesor inhabilitado del sistema';

        return response()->json(['message' => $mensaje, 'estado' => $asesor->estado], 200);
    }
}