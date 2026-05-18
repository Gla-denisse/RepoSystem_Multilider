<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['rolesPermisos.rolPermiso.rol', 'rolesPermisos.rolPermiso.permiso']);
        return response()->json($user, 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:users,correo,' . $user->id,
        ]);

        $user->update([
            'nombre' => $request->nombre,
            'correo' => $request->correo,
        ]);

        $userActualizado = $user->fresh()->load(['rolesPermisos.rolPermiso.rol', 'rolesPermisos.rolPermiso.permiso']);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'data' => $userActualizado
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password_actual'            => 'required',
            'password_nuevo'             => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
            ],
            'password_nuevo_confirmation' => 'required',
        ]);

        if (!Hash::check($request->password_actual, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.',
                'errors'  => ['password_actual' => ['La contraseña actual no es correcta.']]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password_nuevo)
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ], 200);
    }
}
