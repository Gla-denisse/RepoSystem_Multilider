<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RolPermisoUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Obtener todos los usuarios
    public function index() {
        $usuarios = User::all();
        return response()->json($usuarios, 200);
    }

    // Crear un nuevo usuario
    public function store(Request $request) {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $usuario = User::create([
            'nombre' => $request->nombre,
            'correo' => $request->correo,
            'password' => Hash::make($request->password),
            'estado' => $request->estado ?? true
        ]);

        return response()->json(['message' => 'Usuario creado con éxito', 'data' => $usuario], 201);
    }

    // Obtener un usuario específico
    public function show($id) {
        $usuario = User::findOrFail($id);
        return response()->json($usuario, 200);
    }

    // Actualizar un usuario
    public function update(Request $request, $id) {
        $usuario = User::findOrFail($id);

        $request->validate([
            'nombre' => 'string|max:255',
            'correo' => 'email|unique:users,correo,' . $usuario->id,
        ]);

        if($request->has('password')) {
            $request->merge(['password' => Hash::make($request->password)]);
        }

        $usuario->update($request->all());

        return response()->json(['message' => 'Usuario actualizado', 'data' => $usuario], 200);
    }

    // Eliminar o desactivar un usuario
    public function destroy($id) {
        $usuario = User::findOrFail($id);
        $usuario->estado = false; // Soft delete lógico
        $usuario->save();

        return response()->json(['message' => 'Usuario desactivado'], 200);
    }

    public function getAsignaciones($id) {
        $usuario = \App\Models\User::with('rolesPermisos')->findOrFail($id);
        // Devolvemos solo el array de IDs de la tabla intermedia rol_permiso
        return response()->json($usuario->rolesPermisos->pluck('rol_permiso_id'));
    }

    // Sincronizar las combinaciones Rol-Permiso para el usuario
    public function syncAsignaciones(Request $request, $id) {
        $request->validate([
            'rol_permiso_ids' => 'array'
        ]);

        $usuario = \App\Models\User::findOrFail($id);

        // Borramos las asignaciones anteriores
        \App\Models\RolPermisoUsuario::where('user_id', $id)->delete();

        // Creamos las nuevas
        $nuevasAsignaciones = [];
        foreach ($request->rol_permiso_ids as $rp_id) {
            $nuevasAsignaciones[] = [
                'user_id' => $id,
                'rol_permiso_id' => $rp_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \App\Models\RolPermisoUsuario::insert($nuevasAsignaciones);

        return response()->json(['message' => 'Accesos sincronizados correctamente']);
    }
}
