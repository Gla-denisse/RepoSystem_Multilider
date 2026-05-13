<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $query = Contrato::with([
            'notaVenta.cliente',
            'notaVenta.propiedad.sectorUrbano.distrito.ciudad',
        ]);

        if ($request->filled('estado') && $request->estado !== 'TODOS') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha_emision', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_contrato', 'like', "%{$buscar}%")
                  ->orWhereHas('notaVenta.cliente', fn ($sq) =>
                      $sq->where('nombre_completo', 'like', "%{$buscar}%")
                  );
            });
        }

        $perPage = $request->input('per_page', 15);
        $contratos = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($contratos, 200);
    }

    // Sube PDF y/o cambia estado (Firmado/Pendiente) en una sola operación
    public function gestionar(Request $request, $id)
    {
        $request->validate([
            'archivo'     => 'nullable|file|mimes:pdf|max:10240',
            'firmado'     => 'nullable',
            'fecha_firma' => 'nullable|date',
        ]);

        $contrato = Contrato::findOrFail($id);

        if ($contrato->estado === 'Anulado') {
            return response()->json(['message' => 'No se puede modificar un contrato anulado.'], 400);
        }

        if ($request->hasFile('archivo')) {
            if ($contrato->url_doc) {
                $oldPath = ltrim(str_replace('/storage', '', $contrato->url_doc), '/');
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('archivo')->store("contratos/{$contrato->id}", 'public');
            $contrato->url_doc = Storage::url($path);
        }

        $firmado = filter_var($request->input('firmado', false), FILTER_VALIDATE_BOOLEAN);

        if ($firmado) {
            $contrato->estado = 'Firmado';
            $contrato->fecha_firma = $request->fecha_firma ?? now()->toDateString();
        } elseif ($contrato->estado === 'Firmado') {
            $contrato->estado = 'Pendiente';
            $contrato->fecha_firma = null;
        }

        $contrato->save();

        return response()->json([
            'message' => 'Contrato actualizado correctamente.',
            'data'    => $contrato->load('notaVenta.cliente', 'notaVenta.propiedad'),
        ], 200);
    }

    public function anular($id)
    {
        $contrato = Contrato::findOrFail($id);

        if ($contrato->estado === 'Anulado') {
            return response()->json(['message' => 'El contrato ya está anulado.'], 400);
        }

        $contrato->update(['estado' => 'Anulado']);

        return response()->json(['message' => 'Contrato anulado correctamente.'], 200);
    }

    public function descargar($id)
    {
        $contrato = Contrato::findOrFail($id);

        if (!$contrato->url_doc) {
            return response()->json(['message' => 'Este contrato no tiene documento adjunto.'], 404);
        }

        $path = ltrim(str_replace('/storage', '', $contrato->url_doc), '/');

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'El archivo no se encontró en el servidor.'], 404);
        }

        return Storage::disk('public')->download($path, $contrato->codigo_contrato . '.pdf');
    }
}
