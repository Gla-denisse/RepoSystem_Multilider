<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Rol;
use App\Models\RolPermiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    // 1. Listar Clientes (Paginación y Búsqueda)
    public function index(Request $request) {
        $query = Cliente::with('usuario');

        // Búsqueda por CI, Nombre o Correo
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('ci', 'LIKE', "%{$search}%")
                  ->orWhere('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('correo', 'LIKE', "%{$search}%");
        }

        $perPage = $request->input('per_page', 10);
        $clientes = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($clientes, 200);
    }

    // 2. Guardar Cliente + Usuario (Contraseña = CI)
    public function store(Request $request) {
        $validatedData = $request->validate([
            'ci'               => 'required|string|max:50|unique:clientes,ci',
            'lugar_expedicion' => 'required|string|max:10',
            'nombre_completo'  => 'required|string|max:255',
            'telefono'         => 'nullable|string|max:50',
            'correo'           => 'required|email|max:255|unique:users,correo',
            'direccion'        => 'nullable|string|max:255',
            'estado'           => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            // A) Crear el Usuario (La contraseña es el CI encriptado)
            $user = User::create([
                'nombre'   => $validatedData['nombre_completo'],
                'correo'   => $validatedData['correo'],
                'password' => bcrypt($validatedData['ci']), // CLAVE POR DEFECTO = CI
                'estado'   => $validatedData['estado'] ?? true
            ]);

            // B) Opcional: Asignar automáticamente el Rol "Cliente" (Si tienes permisos para ellos)
            $rolCliente = Rol::where('nombre', 'Cliente')->first();
            if ($rolCliente) {
                $permisosDelRol = RolPermiso::where('rol_id', $rolCliente->id)->pluck('id')->toArray();
                $user->asignaciones()->sync($permisosDelRol);
            }

            // C) Crear el Perfil del Cliente
            $cliente = Cliente::create([
                'user_id'          => $user->id,
                'ci'               => $validatedData['ci'],
                'lugar_expedicion' => $validatedData['lugar_expedicion'],
                'nombre_completo'  => $validatedData['nombre_completo'],
                'telefono'         => $validatedData['telefono'],
                'correo'           => $validatedData['correo'],
                'direccion'        => $validatedData['direccion'],
                'estado'           => $validatedData['estado'] ?? true
            ]);

            DB::commit();
            return response()->json(['message' => 'Cliente registrado correctamente. Su contraseña es su CI.', 'data' => $cliente->load('usuario')], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver Cliente
    public function show($id) {
        $cliente = Cliente::with('usuario')->findOrFail($id);
        return response()->json($cliente, 200);
    }

    // 4. Actualizar Cliente + Usuario
    public function update(Request $request, $id) {
        $cliente = Cliente::with('usuario')->findOrFail($id);

        $validatedData = $request->validate([
            'ci'               => 'required|string|max:50|unique:clientes,ci,' . $cliente->id,
            'lugar_expedicion' => 'required|string|max:10',
            'nombre_completo'  => 'required|string|max:255',
            'telefono'         => 'nullable|string|max:50',
            'correo'           => 'required|email|max:255|unique:users,correo,' . $cliente->user_id,
            'direccion'        => 'nullable|string|max:255',
            'estado'           => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            // Actualizar Usuario
            $cliente->usuario->update([
                'nombre' => $validatedData['nombre_completo'],
                'correo' => $validatedData['correo'],
                'estado' => $validatedData['estado'] ?? true
            ]);

            // Actualizar Cliente
            $cliente->update([
                'ci'               => $validatedData['ci'],
                'lugar_expedicion' => $validatedData['lugar_expedicion'],
                'nombre_completo'  => $validatedData['nombre_completo'],
                'telefono'         => $validatedData['telefono'],
                'correo'           => $validatedData['correo'],
                'direccion'        => $validatedData['direccion'],
                'estado'           => $validatedData['estado'] ?? true
            ]);

            DB::commit();
            return response()->json(['message' => 'Datos del cliente actualizados', 'data' => $cliente->fresh('usuario')], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // 5. Activar/Desactivar
    public function destroy($id) {
        $cliente = Cliente::findOrFail($id);
        $cliente->estado = !$cliente->estado;
        $cliente->save();

        // Opcional: Desactivar también al usuario asociado
        if ($cliente->usuario) {
            $cliente->usuario->estado = $cliente->estado;
            $cliente->usuario->save();
        }

        $mensaje = $cliente->estado ? 'Cliente y cuenta activados' : 'Cliente y cuenta suspendidos';
        return response()->json(['message' => $mensaje, 'estado' => $cliente->estado], 200);
    }
}