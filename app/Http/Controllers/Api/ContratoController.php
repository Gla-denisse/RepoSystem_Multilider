<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Entrega;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $query = Contrato::with([
            'notaVenta.cliente',
            'notaVenta.propiedad.sectorUrbano.distrito.ciudad',
            'entrega',
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
            'archivo'                  => 'nullable|file|mimes:pdf|max:10240',
            'firmado'                  => 'nullable',
            'fecha_firma'              => 'nullable|date',
            'fecha_programada_entrega' => 'nullable|date',
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

        $eraFirmado = $contrato->estado === 'Firmado';

        if ($firmado) {
            $contrato->estado = 'Firmado';
            $contrato->fecha_firma = $request->fecha_firma ?? now()->toDateString();
        } elseif ($eraFirmado) {
            $contrato->estado = 'Pendiente';
            $contrato->fecha_firma = null;
        }

        $contrato->save();

        // Crear o actualizar entrega cuando el contrato está firmado
        if ($firmado) {
            $fechaProgramada = $request->input('fecha_programada_entrega') ?? $contrato->fecha_firma ?? now()->toDateString();
            $entrega = Entrega::where('contrato_id', $contrato->id)->first();
            if (!$entrega) {
                Entrega::create([
                    'contrato_id'      => $contrato->id,
                    'fecha_programada' => $fechaProgramada,
                    'estado'           => 'Pendiente',
                ]);
            } elseif ($request->filled('fecha_programada_entrega')) {
                $entrega->update(['fecha_programada' => $fechaProgramada]);
            }
        }

        return response()->json([
            'message' => 'Contrato actualizado correctamente.',
            'data'    => $contrato->load('notaVenta.cliente', 'notaVenta.propiedad', 'entrega'),
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

    public function generarPdf($id)
    {
        $contrato = Contrato::with([
            'notaVenta.cliente',
            'notaVenta.propiedad.propietarios',
            'notaVenta.propiedad.sectorUrbano.distrito.ciudad',
        ])->findOrFail($id);

        $nv          = $contrato->notaVenta;
        $cliente     = $nv->cliente;
        $propiedad   = $nv->propiedad;
        $propietarios = $propiedad->propietarios ?? collect();
        $sector      = $propiedad->sectorUrbano;
        $distrito    = $sector?->distrito;
        $ciudad      = $distrito?->ciudad;

        $monto       = floatval($nv->monto_liquido ?? $nv->monto_total ?? 0);
        $montoLiteral = $this->numeroALetras($monto);

        $html = view('contratos.plantilla', compact(
            'contrato', 'nv', 'cliente', 'propiedad',
            'propietarios', 'sector', 'distrito', 'ciudad',
            'montoLiteral'
        ))->render();

        $mpdf = $this->makeMpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 20,
            'margin_right'  => 20,
        ]);

        $mpdf->SetTitle('Contrato ' . $contrato->codigo_contrato);
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');
        $filename   = $contrato->codigo_contrato . '_contrato.pdf';

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfContent),
        ]);
    }

    // ---------------------------------------------------------------
    private function makeMpdf(array $config = []): \Mpdf\Mpdf
    {
        $tmpDir = storage_path('app/mpdf-tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        return new \Mpdf\Mpdf(array_merge(['tempDir' => $tmpDir], $config));
    }

    // Helpers de conversión numérica a letras (español)
    // ---------------------------------------------------------------

    private function numeroALetras(float $numero): string
    {
        $entero    = (int) abs($numero);
        $decimales = (int) round((abs($numero) - $entero) * 100);

        $texto = $this->enteroALetras($entero);
        if ($decimales > 0) {
            $texto .= ' CON ' . sprintf('%02d', $decimales) . '/100';
        }

        return strtoupper(trim($texto));
    }

    private function enteroALetras(int $n): string
    {
        if ($n === 0) return 'CERO';

        $unidades = [
            '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
            'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS',
            'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        ];
        $veintis = [
            '', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO',
            'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
        ];
        $decenas  = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = [
            '', 'CIEN', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
            'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
        ];

        $resultado = '';

        if ($n >= 1000000) {
            $millones  = intdiv($n, 1000000);
            $resultado .= ($millones === 1 ? 'UN MILLÓN' : $this->enteroALetras($millones) . ' MILLONES');
            $n         %= 1000000;
            if ($n > 0) $resultado .= ' ';
        }

        if ($n >= 1000) {
            $miles     = intdiv($n, 1000);
            $resultado .= ($miles === 1 ? 'MIL' : $this->enteroALetras($miles) . ' MIL');
            $n         %= 1000;
            if ($n > 0) $resultado .= ' ';
        }

        if ($n >= 100) {
            $c    = intdiv($n, 100);
            $rest = $n % 100;
            $resultado .= ($c === 1 && $rest > 0) ? 'CIENTO' : $centenas[$c];
            $n    = $rest;
            if ($n > 0) $resultado .= ' ';
        }

        if ($n > 0) {
            if ($n < 20) {
                $resultado .= $unidades[$n];
            } elseif ($n < 30) {
                $resultado .= ($n === 20) ? 'VEINTE' : $veintis[$n - 20];
            } else {
                $dec = intdiv($n, 10);
                $uni = $n % 10;
                $resultado .= $decenas[$dec];
                if ($uni > 0) $resultado .= ' Y ' . $unidades[$uni];
            }
        }

        return $resultado;
    }
}
