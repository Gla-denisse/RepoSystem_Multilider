<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolPermisoUsuario;
use Illuminate\Http\Request;

class RolPermisoUsuarioController extends Controller
{
    public function index() {
        $asignaciones = RolPermisoUsuario::with(['user', 'rolPermiso.rol', 'rolPermiso.permiso'])->get();
        return response()->json($asignaciones, 200);
    }

    public function store(Request $request) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'rol_permiso_id' => 'required|exists:rol_permiso,id'
        ]);

        $existe = RolPermisoUsuario::where('user_id', $request->user_id)
                                   ->where('rol_permiso_id', $request->rol_permiso_id)->first();
        
        if ($existe) {
            return response()->json(['message' => 'El usuario ya tiene asignado este rol y permiso'], 400);
        }

        $asignacion = RolPermisoUsuario::create($request->all());
        return response()->json(['message' => 'Rol/Permiso asignado al usuario', 'data' => $asignacion], 201);
    }

    public function show($id) {
        return response()->json(RolPermisoUsuario::with(['user', 'rolPermiso'])->findOrFail($id), 200);
    }

    public function destroy($id) {
        RolPermisoUsuario::destroy($id);
        return response()->json(['message' => 'Asignación revocada del usuario'], 200);
    }
}
