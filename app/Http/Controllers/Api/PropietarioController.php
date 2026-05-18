<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propietario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropietarioController extends Controller
{
    public function index(Request $request)
    {
        $query = Propietario::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'LIKE', "%{$search}%")
                  ->orWhere('ci', 'LIKE', "%{$search}%")
                  ->orWhere('nombre_empresa', 'LIKE', "%{$search}%")
                  ->orWhere('nit', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $perPage = $request->input('per_page', 10);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'tipo'             => 'required|in:persona_natural,empresa',
            'ci'               => ['nullable', 'string', 'max:50', Rule::unique('propietarios', 'ci')->whereNotNull('ci')],
            'lugar_expedicion' => 'nullable|string|max:20',
            'nombre_completo'  => 'required|string|max:255',
            'nombre_empresa'   => 'nullable|string|max:255|required_if:tipo,empresa',
            'nit'              => 'nullable|string|max:50',
            'telefono'         => 'nullable|string|max:50',
            'correo'           => 'nullable|email|max:255',
            'direccion'        => 'nullable|string|max:255',
            'estado'           => 'nullable|boolean',
        ]);

        $propietario = Propietario::create($validatedData);

        return response()->json(['message' => 'Propietario registrado con éxito', 'data' => $propietario], 201);
    }

    public function show($id)
    {
        $propietario = Propietario::with('propiedades')->findOrFail($id);
        return response()->json($propietario, 200);
    }

    public function update(Request $request, $id)
    {
        $propietario = Propietario::findOrFail($id);

        $validatedData = $request->validate([
            'tipo'             => 'required|in:persona_natural,empresa',
            'ci'               => ['nullable', 'string', 'max:50', Rule::unique('propietarios', 'ci')->ignore($propietario->id)->whereNotNull('ci')],
            'lugar_expedicion' => 'nullable|string|max:20',
            'nombre_completo'  => 'required|string|max:255',
            'nombre_empresa'   => 'nullable|string|max:255|required_if:tipo,empresa',
            'nit'              => 'nullable|string|max:50',
            'telefono'         => 'nullable|string|max:50',
            'correo'           => 'nullable|email|max:255',
            'direccion'        => 'nullable|string|max:255',
            'estado'           => 'nullable|boolean',
        ]);

        $propietario->update($validatedData);

        return response()->json(['message' => 'Datos del propietario actualizados', 'data' => $propietario], 200);
    }

    public function destroy($id)
    {
        $propietario = Propietario::findOrFail($id);
        $propietario->estado = !$propietario->estado;
        $propietario->save();

        $mensaje = $propietario->estado ? 'Propietario activado correctamente' : 'Propietario desactivado correctamente';
        return response()->json(['message' => $mensaje, 'estado' => $propietario->estado], 200);
    }
}
