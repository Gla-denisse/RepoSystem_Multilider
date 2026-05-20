<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaVenta;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Asesor;
use App\Models\MiEmpresa;
use App\Exports\ReporteVentasCobrosExport;
use App\Exports\ReporteCarteraMoraExport;
use App\Exports\ReporteComisionesExport;
use App\Exports\ReporteDesempenoAsesoresExport;
use App\Exports\ReporteInventarioPropiedadesExport;
use App\Models\Propiedad;
use App\Models\Egreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class ReporteController extends Controller
{
    // ─── Helpers compartidos ────────────────────────────────────────────────

    private function rangoFechas(Request $request): array
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());
        return [$desde, $hasta];
    }

    private function baseVentasQuery(Request $request, string $desde, string $hasta)
    {
        $query = NotaVenta::whereBetween('fecha', [$desde, $hasta]);

        if ($request->filled('tipo_venta') && $request->tipo_venta !== 'Todos') {
            $query->where('tipo_venta', $request->tipo_venta);
        }
        if ($request->filled('asesor_id')) {
            $query->where('asesor_id', $request->asesor_id);
        }
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }

        return $query;
    }

    private function kpisVentas(Request $request, string $desde, string $hasta): array
    {
        $base = $this->baseVentasQuery($request, $desde, $hasta)->where('estado', '!=', 'Anulada');

        return [
            'total_ventas_monto'    => (clone $base)->sum('monto_total'),
            'total_ventas_cantidad' => (clone $base)->count(),
            'ventas_contado_monto'  => (clone $base)->where('tipo_venta', 'Contado')->sum('monto_total'),
            'ventas_credito_monto'  => (clone $base)->where('tipo_venta', 'Crédito')->sum('monto_total'),
            'total_cobrado'         => Pago::where('estado', 'PAGADO')
                                          ->whereBetween('fecha_pago', [$desde, $hasta])
                                          ->sum('monto'),
        ];
    }

    private function graficoVentas(string $desde, string $hasta): array
    {
        $ventas = NotaVenta::select(
            DB::raw('YEAR(fecha) as anio'),
            DB::raw('MONTH(fecha) as mes'),
            DB::raw('SUM(monto_total) as total')
        )
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('estado', '!=', 'Anulada')
        ->groupBy('anio', 'mes')
        ->orderBy('anio')->orderBy('mes')
        ->get();

        $cobros = Pago::select(
            DB::raw('YEAR(fecha_pago) as anio'),
            DB::raw('MONTH(fecha_pago) as mes'),
            DB::raw('SUM(monto) as total')
        )
        ->whereBetween('fecha_pago', [$desde, $hasta])
        ->where('estado', 'PAGADO')
        ->groupBy('anio', 'mes')
        ->orderBy('anio')->orderBy('mes')
        ->get();

        return ['ventas' => $ventas, 'cobros' => $cobros];
    }

    private function notasConEager($query)
    {
        return $query
            ->with([
                'cliente:id,nombre_completo',
                'asesor:id,nombre_completo',
                'propiedad:id,codigo',
            ])
            ->withSum(['pagos as cobrado' => fn($q) => $q->where('estado', 'PAGADO')], 'monto');
    }

    private function notasARows($notas): array
    {
        return collect($notas)->map(fn($n) => [
            'fecha'         => $n->fecha,
            'id'            => $n->id,
            'cliente'       => $n->cliente?->nombre_completo ?? '-',
            'asesor'        => $n->asesor?->nombre_completo  ?? '-',
            'propiedad'     => $n->propiedad?->codigo         ?? '-',
            'tipo_venta'    => $n->tipo_venta,
            'monto_total'   => (float) $n->monto_total,
            'cobrado'       => (float) ($n->cobrado ?? 0),
            'saldo_credito' => (float) $n->saldo_credito,
            'estado'        => $n->estado,
        ])->toArray();
    }

    // ─── Endpoint JSON (tabla paginada + KPIs + gráfico) ───────────────────

    public function ventasCobros(Request $request)
    {
        [$desde, $hasta] = $this->rangoFechas($request);

        $kpis    = $this->kpisVentas($request, $desde, $hasta);
        $grafico = $this->graficoVentas($desde, $hasta);
        $asesores = Asesor::select('id', 'nombre_completo')->where('estado', 'Activo')->get();

        $notas = $this->notasConEager($this->baseVentasQuery($request, $desde, $hasta))
            ->orderBy('fecha', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'kpis'     => $kpis,
            'grafico'  => $grafico,
            'notas'    => $notas,
            'asesores' => $asesores,
        ]);
    }

    // ─── Endpoint Excel ─────────────────────────────────────────────────────

    public function ventasCobrosExcel(Request $request)
    {
        [$desde, $hasta] = $this->rangoFechas($request);

        $kpis  = $this->kpisVentas($request, $desde, $hasta);
        $notas = $this->notasConEager($this->baseVentasQuery($request, $desde, $hasta))
            ->orderBy('fecha', 'desc')
            ->get();

        $rows = $this->notasARows($notas);

        $filename = 'ventas-cobros-' . $desde . '-al-' . $hasta . '.xlsx';
        return Excel::download(new ReporteVentasCobrosExport($rows, $kpis, $desde, $hasta), $filename);
    }

    // ─── Endpoint PDF ───────────────────────────────────────────────────────

    public function ventasCobrosPdf(Request $request)
    {
        [$desde, $hasta] = $this->rangoFechas($request);

        $kpis   = $this->kpisVentas($request, $desde, $hasta);
        $notas  = $this->notasConEager($this->baseVentasQuery($request, $desde, $hasta))
            ->orderBy('fecha', 'desc')
            ->get();

        $empresa = MiEmpresa::first();
        $rows    = $this->notasARows($notas);
        $html    = $this->buildPdfHtml($kpis, $rows, $desde, $hasta, $empresa);

        $mpdf = new Mpdf([
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 8,
            'margin_right'  => 8,
            'format'        => 'A4-L',
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'ventas-cobros-' . $desde . '-al-' . $hasta . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // ─── HTML para PDF ──────────────────────────────────────────────────────

    private function buildPdfHtml(array $kpis, array $rows, string $desde, string $hasta, ?MiEmpresa $empresa): string
    {
        $empresaNombre   = $empresa?->nombre ?? 'Sistema Multilider';
        $empresaTelefono = $empresa?->telefono ?? '';
        $empresaEmail    = $empresa?->email ?? '';
        $generado        = date('d/m/Y H:i');
        $total           = count($rows);

        $montoTotal    = number_format($kpis['total_ventas_monto'], 2);
        $cantidad      = $kpis['total_ventas_cantidad'];
        $contado       = number_format($kpis['ventas_contado_monto'], 2);
        $credito       = number_format($kpis['ventas_credito_monto'], 2);
        $cobrado       = number_format($kpis['total_cobrado'], 2);

        $filas = '';
        foreach ($rows as $i => $r) {
            $bgRow  = ($i % 2 === 0) ? '#ffffff' : '#f8f9fa';
            $fecha  = date('d/m/Y', strtotime($r['fecha']));
            $monto  = number_format($r['monto_total'], 2);
            $cob    = number_format($r['cobrado'], 2);
            $saldo  = number_format($r['saldo_credito'], 2);
            $est    = htmlspecialchars($r['estado']);
            $estBg  = $r['estado'] === 'Anulada' ? '#fee2e2' : '#d1fae5';
            $estCol = $r['estado'] === 'Anulada' ? '#991b1b' : '#065f46';
            $cliente  = htmlspecialchars($r['cliente']);
            $asesor   = htmlspecialchars($r['asesor']);
            $propiedad = htmlspecialchars($r['propiedad']);
            $tipo     = htmlspecialchars($r['tipo_venta']);

            $filas .= '<tr style="background:' . $bgRow . ';">'
                . '<td>' . $fecha . '</td>'
                . '<td style="text-align:center;">#' . $r['id'] . '</td>'
                . '<td>' . $cliente . '</td>'
                . '<td>' . $asesor . '</td>'
                . '<td style="text-align:center;">' . $propiedad . '</td>'
                . '<td style="text-align:center;">' . $tipo . '</td>'
                . '<td style="text-align:right;">Bs. ' . $monto . '</td>'
                . '<td style="text-align:right;">Bs. ' . $cob . '</td>'
                . '<td style="text-align:right;">Bs. ' . $saldo . '</td>'
                . '<td style="text-align:center;"><span style="background:' . $estBg . ';color:' . $estCol . ';padding:2px 6px;border-radius:8px;font-size:9px;">' . $est . '</span></td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;font-size:10px;color:#333;margin:0;}
.header{text-align:center;margin-bottom:16px;padding-bottom:10px;border-bottom:3px solid #0B2545;}
.header h1{font-size:16px;color:#0B2545;margin:0 0 2px;}
.header p{margin:2px 0;color:#666;font-size:9px;}
.kpis{width:100%;border-collapse:collapse;margin-bottom:16px;}
.kpis td{border:1px solid #ddd;padding:8px;text-align:center;width:20%;}
.kpi-label{font-size:8px;color:#666;text-transform:uppercase;margin-bottom:4px;}
.kpi-value{font-size:14px;font-weight:bold;}
table.main{width:100%;border-collapse:collapse;}
table.main th{background:#0B2545;color:#fff;padding:5px 7px;text-align:left;font-size:9px;}
table.main td{padding:4px 7px;font-size:9px;border-bottom:1px solid #eee;}
.footer{margin-top:12px;text-align:right;font-size:8px;color:#999;}
</style></head><body>

<div class="header">
  <h1>' . htmlspecialchars($empresaNombre) . '</h1>
  <p>Reporte de Ventas y Cobros &nbsp;&bull;&nbsp; Período: ' . $desde . ' al ' . $hasta . '</p>
  ' . ($empresaTelefono ? '<p>Tel: ' . htmlspecialchars($empresaTelefono) . ($empresaEmail ? ' &nbsp;|&nbsp; ' . htmlspecialchars($empresaEmail) : '') . '</p>' : '') . '
</div>

<table class="kpis">
  <tr>
    <td style="background:#eff6ff;">
      <div class="kpi-label">Total Ventas</div>
      <div class="kpi-value" style="color:#1d4ed8;">Bs. ' . $montoTotal . '</div>
      <div style="font-size:8px;color:#666;">' . $cantidad . ' operaciones</div>
    </td>
    <td style="background:#f0fdf4;">
      <div class="kpi-label">Ventas Contado</div>
      <div class="kpi-value" style="color:#15803d;">Bs. ' . $contado . '</div>
    </td>
    <td style="background:#fffbeb;">
      <div class="kpi-label">Ventas Crédito</div>
      <div class="kpi-value" style="color:#b45309;">Bs. ' . $credito . '</div>
    </td>
    <td style="background:#faf5ff;">
      <div class="kpi-label">Total Cobrado (período)</div>
      <div class="kpi-value" style="color:#7c3aed;">Bs. ' . $cobrado . '</div>
    </td>
    <td style="background:#fff1f2;">
      <div class="kpi-label">Saldo por Cobrar</div>
      <div class="kpi-value" style="color:#be123c;">Bs. ' . number_format($kpis['total_ventas_monto'] - $kpis['total_cobrado'], 2) . '</div>
    </td>
  </tr>
</table>

<table class="main">
  <thead>
    <tr>
      <th>Fecha</th><th>N° Nota</th><th>Cliente</th><th>Asesor</th>
      <th>Propiedad</th><th>Tipo</th>
      <th style="text-align:right;">Monto Total</th>
      <th style="text-align:right;">Cobrado</th>
      <th style="text-align:right;">Saldo</th>
      <th style="text-align:center;">Estado</th>
    </tr>
  </thead>
  <tbody>' . $filas . '</tbody>
</table>

<div class="footer">
  Generado el: ' . $generado . ' &nbsp;|&nbsp; Total de registros: ' . $total . '
</div>
</body></html>';
    }

    // ════════════════════════════════════════════════════════════════════════
    // REPORTE 2: CARTERA Y MORA
    // ════════════════════════════════════════════════════════════════════════

    private function baseCuotasVencidas(Request $request)
    {
        $query = Cuota::where('estado', 'Vencida');

        if ($request->filled('asesor_id')) {
            $asesorId = $request->asesor_id;
            $query->whereHas('planPago.notaVenta', fn($q) => $q->where('asesor_id', $asesorId));
        }

        if ($request->filled('desde')) {
            $query->where('fecha_vencimiento', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->where('fecha_vencimiento', '<=', $request->hasta);
        }

        return $query;
    }

    private function calcularAging($cuotas): array
    {
        $tramos = [
            '1-30 días'  => ['label' => '1-30 días',  'min' => 1,  'max' => 30,  'count' => 0, 'monto' => 0, 'clientes' => []],
            '31-60 días' => ['label' => '31-60 días', 'min' => 31, 'max' => 60,  'count' => 0, 'monto' => 0, 'clientes' => []],
            '61-90 días' => ['label' => '61-90 días', 'min' => 61, 'max' => 90,  'count' => 0, 'monto' => 0, 'clientes' => []],
            '+90 días'   => ['label' => '+90 días',   'min' => 91, 'max' => PHP_INT_MAX, 'count' => 0, 'monto' => 0, 'clientes' => []],
        ];

        foreach ($cuotas as $c) {
            $dias = (int) floor((time() - strtotime($c->fecha_vencimiento)) / 86400);
            $dias = max(1, $dias);

            foreach ($tramos as $key => &$tramo) {
                if ($dias >= $tramo['min'] && $dias <= $tramo['max']) {
                    $tramo['count']++;
                    $tramo['monto'] += (float) $c->monto_cuota;
                    $clienteId = $c->planPago?->notaVenta?->cliente_id;
                    if ($clienteId) $tramo['clientes'][$clienteId] = true;
                    break;
                }
            }
            unset($tramo);
        }

        foreach ($tramos as &$t) {
            $t['clientes'] = count($t['clientes']);
        }
        unset($t);

        return $tramos;
    }

    private function cuotasARows($cuotas): array
    {
        return collect($cuotas)->map(function ($c) {
            $dias = (int) floor((time() - strtotime($c->fecha_vencimiento)) / 86400);
            $dias = max(1, $dias);

            if ($dias <= 30)      $tramo = '1-30 días';
            elseif ($dias <= 60)  $tramo = '31-60 días';
            elseif ($dias <= 90)  $tramo = '61-90 días';
            else                  $tramo = '+90 días';

            return [
                'id'               => $c->id,
                'cliente'          => $c->planPago?->notaVenta?->cliente?->nombre_completo ?? '-',
                'cliente_id'       => $c->planPago?->notaVenta?->cliente_id,
                'asesor'           => $c->planPago?->notaVenta?->asesor?->nombre_completo  ?? '-',
                'numero_cuota'     => $c->numero_cuota,
                'fecha_vencimiento'=> $c->fecha_vencimiento,
                'dias_mora'        => $dias,
                'tramo'            => $tramo,
                'monto_cuota'      => (float) $c->monto_cuota,
                'saldo_capital'    => (float) $c->saldo_capital,
            ];
        })->sortByDesc('dias_mora')->values()->toArray();
    }

    private function eagerCuotas($query)
    {
        return $query->with([
            'planPago.notaVenta.cliente:id,nombre_completo',
            'planPago.notaVenta.asesor:id,nombre_completo',
        ]);
    }

    // ─── Endpoint JSON ──────────────────────────────────────────────────────

    public function carteraMora(Request $request)
    {
        $asesorId = $request->input('asesor_id');

        // Cartera total activa
        $notasBase = NotaVenta::where('tipo_venta', 'Crédito')->where('estado', 'Activa');
        if ($asesorId) $notasBase->where('asesor_id', $asesorId);
        $carteraTotal = (clone $notasBase)->sum('saldo_credito');

        // Cuotas vencidas
        $todasVencidas = $this->eagerCuotas($this->baseCuotasVencidas($request))->get();
        $rows          = $this->cuotasARows($todasVencidas);
        $aging         = $this->calcularAging($todasVencidas);

        $kpis = [
            'cartera_total'        => (float) $carteraTotal,
            'monto_vencido'        => array_sum(array_column($aging, 'monto')),
            'clientes_en_mora'     => count(array_unique(array_filter(array_column($rows, 'cliente_id')))),
            'cuotas_vencidas_count'=> count($rows),
        ];

        // Paginación manual (frontend recibe todo pero el componente pagina)
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $total   = count($rows);
        $slice   = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $asesores = Asesor::select('id', 'nombre_completo')->where('estado', 'Activo')->get();

        return response()->json([
            'kpis'     => $kpis,
            'aging'    => array_values($aging),
            'cuotas'   => [
                'data'         => $slice,
                'total'        => $total,
                'current_page' => $page,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
                'per_page'     => $perPage,
                'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ],
            'asesores' => $asesores,
        ]);
    }

    // ─── Endpoint Excel ─────────────────────────────────────────────────────

    public function carteraMoraExcel(Request $request)
    {
        $asesorId = $request->input('asesor_id');
        $notasBase = NotaVenta::where('tipo_venta', 'Crédito')->where('estado', 'Activa');
        if ($asesorId) $notasBase->where('asesor_id', $asesorId);

        $todasVencidas = $this->eagerCuotas($this->baseCuotasVencidas($request))->get();
        $rows  = $this->cuotasARows($todasVencidas);
        $aging = $this->calcularAging($todasVencidas);
        $kpis  = [
            'cartera_total'        => (float) (clone $notasBase)->sum('saldo_credito'),
            'monto_vencido'        => array_sum(array_column($aging, 'monto')),
            'clientes_en_mora'     => count(array_unique(array_filter(array_column($rows, 'cliente_id')))),
            'cuotas_vencidas_count'=> count($rows),
        ];

        $filename = 'cartera-mora-' . now()->toDateString() . '.xlsx';
        return Excel::download(new ReporteCarteraMoraExport($rows, $kpis, $aging, now()->toDateString()), $filename);
    }

    // ─── Endpoint PDF ───────────────────────────────────────────────────────

    public function carteraMoraPdf(Request $request)
    {
        $asesorId = $request->input('asesor_id');
        $notasBase = NotaVenta::where('tipo_venta', 'Crédito')->where('estado', 'Activa');
        if ($asesorId) $notasBase->where('asesor_id', $asesorId);

        $todasVencidas = $this->eagerCuotas($this->baseCuotasVencidas($request))->get();
        $rows  = $this->cuotasARows($todasVencidas);
        $aging = $this->calcularAging($todasVencidas);
        $kpis  = [
            'cartera_total'        => (float) (clone $notasBase)->sum('saldo_credito'),
            'monto_vencido'        => array_sum(array_column($aging, 'monto')),
            'clientes_en_mora'     => count(array_unique(array_filter(array_column($rows, 'cliente_id')))),
            'cuotas_vencidas_count'=> count($rows),
        ];

        $empresa = MiEmpresa::first();
        $html    = $this->buildCarteraMoraPdfHtml($kpis, $aging, $rows, $empresa);

        $mpdf = new Mpdf([
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'format'        => 'A4-L',
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'cartera-mora-' . now()->toDateString() . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildCarteraMoraPdfHtml(array $kpis, array $aging, array $rows, ?MiEmpresa $empresa): string
    {
        $empresaNombre = $empresa?->nombre ?? 'Sistema Multilider';
        $generado      = date('d/m/Y H:i');
        $hoy           = date('d/m/Y');
        $total         = count($rows);

        $carteraTotal = number_format($kpis['cartera_total'], 2);
        $montoVencido = number_format($kpis['monto_vencido'], 2);
        $clientes     = $kpis['clientes_en_mora'];
        $cuotas       = $kpis['cuotas_vencidas_count'];

        // Tabla aging HTML
        $agingHtml = '';
        $totalMonto = array_sum(array_column($aging, 'monto'));
        foreach ($aging as $t) {
            $pct    = $totalMonto > 0 ? round($t['monto'] / $totalMonto * 100, 1) : 0;
            $bg     = match($t['label']) {
                '1-30 días'  => '#fffbeb',
                '31-60 días' => '#fff7ed',
                '61-90 días' => '#fef2f2',
                '+90 días'   => '#fce7f3',
                default      => '#f8f9fa',
            };
            $agingHtml .= '<tr style="background:' . $bg . ';">'
                . '<td style="font-weight:bold;">' . $t['label'] . '</td>'
                . '<td style="text-align:center;">' . $t['count'] . '</td>'
                . '<td style="text-align:center;">' . $t['clientes'] . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($t['monto'], 2) . '</td>'
                . '<td style="text-align:center;">' . $pct . '%</td>'
                . '</tr>';
        }

        // Tabla detalle HTML
        $detalleHtml = '';
        foreach ($rows as $i => $r) {
            $bg      = $i % 2 === 0 ? '#fff' : '#f8f9fa';
            $fecha   = date('d/m/Y', strtotime($r['fecha_vencimiento']));
            $tramoBg = match($r['tramo']) {
                '1-30 días'  => '#fef9c3',
                '31-60 días' => '#fed7aa',
                '61-90 días' => '#fecaca',
                '+90 días'   => '#f9a8d4',
                default      => '#e5e7eb',
            };
            $detalleHtml .= '<tr style="background:' . $bg . ';">'
                . '<td>' . htmlspecialchars($r['cliente']) . '</td>'
                . '<td>' . htmlspecialchars($r['asesor']) . '</td>'
                . '<td style="text-align:center;">N° ' . $r['numero_cuota'] . '</td>'
                . '<td style="text-align:center;">' . $fecha . '</td>'
                . '<td style="text-align:center;font-weight:bold;color:#dc2626;">' . $r['dias_mora'] . ' días</td>'
                . '<td style="text-align:center;"><span style="background:' . $tramoBg . ';padding:1px 6px;border-radius:8px;font-size:9px;">' . $r['tramo'] . '</span></td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['monto_cuota'], 2) . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['saldo_capital'], 2) . '</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;font-size:10px;color:#333;}
.header{text-align:center;margin-bottom:14px;padding-bottom:8px;border-bottom:3px solid #0B2545;}
.header h1{font-size:15px;color:#0B2545;margin:0 0 2px;}
.header p{margin:2px 0;color:#666;font-size:9px;}
.kpis{width:100%;border-collapse:collapse;margin-bottom:14px;}
.kpis td{border:1px solid #ddd;padding:7px;text-align:center;width:25%;}
.kpi-label{font-size:8px;color:#666;text-transform:uppercase;margin-bottom:3px;}
.kpi-value{font-size:13px;font-weight:bold;}
.section-title{font-size:11px;font-weight:bold;color:#0B2545;margin:14px 0 6px;padding-bottom:3px;border-bottom:1px solid #ddd;}
table.aging,table.detail{width:100%;border-collapse:collapse;margin-bottom:14px;}
table.aging th,table.detail th{background:#0B2545;color:#fff;padding:5px 7px;text-align:left;font-size:9px;}
table.aging td,table.detail td{padding:4px 7px;font-size:9px;border-bottom:1px solid #eee;}
.footer{margin-top:10px;text-align:right;font-size:8px;color:#999;}
</style></head><body>

<div class="header">
  <h1>' . htmlspecialchars($empresaNombre) . '</h1>
  <p>Reporte de Cartera y Mora &nbsp;&bull;&nbsp; Corte al: ' . $hoy . '</p>
</div>

<table class="kpis">
  <tr>
    <td style="background:#eff6ff;">
      <div class="kpi-label">Cartera Total Activa</div>
      <div class="kpi-value" style="color:#1d4ed8;">Bs. ' . $carteraTotal . '</div>
    </td>
    <td style="background:#fef2f2;">
      <div class="kpi-label">Monto Vencido</div>
      <div class="kpi-value" style="color:#dc2626;">Bs. ' . $montoVencido . '</div>
    </td>
    <td style="background:#fff7ed;">
      <div class="kpi-label">Clientes en Mora</div>
      <div class="kpi-value" style="color:#ea580c;">' . $clientes . '</div>
    </td>
    <td style="background:#fef9c3;">
      <div class="kpi-label">Cuotas Vencidas</div>
      <div class="kpi-value" style="color:#ca8a04;">' . $cuotas . '</div>
    </td>
  </tr>
</table>

<div class="section-title">Análisis de Mora (Aging)</div>
<table class="aging">
  <thead><tr>
    <th>Tramo</th><th style="text-align:center;">Cuotas</th>
    <th style="text-align:center;">Clientes</th>
    <th style="text-align:right;">Monto (Bs.)</th>
    <th style="text-align:center;">% del Total</th>
  </tr></thead>
  <tbody>' . $agingHtml . '</tbody>
</table>

<div class="section-title">Detalle de Cuotas Vencidas (' . $total . ' registros)</div>
<table class="detail">
  <thead><tr>
    <th>Cliente</th><th>Asesor</th><th style="text-align:center;">Cuota</th>
    <th style="text-align:center;">Vencimiento</th>
    <th style="text-align:center;">Días Mora</th>
    <th style="text-align:center;">Tramo</th>
    <th style="text-align:right;">Monto Cuota</th>
    <th style="text-align:right;">Saldo Capital</th>
  </tr></thead>
  <tbody>' . $detalleHtml . '</tbody>
</table>

<div class="footer">Generado el: ' . $generado . ' &nbsp;|&nbsp; Total de registros: ' . $total . '</div>
</body></html>';
    }

    // ════════════════════════════════════════════════════════════════════════
    // REPORTE 3: COMISIONES
    // ════════════════════════════════════════════════════════════════════════

    private function baseComisionesQuery(Request $request)
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());

        $query = Egreso::where('categoria', 'COMISION_ASESOR')
            ->whereBetween('fecha', [$desde, $hasta]);

        if ($request->filled('asesor_id')) {
            $query->where('asesor_id', $request->asesor_id);
        }
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('moneda') && $request->moneda !== 'Todos') {
            $query->where('moneda', $request->moneda);
        }

        return [$query, $desde, $hasta];
    }

    private function kpisComisiones($base): array
    {
        return [
            'pendiente_bs'  => (float)(clone $base)->where('estado', 'PENDIENTE')->where('moneda', 'Bs')->sum('monto'),
            'pendiente_usd' => (float)(clone $base)->where('estado', 'PENDIENTE')->where('moneda', '$')->sum('monto'),
            'pagado_bs'     => (float)(clone $base)->where('estado', 'PAGADO')->where('moneda', 'Bs')->sum('monto'),
            'pagado_usd'    => (float)(clone $base)->where('estado', 'PAGADO')->where('moneda', '$')->sum('monto'),
            'total_count'   => (clone $base)->count(),
            'pendiente_count' => (clone $base)->where('estado', 'PENDIENTE')->count(),
            'pagado_count'    => (clone $base)->where('estado', 'PAGADO')->count(),
        ];
    }

    private function graficoComisiones($base): array
    {
        return (clone $base)
            ->where('moneda', 'Bs')
            ->with('asesor:id,nombre_completo')
            ->select('asesor_id',
                DB::raw('SUM(CASE WHEN estado = "PENDIENTE" THEN monto ELSE 0 END) as pendiente'),
                DB::raw('SUM(CASE WHEN estado = "PAGADO"    THEN monto ELSE 0 END) as pagado')
            )
            ->groupBy('asesor_id')
            ->orderByDesc('pendiente')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'asesor'    => $r->asesor?->nombre_completo ?? 'Sin asesor',
                'pendiente' => (float) $r->pendiente,
                'pagado'    => (float) $r->pagado,
            ])
            ->toArray();
    }

    private function egresosARows($egresos): array
    {
        return collect($egresos)->map(fn($e) => [
            'id'           => $e->id,
            'fecha'        => $e->fecha?->toDateString(),
            'asesor'       => $e->asesor?->nombre_completo ?? '-',
            'asesor_id'    => $e->asesor_id,
            'nota_venta_id'=> $e->nota_venta_id,
            'propiedad'    => $e->notaVenta?->propiedad?->codigo ?? '-',
            'cliente'      => $e->notaVenta?->cliente?->nombre_completo ?? '-',
            'moneda'       => $e->moneda,
            'monto'        => (float) $e->monto,
            'estado'       => $e->estado,
            'fecha_pago'   => $e->estado === 'PAGADO' ? $e->fecha?->toDateString() : null,
            'comprobante'  => $e->comprobante ?? '',
            'concepto'     => $e->concepto ?? '',
        ])->toArray();
    }

    private function eagerEgresos($query)
    {
        return $query->with([
            'asesor:id,nombre_completo',
            'notaVenta.propiedad:id,codigo',
            'notaVenta.cliente:id,nombre_completo',
        ]);
    }

    // ─── Endpoint JSON ──────────────────────────────────────────────────────

    public function comisiones(Request $request)
    {
        [$base, $desde, $hasta] = $this->baseComisionesQuery($request);

        $kpis    = $this->kpisComisiones($base);
        $grafico = $this->graficoComisiones($base);
        $asesores = Asesor::select('id', 'nombre_completo')->where('estado', 'Activo')->get();

        $egresos = $this->eagerEgresos(clone $base)
            ->orderByDesc('fecha')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'kpis'     => $kpis,
            'grafico'  => $grafico,
            'egresos'  => $egresos,
            'asesores' => $asesores,
            'desde'    => $desde,
            'hasta'    => $hasta,
        ]);
    }

    // ─── Endpoint Excel ─────────────────────────────────────────────────────

    public function comisionesExcel(Request $request)
    {
        [$base, $desde, $hasta] = $this->baseComisionesQuery($request);

        $kpis    = $this->kpisComisiones($base);
        $egresos = $this->eagerEgresos(clone $base)->orderByDesc('fecha')->get();
        $rows    = $this->egresosARows($egresos);

        $filename = 'comisiones-' . $desde . '-al-' . $hasta . '.xlsx';
        return Excel::download(new ReporteComisionesExport($rows, $kpis, $desde, $hasta), $filename);
    }

    // ─── Endpoint PDF ───────────────────────────────────────────────────────

    public function comisionesPdf(Request $request)
    {
        [$base, $desde, $hasta] = $this->baseComisionesQuery($request);

        $kpis    = $this->kpisComisiones($base);
        $egresos = $this->eagerEgresos(clone $base)->orderByDesc('fecha')->get();
        $rows    = $this->egresosARows($egresos);
        $empresa = MiEmpresa::first();
        $html    = $this->buildComisionesPdfHtml($kpis, $rows, $desde, $hasta, $empresa);

        $mpdf = new Mpdf([
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'margin_left'   => 10,
            'margin_right'  => 10,
            'format'        => 'A4-L',
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'comisiones-' . $desde . '-al-' . $hasta . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildComisionesPdfHtml(array $kpis, array $rows, string $desde, string $hasta, ?MiEmpresa $empresa): string
    {
        $empresaNombre = $empresa?->nombre ?? 'Sistema Multilider';
        $generado      = date('d/m/Y H:i');
        $total         = count($rows);

        $pendBs  = number_format($kpis['pendiente_bs'], 2);
        $pagBs   = number_format($kpis['pagado_bs'], 2);
        $pendUsd = number_format($kpis['pendiente_usd'], 2);
        $pagUsd  = number_format($kpis['pagado_usd'], 2);
        $cnt     = $kpis['total_count'];

        $filas = '';
        foreach ($rows as $i => $r) {
            $bg      = $i % 2 === 0 ? '#fff' : '#f8f9fa';
            $fecha   = $r['fecha'] ? date('d/m/Y', strtotime($r['fecha'])) : '-';
            $estBg   = $r['estado'] === 'PAGADO' ? '#d1fae5' : '#fef9c3';
            $estCol  = $r['estado'] === 'PAGADO' ? '#065f46' : '#713f12';
            $filas  .= '<tr style="background:' . $bg . ';">'
                . '<td>' . $fecha . '</td>'
                . '<td>' . htmlspecialchars($r['asesor']) . '</td>'
                . '<td style="text-align:center;">' . ($r['nota_venta_id'] ? '#' . $r['nota_venta_id'] : '-') . '</td>'
                . '<td>' . htmlspecialchars($r['propiedad']) . '</td>'
                . '<td>' . htmlspecialchars($r['cliente']) . '</td>'
                . '<td style="text-align:center;">' . $r['moneda'] . '</td>'
                . '<td style="text-align:right;font-weight:bold;">' . number_format($r['monto'], 2) . '</td>'
                . '<td style="text-align:center;"><span style="background:' . $estBg . ';color:' . $estCol . ';padding:2px 6px;border-radius:8px;font-size:9px;">' . $r['estado'] . '</span></td>'
                . '<td style="text-align:center;">' . ($r['comprobante'] ?: '-') . '</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;font-size:10px;color:#333;}
.header{text-align:center;margin-bottom:14px;padding-bottom:8px;border-bottom:3px solid #0B2545;}
.header h1{font-size:15px;color:#0B2545;margin:0 0 2px;}
.header p{margin:2px 0;color:#666;font-size:9px;}
.kpis{width:100%;border-collapse:collapse;margin-bottom:14px;}
.kpis td{border:1px solid #ddd;padding:7px;text-align:center;}
.kpi-label{font-size:8px;color:#666;text-transform:uppercase;margin-bottom:3px;}
.kpi-value{font-size:13px;font-weight:bold;}
.section-title{font-size:11px;font-weight:bold;color:#0B2545;margin:14px 0 6px;padding-bottom:3px;border-bottom:1px solid #ddd;}
table.main{width:100%;border-collapse:collapse;}
table.main th{background:#0B2545;color:#fff;padding:5px 7px;text-align:left;font-size:9px;}
table.main td{padding:4px 7px;font-size:9px;border-bottom:1px solid #eee;}
.footer{margin-top:10px;text-align:right;font-size:8px;color:#999;}
</style></head><body>

<div class="header">
  <h1>' . htmlspecialchars($empresaNombre) . '</h1>
  <p>Reporte de Comisiones &nbsp;&bull;&nbsp; Período: ' . $desde . ' al ' . $hasta . '</p>
</div>

<table class="kpis">
  <tr>
    <td style="background:#f0fdf4;">
      <div class="kpi-label">Pagado (Bs.)</div>
      <div class="kpi-value" style="color:#15803d;">Bs. ' . $pagBs . '</div>
      <div style="font-size:8px;color:#666;">' . $kpis['pagado_count'] . ' comisiones</div>
    </td>
    <td style="background:#fffbeb;">
      <div class="kpi-label">Pendiente (Bs.)</div>
      <div class="kpi-value" style="color:#b45309;">Bs. ' . $pendBs . '</div>
      <div style="font-size:8px;color:#666;">' . $kpis['pendiente_count'] . ' comisiones</div>
    </td>
    <td style="background:#f0fdf4;">
      <div class="kpi-label">Pagado (USD)</div>
      <div class="kpi-value" style="color:#15803d;">$ ' . $pagUsd . '</div>
    </td>
    <td style="background:#fffbeb;">
      <div class="kpi-label">Pendiente (USD)</div>
      <div class="kpi-value" style="color:#b45309;">$ ' . $pendUsd . '</div>
    </td>
    <td style="background:#eff6ff;">
      <div class="kpi-label">Total Registros</div>
      <div class="kpi-value" style="color:#1d4ed8;">' . $cnt . '</div>
    </td>
  </tr>
</table>

<div class="section-title">Detalle de Comisiones (' . $total . ' registros)</div>
<table class="main">
  <thead><tr>
    <th>Fecha</th><th>Asesor</th><th style="text-align:center;">N° Venta</th>
    <th>Propiedad</th><th>Cliente</th>
    <th style="text-align:center;">Moneda</th>
    <th style="text-align:right;">Monto</th>
    <th style="text-align:center;">Estado</th>
    <th style="text-align:center;">Comprobante</th>
  </tr></thead>
  <tbody>' . $filas . '</tbody>
</table>

<div class="footer">Generado el: ' . $generado . ' &nbsp;|&nbsp; Total: ' . $total . ' registros</div>
</body></html>';
    }

    // ════════════════════════════════════════════════════════════════════════
    // REPORTE 4: DESEMPEÑO ASESORES
    // ════════════════════════════════════════════════════════════════════════

    private function rangoDesempeno(Request $request): array
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());
        return [$desde, $hasta];
    }

    private function buildRankingAsesores(string $desde, string $hasta, ?int $asesorId): array
    {
        // 1. Ventas en período por asesor (4 queries totales, eficiente)
        $ventasQ = DB::table('notas_ventas')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')
            ->when($asesorId, fn($q) => $q->where('asesor_id', $asesorId))
            ->select('asesor_id',
                DB::raw('COUNT(*) as ventas_cantidad'),
                DB::raw('SUM(monto_total) as monto_vendido'),
                DB::raw('SUM(monto_comision) as comision_estimada')
            )
            ->groupBy('asesor_id')
            ->get()->keyBy('asesor_id');

        // 2. Cobrado en período (pagos → notas_ventas)
        $cobradoQ = DB::table('pagos')
            ->join('notas_ventas', 'pagos.nota_venta_id', '=', 'notas_ventas.id')
            ->where('pagos.estado', 'PAGADO')
            ->whereBetween('pagos.fecha_pago', [$desde, $hasta])
            ->when($asesorId, fn($q) => $q->where('notas_ventas.asesor_id', $asesorId))
            ->select('notas_ventas.asesor_id', DB::raw('SUM(pagos.monto) as cobrado'))
            ->groupBy('notas_ventas.asesor_id')
            ->get()->keyBy('asesor_id');

        // 3. Cartera vigente por asesor (independiente del período)
        $carteraQ = DB::table('notas_ventas')
            ->where('tipo_venta', 'Crédito')
            ->where('estado', 'Activa')
            ->when($asesorId, fn($q) => $q->where('asesor_id', $asesorId))
            ->select('asesor_id', DB::raw('SUM(saldo_credito) as cartera'))
            ->groupBy('asesor_id')
            ->get()->keyBy('asesor_id');

        // 4. Mora en cartera por asesor (cuotas → planes_pagos → notas_ventas)
        $moraQ = DB::table('cuotas')
            ->join('planes_pagos', 'cuotas.plan_pago_id', '=', 'planes_pagos.id')
            ->join('notas_ventas', 'planes_pagos.nota_venta_id', '=', 'notas_ventas.id')
            ->where('cuotas.estado', 'Vencida')
            ->when($asesorId, fn($q) => $q->where('notas_ventas.asesor_id', $asesorId))
            ->select('notas_ventas.asesor_id', DB::raw('SUM(cuotas.monto_cuota) as mora'))
            ->groupBy('notas_ventas.asesor_id')
            ->get()->keyBy('asesor_id');

        // Combinar con lista de asesores activos
        $asesores = Asesor::where('estado', 'Activo')
            ->when($asesorId, fn($q) => $q->where('id', $asesorId))
            ->get(['id', 'nombre_completo', 'telefono', 'correo', 'foto']);

        $ranking = $asesores->map(fn($a) => [
            'id'                => $a->id,
            'nombre'            => $a->nombre_completo,
            'telefono'          => $a->telefono ?? '-',
            'correo'            => $a->correo   ?? '-',
            'ventas_cantidad'   => (int)   ($ventasQ[$a->id]->ventas_cantidad   ?? 0),
            'monto_vendido'     => (float) ($ventasQ[$a->id]->monto_vendido     ?? 0),
            'comision_estimada' => (float) ($ventasQ[$a->id]->comision_estimada ?? 0),
            'cobrado'           => (float) ($cobradoQ[$a->id]->cobrado          ?? 0),
            'cartera_vigente'   => (float) ($carteraQ[$a->id]->cartera          ?? 0),
            'mora'              => (float) ($moraQ[$a->id]->mora                ?? 0),
        ])
        ->sortByDesc('monto_vendido')
        ->values()
        ->toArray();

        return $ranking;
    }

    private function kpisDesempeno(array $ranking): array
    {
        $conVentas = array_filter($ranking, fn($r) => $r['ventas_cantidad'] > 0);
        $topAsesor = count($conVentas) > 0 ? reset($conVentas) : null;

        return [
            'top_asesor'          => $topAsesor ? ['nombre' => $topAsesor['nombre'], 'monto' => $topAsesor['monto_vendido']] : null,
            'total_vendido'       => array_sum(array_column($ranking, 'monto_vendido')),
            'total_cobrado'       => array_sum(array_column($ranking, 'cobrado')),
            'total_comisiones'    => array_sum(array_column($ranking, 'comision_estimada')),
            'asesores_con_ventas' => count($conVentas),
            'total_asesores'      => count($ranking),
        ];
    }

    private function graficoVentasMes(string $desde, string $hasta, ?int $asesorId): array
    {
        return DB::table('notas_ventas')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')
            ->when($asesorId, fn($q) => $q->where('asesor_id', $asesorId))
            ->select(
                DB::raw('YEAR(fecha) as anio'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('SUM(monto_total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy('anio', 'mes')
            ->orderBy('anio')->orderBy('mes')
            ->get()
            ->toArray();
    }

    // ─── Endpoint JSON ──────────────────────────────────────────────────────

    public function desempenoAsesores(Request $request)
    {
        [$desde, $hasta] = $this->rangoDesempeno($request);
        $asesorId = $request->filled('asesor_id') ? (int) $request->asesor_id : null;

        $ranking   = $this->buildRankingAsesores($desde, $hasta, $asesorId);
        $kpis      = $this->kpisDesempeno($ranking);
        $grafico   = $this->graficoVentasMes($desde, $hasta, $asesorId);
        $asesores  = Asesor::select('id', 'nombre_completo')->where('estado', 'Activo')->get();

        // Detalle de ventas (paginado), filtrado por asesor si está seleccionado
        $ventasQuery = NotaVenta::with([
                'cliente:id,nombre_completo',
                'asesor:id,nombre_completo',
                'propiedad:id,codigo',
            ])
            ->withSum(['pagos as cobrado' => fn($q) => $q->where('estado', 'PAGADO')], 'monto')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'Anulada')
            ->when($asesorId, fn($q) => $q->where('asesor_id', $asesorId))
            ->orderBy('fecha', 'desc');

        $ventas = $ventasQuery->paginate($request->input('per_page', 15));

        return response()->json([
            'kpis'     => $kpis,
            'ranking'  => $ranking,
            'grafico'  => $grafico,
            'ventas'   => $ventas,
            'asesores' => $asesores,
        ]);
    }

    // ─── Endpoint Excel ─────────────────────────────────────────────────────

    public function desempenoAsesoresExcel(Request $request)
    {
        [$desde, $hasta] = $this->rangoDesempeno($request);
        $asesorId = $request->filled('asesor_id') ? (int) $request->asesor_id : null;

        $ranking = $this->buildRankingAsesores($desde, $hasta, $asesorId);
        $kpis    = $this->kpisDesempeno($ranking);

        $filename = 'desempeno-asesores-' . $desde . '-al-' . $hasta . '.xlsx';
        return Excel::download(new ReporteDesempenoAsesoresExport($ranking, $kpis, $desde, $hasta), $filename);
    }

    // ─── Endpoint PDF ───────────────────────────────────────────────────────

    public function desempenoAsesoresPdf(Request $request)
    {
        [$desde, $hasta] = $this->rangoDesempeno($request);
        $asesorId = $request->filled('asesor_id') ? (int) $request->asesor_id : null;

        $ranking = $this->buildRankingAsesores($desde, $hasta, $asesorId);
        $kpis    = $this->kpisDesempeno($ranking);
        $empresa = MiEmpresa::first();
        $html    = $this->buildDesempenoPdfHtml($kpis, $ranking, $desde, $hasta, $empresa);

        $mpdf = new Mpdf([
            'margin_top' => 12, 'margin_bottom' => 12,
            'margin_left' => 10, 'margin_right' => 10,
            'format' => 'A4-L',
        ]);
        $mpdf->WriteHTML($html);

        $filename = 'desempeno-asesores-' . $desde . '-al-' . $hasta . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildDesempenoPdfHtml(array $kpis, array $ranking, string $desde, string $hasta, ?MiEmpresa $empresa): string
    {
        $empresaNombre = $empresa?->nombre ?? 'Sistema Multilider';
        $generado      = date('d/m/Y H:i');

        $totalVendido    = number_format($kpis['total_vendido'], 2);
        $totalCobrado    = number_format($kpis['total_cobrado'], 2);
        $totalComisiones = number_format($kpis['total_comisiones'], 2);
        $topNombre       = $kpis['top_asesor']['nombre'] ?? '-';
        $topMonto        = $kpis['top_asesor'] ? number_format($kpis['top_asesor']['monto'], 2) : '0.00';
        $conVentas       = $kpis['asesores_con_ventas'];

        $filas = '';
        foreach ($ranking as $i => $r) {
            $bg      = $i % 2 === 0 ? '#fff' : '#f8f9fa';
            $pctMora = $r['cartera_vigente'] > 0 ? round($r['mora'] / $r['cartera_vigente'] * 100, 1) : 0;
            $moraBg  = $pctMora > 20 ? '#fef2f2' : ($pctMora > 5 ? '#fffbeb' : 'transparent');
            $medal   = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => ($i + 1) . '°' };
            $filas  .= '<tr style="background:' . $bg . ';">'
                . '<td style="text-align:center;font-size:11px;">' . $medal . '</td>'
                . '<td style="font-weight:bold;">' . htmlspecialchars($r['nombre']) . '</td>'
                . '<td style="text-align:center;">' . $r['ventas_cantidad'] . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['monto_vendido'], 2) . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['cobrado'], 2) . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['comision_estimada'], 2) . '</td>'
                . '<td style="text-align:right;">Bs. ' . number_format($r['cartera_vigente'], 2) . '</td>'
                . '<td style="text-align:right;background:' . $moraBg . ';">Bs. ' . number_format($r['mora'], 2) . '</td>'
                . '<td style="text-align:center;background:' . $moraBg . ';">' . $pctMora . '%</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;font-size:10px;color:#333;}
.header{text-align:center;margin-bottom:14px;padding-bottom:8px;border-bottom:3px solid #0B2545;}
.header h1{font-size:15px;color:#0B2545;margin:0 0 2px;}
.header p{margin:2px 0;color:#666;font-size:9px;}
.kpis{width:100%;border-collapse:collapse;margin-bottom:14px;}
.kpis td{border:1px solid #ddd;padding:7px;text-align:center;}
.kpi-label{font-size:8px;color:#666;text-transform:uppercase;margin-bottom:3px;}
.kpi-value{font-size:13px;font-weight:bold;}
.section-title{font-size:11px;font-weight:bold;color:#0B2545;margin:14px 0 6px;padding-bottom:3px;border-bottom:1px solid #ddd;}
table.main{width:100%;border-collapse:collapse;}
table.main th{background:#0B2545;color:#fff;padding:5px 7px;text-align:left;font-size:9px;}
table.main td{padding:4px 7px;font-size:9px;border-bottom:1px solid #eee;}
.footer{margin-top:10px;text-align:right;font-size:8px;color:#999;}
</style></head><body>

<div class="header">
  <h1>' . htmlspecialchars($empresaNombre) . '</h1>
  <p>Reporte de Desempeño de Asesores &nbsp;&bull;&nbsp; Período: ' . $desde . ' al ' . $hasta . '</p>
</div>

<table class="kpis">
  <tr>
    <td style="background:#fffbeb;">
      <div class="kpi-label">Top Asesor</div>
      <div class="kpi-value" style="color:#b45309;font-size:11px;">' . htmlspecialchars($topNombre) . '</div>
      <div style="font-size:8px;color:#666;">Bs. ' . $topMonto . '</div>
    </td>
    <td style="background:#eff6ff;">
      <div class="kpi-label">Total Vendido</div>
      <div class="kpi-value" style="color:#1d4ed8;">Bs. ' . $totalVendido . '</div>
    </td>
    <td style="background:#f0fdf4;">
      <div class="kpi-label">Total Cobrado</div>
      <div class="kpi-value" style="color:#15803d;">Bs. ' . $totalCobrado . '</div>
    </td>
    <td style="background:#fdf4ff;">
      <div class="kpi-label">Total Comisiones</div>
      <div class="kpi-value" style="color:#7c3aed;">Bs. ' . $totalComisiones . '</div>
    </td>
    <td style="background:#f8f9fa;">
      <div class="kpi-label">Asesores con Ventas</div>
      <div class="kpi-value" style="color:#374151;">' . $conVentas . '</div>
    </td>
  </tr>
</table>

<div class="section-title">Ranking de Asesores</div>
<table class="main">
  <thead><tr>
    <th style="text-align:center;">Pos.</th>
    <th>Asesor</th>
    <th style="text-align:center;">Ventas #</th>
    <th style="text-align:right;">Monto Vendido</th>
    <th style="text-align:right;">Cobrado</th>
    <th style="text-align:right;">Comisión Est.</th>
    <th style="text-align:right;">Cartera Vigente</th>
    <th style="text-align:right;">Mora</th>
    <th style="text-align:center;">% Mora</th>
  </tr></thead>
  <tbody>' . $filas . '</tbody>
</table>

<div class="footer">Generado el: ' . $generado . ' &nbsp;|&nbsp; Total asesores: ' . count($ranking) . '</div>
</body></html>';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REPORTE 5 — INVENTARIO PROPIEDADES
    // ═══════════════════════════════════════════════════════════════════════

    private function baseInventarioQuery(Request $request)
    {
        $query = Propiedad::with([
            'sectorUrbano:id,nombre,distrito_id',
            'sectorUrbano.distrito:id,nombre,ciudad_id',
            'sectorUrbano.distrito.ciudad:id,nombre',
        ])->where('activo', true);

        if ($request->filled('tipo') && $request->tipo !== 'Todos') {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('moneda') && $request->moneda !== 'Todos') {
            $query->where('moneda', $request->moneda);
        }
        if ($request->filled('precio_min')) {
            $query->where('precio_venta', '>=', $request->precio_min);
        }
        if ($request->filled('precio_max')) {
            $query->where('precio_venta', '<=', $request->precio_max);
        }
        if ($request->filled('sector_urbano_id')) {
            $query->where('sector_urbano_id', $request->sector_urbano_id);
        }
        if ($request->filled('distrito_id')) {
            $query->whereHas('sectorUrbano', fn($q) => $q->where('distrito_id', $request->distrito_id));
        }
        if ($request->filled('ciudad_id')) {
            $query->whereHas('sectorUrbano.distrito', fn($q) => $q->where('ciudad_id', $request->ciudad_id));
        }

        return $query;
    }

    private function kpisInventario(Request $request): array
    {
        $base = $this->baseInventarioQuery($request);

        $total       = (clone $base)->count();
        $disponibles = (clone $base)->where('estado', 'Disponible')->count();
        $vendidas    = (clone $base)->where('estado', 'Vendido')->count();
        $reservadas  = (clone $base)->where('estado', 'Reservado')->count();

        $valorUSD = (clone $base)->where('estado', 'Disponible')->where('moneda', 'USD')
            ->sum('precio_venta');
        $valorBOB = (clone $base)->where('estado', 'Disponible')->where('moneda', 'BOB')
            ->sum('precio_venta');
        $supProm  = (clone $base)->avg('superficie_m2') ?? 0;

        return [
            'total'                => $total,
            'disponibles'          => $disponibles,
            'vendidas'             => $vendidas,
            'reservadas'           => $reservadas,
            'valor_disponible_usd' => (float) $valorUSD,
            'valor_disponible_bob' => (float) $valorBOB,
            'superficie_promedio'  => round((float) $supProm, 2),
        ];
    }

    private function graficoEstados(Request $request): array
    {
        $base = $this->baseInventarioQuery($request);
        return (clone $base)
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->map(fn($r) => ['estado' => $r->estado, 'total' => (int)$r->total])
            ->toArray();
    }

    private function graficoTipos(Request $request): array
    {
        $base = $this->baseInventarioQuery($request);
        return (clone $base)
            ->select('tipo', DB::raw('COUNT(*) as total'), DB::raw('SUM(precio_venta) as valor'))
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'tipo'  => $r->tipo,
                'total' => (int)$r->total,
                'valor' => (float)$r->valor,
            ])
            ->toArray();
    }

    private function mapearPropiedad(Propiedad $p): array
    {
        return [
            'id'                       => $p->id,
            'codigo'                   => $p->codigo,
            'tipo'                     => $p->tipo,
            'sector_urbano'            => $p->sectorUrbano?->nombre,
            'distrito'                 => $p->sectorUrbano?->distrito?->nombre,
            'ciudad'                   => $p->sectorUrbano?->distrito?->ciudad?->nombre,
            'direccion'                => $p->direccion,
            'nro_lote'                 => $p->nro_lote,
            'superficie_m2'            => (float) $p->superficie_m2,
            'superficie_construida_m2' => $p->superficie_construida_m2 ? (float)$p->superficie_construida_m2 : null,
            'frente_mts'               => $p->frente_mts  ? (float)$p->frente_mts  : null,
            'fondo_mts'                => $p->fondo_mts   ? (float)$p->fondo_mts   : null,
            'habitaciones'             => $p->habitaciones,
            'banos'                    => $p->banos,
            'es_esquina'               => (bool)$p->es_esquina,
            'precio_venta'             => (float) $p->precio_venta,
            'moneda'                   => $p->moneda,
            'estado'                   => $p->estado,
        ];
    }

    public function inventarioPropiedades(Request $request)
    {
        $kpis         = $this->kpisInventario($request);
        $graficoEstados = $this->graficoEstados($request);
        $graficoTipos   = $this->graficoTipos($request);

        $perPage = (int)$request->input('per_page', 15);
        $paginated = $this->baseInventarioQuery($request)
            ->orderByRaw("FIELD(estado,'Disponible','Reservado','Vendido')")
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->paginate($perPage);

        $items = $paginated->getCollection()->map(fn($p) => $this->mapearPropiedad($p));

        return response()->json([
            'kpis'            => $kpis,
            'grafico_estados' => $graficoEstados,
            'grafico_tipos'   => $graficoTipos,
            'propiedades'     => [
                'data'          => $items,
                'current_page'  => $paginated->currentPage(),
                'last_page'     => $paginated->lastPage(),
                'total'         => $paginated->total(),
                'per_page'      => $paginated->perPage(),
            ],
        ]);
    }

    public function inventarioPropiedadesExcel(Request $request)
    {
        $kpis  = $this->kpisInventario($request);
        $rows  = $this->baseInventarioQuery($request)
            ->orderByRaw("FIELD(estado,'Disponible','Reservado','Vendido')")
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->get()
            ->map(fn($p) => $this->mapearPropiedad($p))
            ->toArray();

        $fecha = now()->format('d/m/Y');
        return Excel::download(
            new ReporteInventarioPropiedadesExport($rows, $kpis, $fecha),
            'inventario_propiedades_' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function inventarioPropiedadesPdf(Request $request)
    {
        $kpis  = $this->kpisInventario($request);
        $rows  = $this->baseInventarioQuery($request)
            ->orderByRaw("FIELD(estado,'Disponible','Reservado','Vendido')")
            ->orderBy('tipo')
            ->orderBy('codigo')
            ->get()
            ->map(fn($p) => $this->mapearPropiedad($p))
            ->toArray();

        $empresa  = MiEmpresa::first();
        $html     = $this->buildInventarioPdfHtml($rows, $kpis, $empresa);

        $mpdf = new Mpdf(['orientation' => 'L', 'margin_top' => 10, 'margin_bottom' => 10]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('inventario.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="inventario_propiedades_' . now()->format('Ymd') . '.pdf"',
        ]);
    }

    private function buildInventarioPdfHtml(array $rows, array $kpis, $empresa): string
    {
        $nombre    = $empresa?->nombre ?? 'Mi Empresa';
        $generado  = now()->format('d/m/Y H:i');

        $filas = '';
        $estadoColors = ['Disponible' => '#22c55e', 'Vendido' => '#3b82f6', 'Reservado' => '#f59e0b'];
        foreach ($rows as $r) {
            $color = $estadoColors[$r['estado']] ?? '#6b7280';
            $filas .= '<tr>
                <td>' . htmlspecialchars($r['codigo'] ?? '-') . '</td>
                <td>' . htmlspecialchars($r['tipo']) . '</td>
                <td>' . htmlspecialchars($r['sector_urbano'] ?? '-') . '</td>
                <td>' . htmlspecialchars($r['distrito'] ?? '-') . '</td>
                <td style="text-align:right;">' . number_format($r['superficie_m2'], 2) . '</td>
                <td style="text-align:right;">' . ($r['superficie_construida_m2'] ? number_format($r['superficie_construida_m2'], 2) : '-') . '</td>
                <td style="text-align:right;">' . number_format($r['precio_venta'], 2) . ' ' . $r['moneda'] . '</td>
                <td style="text-align:center;color:' . $color . ';font-weight:bold;">' . $r['estado'] . '</td>
            </tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 9px; color: #1f2937; }
  .header { text-align:center; margin-bottom:10px; }
  .header h2 { margin:0; font-size:14px; color:#0b2545; }
  .header p  { margin:2px 0; font-size:8px; color:#6b7280; }
  .kpis { display:flex; gap:8px; margin-bottom:10px; }
  .kpi  { flex:1; background:#f8fafc; border:1px solid #e2e8f0; border-radius:5px; padding:6px 10px; text-align:center; }
  .kpi .val { font-size:14px; font-weight:bold; color:#0b2545; }
  .kpi .lbl { font-size:7px; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
  table { width:100%; border-collapse:collapse; }
  th    { background:#0b2545; color:#fff; padding:5px 6px; text-align:left; font-size:8px; }
  td    { padding:4px 6px; border-bottom:1px solid #f1f5f9; font-size:8.5px; }
  tr:nth-child(even) td { background:#f8fafc; }
  .footer { text-align:right; font-size:7px; color:#9ca3af; margin-top:8px; }
</style></head><body>
<div class="header">
  <h2>' . htmlspecialchars($nombre) . ' — Inventario de Propiedades</h2>
  <p>Generado el: ' . $generado . ' &nbsp;|&nbsp; Total: ' . $kpis['total'] . ' propiedades activas</p>
</div>
<table class="kpis" style="width:100%;margin-bottom:10px;border-collapse:separate;border-spacing:4px;">
  <tr>
    <td style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:13px;font-weight:bold;color:#16a34a;">' . $kpis['disponibles'] . '</div>
      <div style="font-size:7px;color:#64748b;">DISPONIBLES</div>
    </td>
    <td style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:13px;font-weight:bold;color:#2563eb;">' . $kpis['vendidas'] . '</div>
      <div style="font-size:7px;color:#64748b;">VENDIDAS</div>
    </td>
    <td style="background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:13px;font-weight:bold;color:#d97706;">' . $kpis['reservadas'] . '</div>
      <div style="font-size:7px;color:#64748b;">RESERVADAS</div>
    </td>
    <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:11px;font-weight:bold;color:#0b2545;">USD ' . number_format($kpis['valor_disponible_usd'], 0, '.', ',') . '</div>
      <div style="font-size:7px;color:#64748b;">VALOR DISP. (USD)</div>
    </td>
    <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:11px;font-weight:bold;color:#0b2545;">Bs. ' . number_format($kpis['valor_disponible_bob'], 0, '.', ',') . '</div>
      <div style="font-size:7px;color:#64748b;">VALOR DISP. (Bs.)</div>
    </td>
    <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;padding:6px 10px;text-align:center;">
      <div style="font-size:11px;font-weight:bold;color:#0b2545;">' . number_format($kpis['superficie_promedio'], 1) . ' m²</div>
      <div style="font-size:7px;color:#64748b;">SUP. PROMEDIO</div>
    </td>
  </tr>
</table>
<table>
  <thead><tr>
    <th>Código</th><th>Tipo</th><th>Sector Urbano</th><th>Distrito</th>
    <th style="text-align:right;">Sup. (m²)</th>
    <th style="text-align:right;">Const. (m²)</th>
    <th style="text-align:right;">Precio Venta</th>
    <th style="text-align:center;">Estado</th>
  </tr></thead>
  <tbody>' . $filas . '</tbody>
</table>
<div class="footer">Generado el: ' . $generado . ' &nbsp;|&nbsp; Total: ' . count($rows) . ' propiedades</div>
</body></html>';
    }
}
