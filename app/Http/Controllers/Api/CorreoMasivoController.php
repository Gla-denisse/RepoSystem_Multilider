<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCorreoMasivoJob;
use App\Models\CampanaCorreo;
use App\Models\Ciudad;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CorreoMasivoController extends Controller
{
    // GET /api/correo-masivo/grupos
    // Devuelve los grupos disponibles con conteo de destinatarios
    public function grupos()
    {
        $grupos = [
            [
                'value'       => 'todos',
                'label'       => 'Todos los clientes',
                'descripcion' => 'Clientes activos e inactivos',
                'total'       => Cliente::whereNotNull('correo')->where('correo', '!=', '')->count(),
            ],
            [
                'value'       => 'activos',
                'label'       => 'Clientes activos',
                'descripcion' => 'Solo clientes con cuenta activa',
                'total'       => Cliente::where('estado', true)->whereNotNull('correo')->where('correo', '!=', '')->count(),
            ],
            [
                'value'       => 'inactivos',
                'label'       => 'Clientes inactivos',
                'descripcion' => 'Solo clientes con cuenta inactiva',
                'total'       => Cliente::where('estado', false)->whereNotNull('correo')->where('correo', '!=', '')->count(),
            ],
            [
                'value'       => 'ciudad',
                'label'       => 'Por ciudad',
                'descripcion' => 'Filtrar por ciudad del cliente',
                'total'       => null,
            ],
            [
                'value'       => 'seleccionados',
                'label'       => 'Selección manual',
                'descripcion' => 'Elige los clientes uno por uno',
                'total'       => null,
            ],
        ];

        $ciudades = Ciudad::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);

        return response()->json([
            'grupos'   => $grupos,
            'ciudades' => $ciudades,
        ]);
    }

    // POST /api/correo-masivo/preview-destinatarios
    // Retorna lista de destinatarios según filtros (preview antes de enviar)
    public function previewDestinatarios(Request $request)
    {
        $request->validate([
            'tipo'       => 'required|in:todos,activos,inactivos,ciudad,seleccionados',
            'ciudad_id'  => 'required_if:tipo,ciudad|nullable|integer|exists:ciudades,id',
            'cliente_ids'=> 'required_if:tipo,seleccionados|nullable|array',
        ]);

        $query = $this->buildQuery($request->tipo, $request);

        $destinatarios = $query->get(['id', 'nombre_completo', 'correo', 'estado'])
            ->map(fn($c) => [
                'id'      => $c->id,
                'nombre'  => $c->nombre_completo,
                'correo'  => $c->correo,
                'estado'  => $c->estado,
            ]);

        return response()->json([
            'total'         => $destinatarios->count(),
            'destinatarios' => $destinatarios,
        ]);
    }

    // POST /api/correo-masivo/enviar
    // Crea la campaña y despacha jobs en lotes
    public function enviar(Request $request)
    {
        $request->validate([
            'asunto'      => 'required|string|max:255',
            'mensaje'     => 'required|string|max:10000',
            'tipo'        => 'required|in:todos,activos,inactivos,ciudad,seleccionados',
            'ciudad_id'   => 'required_if:tipo,ciudad|nullable|integer|exists:ciudades,id',
            'cliente_ids' => 'required_if:tipo,seleccionados|nullable|array',
        ]);

        $query = $this->buildQuery($request->tipo, $request);
        $destinatarios = $query->get(['nombre_completo', 'correo'])
            ->filter(fn($c) => !empty($c->correo))
            ->map(fn($c) => ['nombre' => $c->nombre_completo, 'correo' => $c->correo])
            ->values()
            ->toArray();

        if (empty($destinatarios)) {
            return response()->json([
                'message' => 'No se encontraron destinatarios con correo válido para el grupo seleccionado.',
            ], 422);
        }

        $filtros = match ($request->tipo) {
            'ciudad'       => ['ciudad_id' => $request->ciudad_id],
            'seleccionados'=> ['cliente_ids' => $request->cliente_ids],
            default        => null,
        };

        $campana = CampanaCorreo::create([
            'user_id'            => Auth::id(),
            'asunto'             => $request->asunto,
            'mensaje'            => $request->mensaje,
            'tipo_destinatario'  => $request->tipo,
            'filtros'            => $filtros,
            'total_destinatarios'=> count($destinatarios),
            'estado'             => 'pendiente',
        ]);

        // Enviar en lotes de 50 para no sobrecargar la cola
        $lotes = array_chunk($destinatarios, 50);
        foreach ($lotes as $lote) {
            EnviarCorreoMasivoJob::dispatch($campana->id, $lote)->onQueue('correos');
        }

        return response()->json([
            'message'   => 'Campaña creada. Los correos se están enviando en segundo plano.',
            'campana_id'=> $campana->id,
            'total'     => count($destinatarios),
        ], 201);
    }

    // GET /api/correo-masivo/historial
    public function historial(Request $request)
    {
        $campanas = CampanaCorreo::with('usuario:id,nombre')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json($campanas);
    }

    // GET /api/correo-masivo/estado/{id}
    public function estado($id)
    {
        $campana = CampanaCorreo::findOrFail($id);

        $progreso = $campana->total_destinatarios > 0
            ? round(($campana->total_enviados + $campana->total_fallidos) / $campana->total_destinatarios * 100)
            : 0;

        return response()->json([
            'id'                  => $campana->id,
            'asunto'              => $campana->asunto,
            'estado'              => $campana->estado,
            'total_destinatarios' => $campana->total_destinatarios,
            'total_enviados'      => $campana->total_enviados,
            'total_fallidos'      => $campana->total_fallidos,
            'progreso'            => $progreso,
            'created_at'          => $campana->created_at,
        ]);
    }

    // ---- Helper privado ----

    private function buildQuery(string $tipo, Request $request)
    {
        $query = Cliente::whereNotNull('correo')->where('correo', '!=', '');

        return match ($tipo) {
            'activos'      => $query->where('estado', true),
            'inactivos'    => $query->where('estado', false),
            'ciudad'       => $query->whereHas('usuario.notasVenta.propiedad', function ($q) use ($request) {
                                    $q->where('ciudad_id', $request->ciudad_id);
                                }),
            'seleccionados'=> $query->whereIn('id', $request->cliente_ids ?? []),
            default        => $query, // todos
        };
    }
}
