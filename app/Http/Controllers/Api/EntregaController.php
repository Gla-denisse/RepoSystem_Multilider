<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntregaController extends Controller
{
    public function index(Request $request)
    {
        $query = Entrega::with([
            'contrato.notaVenta.cliente',
            'contrato.notaVenta.propiedad.sectorUrbano',
        ]);

        if ($request->filled('estado') && $request->estado !== 'TODOS') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha_programada', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->whereHas('contrato', function ($q) use ($buscar) {
                $q->where('codigo_contrato', 'like', "%{$buscar}%")
                  ->orWhereHas('notaVenta.cliente', fn ($sq) =>
                      $sq->where('nombre_completo', 'like', "%{$buscar}%")
                  );
            });
        }

        $perPage  = $request->input('per_page', 15);
        $entregas = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($entregas, 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'estado'             => 'required|in:Pendiente,Entregado,Diferido',
            'fecha_entrega'      => 'nullable|date',
            'fecha_programada'   => 'nullable|date',
            'condicion_inmueble' => 'nullable|string|max:100',
            'items_entregados'   => 'nullable|string',
            'observaciones'      => 'nullable|string',
            'entregado_por'      => 'nullable|string|max:150',
            'recibido_por'       => 'nullable|string|max:150',
        ]);

        $entrega = Entrega::findOrFail($id);

        $entrega->update($request->only([
            'estado', 'fecha_entrega', 'fecha_programada',
            'condicion_inmueble', 'items_entregados',
            'observaciones', 'entregado_por', 'recibido_por',
        ]));

        return response()->json([
            'message' => 'Entrega actualizada correctamente.',
            'data'    => $entrega->load('contrato.notaVenta.cliente', 'contrato.notaVenta.propiedad.sectorUrbano'),
        ], 200);
    }

    public function subirActa(Request $request, $id)
    {
        $request->validate([
            'acta' => 'required|file|mimes:pdf|max:10240',
        ]);

        $entrega = Entrega::findOrFail($id);

        if ($entrega->url_acta) {
            $oldPath = ltrim(str_replace('/storage', '', $entrega->url_acta), '/');
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('acta')->store("entregas/{$entrega->id}", 'public');
        $entrega->url_acta = Storage::url($path);
        $entrega->save();

        return response()->json([
            'message' => 'Acta de entrega subida correctamente.',
            'data'    => $entrega,
        ], 200);
    }

    public function descargarActa($id)
    {
        $entrega = Entrega::findOrFail($id);

        if (!$entrega->url_acta) {
            return response()->json(['message' => 'Esta entrega no tiene acta adjunta.'], 404);
        }

        $path = ltrim(str_replace('/storage', '', $entrega->url_acta), '/');

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'El archivo no se encontró en el servidor.'], 404);
        }

        $codigoContrato = $entrega->contrato->codigo_contrato ?? "entrega_{$id}";

        return Storage::disk('public')->download($path, $codigoContrato . '_acta.pdf');
    }
}
