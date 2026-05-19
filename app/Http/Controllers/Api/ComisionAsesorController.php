<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asesor;
use App\Models\Egreso;
use App\Models\MiEmpresa;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class ComisionAsesorController extends Controller
{
    // GET /comisiones-asesores
    public function index(Request $request)
    {
        $query = Asesor::query()
            ->whereHas('egresos', fn($q) => $q->where('categoria', 'COMISION_ASESOR'))
            ->withCount([
                'egresos as comisiones_pendientes' => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PENDIENTE'),
                'egresos as comisiones_pagadas'    => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PAGADO'),
            ])
            ->withSum(
                ['egresos as total_deuda_bs' => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PENDIENTE')->where('moneda', 'Bs')],
                'monto'
            )
            ->withSum(
                ['egresos as total_deuda_usd' => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PENDIENTE')->where('moneda', '$')],
                'monto'
            )
            ->withSum(
                ['egresos as total_pagado_bs' => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PAGADO')->where('moneda', 'Bs')],
                'monto'
            )
            ->withSum(
                ['egresos as total_pagado_usd' => fn($q) => $q->where('categoria', 'COMISION_ASESOR')->where('estado', 'PAGADO')->where('moneda', '$')],
                'monto'
            )
            ->orderByDesc('comisiones_pendientes')
            ->orderByDesc('total_deuda_bs');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'like', "%{$search}%")
                  ->orWhere('correo', 'like', "%{$search}%");
            });
        }

        $result = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    // GET /comisiones-asesores/{asesorId}/impagas
    public function impagas(Request $request, $asesorId)
    {
        $asesor = Asesor::findOrFail($asesorId);

        $query = Egreso::where('asesor_id', $asesorId)
            ->where('categoria', 'COMISION_ASESOR')
            ->where('estado', 'PENDIENTE')
            ->with([
                'notaVenta.propiedad',
                'notaVenta.cliente',
            ]);

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        $totalQuery = clone $query;
        $totales = [
            'bs'  => $totalQuery->where('moneda', 'Bs')->sum('monto'),
            'usd' => (clone $query)->where('moneda', '$')->sum('monto'),
        ];

        $result = $query->orderByDesc('fecha')->orderByDesc('id')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'asesor' => $asesor,
            'data'   => $result->items(),
            'meta'   => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
            'totales' => $totales,
        ]);
    }

    // GET /comisiones-asesores/{asesorId}/pagadas
    public function pagadas(Request $request, $asesorId)
    {
        $asesor = Asesor::findOrFail($asesorId);

        $query = Egreso::where('asesor_id', $asesorId)
            ->where('categoria', 'COMISION_ASESOR')
            ->where('estado', 'PAGADO')
            ->with([
                'notaVenta.propiedad',
                'notaVenta.cliente',
                'cuentaBancaria',
                'registradoPor',
            ]);

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        $totales = [
            'bs'  => (clone $query)->where('moneda', 'Bs')->sum('monto'),
            'usd' => (clone $query)->where('moneda', '$')->sum('monto'),
        ];

        $result = $query->orderByDesc('fecha')->orderByDesc('id')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'asesor' => $asesor,
            'data'   => $result->items(),
            'meta'   => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
            'totales' => $totales,
        ]);
    }

    // GET /comisiones-asesores/egreso/{egresoId}/comprobante
    public function comprobante($egresoId)
    {
        $egreso = Egreso::with([
            'asesor',
            'notaVenta.propiedad',
            'notaVenta.cliente',
            'cuentaBancaria',
            'registradoPor',
        ])->findOrFail($egresoId);

        if ($egreso->estado !== 'PAGADO') {
            return response()->json(['message' => 'Solo se puede generar comprobante de comisiones pagadas.'], 422);
        }

        $empresa  = MiEmpresa::first();
        $html     = $this->buildComprobanteHtml($egreso, $empresa);

        $mpdf = new Mpdf([
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'format'        => 'A4',
        ]);

        $mpdf->WriteHTML($html);

        $filename = 'comprobante-comision-' . $egreso->id . '.pdf';

        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildComprobanteHtml(Egreso $egreso, ?MiEmpresa $empresa): string
    {
        $nombreEmpresa = $empresa?->nombre ?? 'Sistema Multilider';
        $telefonoEmpresa = $empresa?->telefono ?? '';
        $emailEmpresa    = $empresa?->email ?? '';
        $direccionEmpresa = $empresa?->direccion ?? '';

        $asesor = $egreso->asesor;
        $venta  = $egreso->notaVenta;
        $propiedad = $venta?->propiedad;
        $cliente   = $venta?->cliente;
        $cuenta    = $egreso->cuentaBancaria;
        $registrador = $egreso->registradoPor;

        $monto    = number_format((float)$egreso->monto, 2);
        $moneda   = $egreso->moneda;
        $fecha    = $egreso->fecha?->format('d/m/Y');
        $fechaReg = now()->format('d/m/Y H:i:s');

        $ventaInfo = $venta ? '#' . $venta->id : 'N/A';
        $propInfo  = $propiedad ? $propiedad->nombre ?? ('Propiedad #' . $propiedad->id) : 'N/A';
        $clienteInfo = $cliente ? ($cliente->nombre ?? '') . ' ' . ($cliente->apellido ?? '') : 'N/A';
        $clienteInfo = trim($clienteInfo) ?: 'N/A';
        $montoVenta  = $venta ? $moneda . ' ' . number_format((float)$venta->monto_total, 2) : 'N/A';
        $comisionPct = $asesor?->porcentaje_comision ? $asesor->porcentaje_comision . '%' : 'N/A';
        $cuentaInfo  = $cuenta ? ($cuenta->banco ?? '') . ' - ' . ($cuenta->numero_cuenta ?? '') : 'No registrada';
        $registradorInfo = $registrador?->nombre ?? 'Sistema';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #2c3e50; }
  .header { text-align: center; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 2px solid #2c3e50; }
  .header h1 { font-size: 20px; font-weight: bold; color: #1a252f; margin-bottom: 3px; }
  .header .sub { font-size: 10px; color: #666; }
  .doc-title { background-color: #2c3e50; color: #fff; text-align: center; padding: 9px 0; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin-bottom: 18px; }
  .doc-number { text-align: right; font-size: 10px; color: #888; margin-bottom: 10px; }
  .amount-box { border: 2px solid #27ae60; border-radius: 6px; text-align: center; padding: 12px; margin: 14px 0; background: #f0faf4; }
  .amount-box .label { font-size: 10px; text-transform: uppercase; color: #555; margin-bottom: 4px; }
  .amount-box .amount { font-size: 26px; font-weight: bold; color: #1e8449; }
  .amount-box .badge { display: inline-block; background: #27ae60; color: #fff; padding: 2px 12px; border-radius: 12px; font-size: 10px; margin-top: 4px; font-weight: bold; }
  .section { margin-bottom: 14px; }
  .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; letter-spacing: 0.5px; }
  table.data { width: 100%; border-collapse: collapse; }
  table.data td { padding: 5px 6px; vertical-align: top; }
  table.data td.lbl { font-weight: bold; color: #555; width: 40%; }
  table.data td.val { color: #222; }
  .footer { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 8px; font-size: 9px; color: #999; text-align: center; }
  .two-col { display: table; width: 100%; }
  .col-half { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
  .col-half:last-child { padding-right: 0; padding-left: 10px; }
</style>
</head>
<body>

<div class="header">
  <h1>{$nombreEmpresa}</h1>
  <div class="sub">{$direccionEmpresa} &nbsp;|&nbsp; Tel: {$telefonoEmpresa} &nbsp;|&nbsp; {$emailEmpresa}</div>
</div>

<div class="doc-title">COMPROBANTE DE PAGO DE COMISIÓN</div>

<div class="doc-number">N° Documento: COMP-{$egreso->id} &nbsp;&nbsp;|&nbsp;&nbsp; Generado: {$fechaReg}</div>

<div class="amount-box">
  <div class="label">Monto Pagado</div>
  <div class="amount">{$moneda} {$monto}</div>
  <div class="badge">PAGADO</div>
</div>

<div class="two-col">
  <div class="col-half">
    <div class="section">
      <div class="section-title">Datos del Asesor</div>
      <table class="data">
        <tr><td class="lbl">Nombre:</td><td class="val">{$asesor->nombre_completo}</td></tr>
        <tr><td class="lbl">Correo:</td><td class="val">{$asesor->correo}</td></tr>
        <tr><td class="lbl">Teléfono:</td><td class="val">{$asesor->telefono}</td></tr>
        <tr><td class="lbl">% Comisión:</td><td class="val">{$comisionPct}</td></tr>
      </table>
    </div>
  </div>
  <div class="col-half">
    <div class="section">
      <div class="section-title">Datos del Pago</div>
      <table class="data">
        <tr><td class="lbl">Fecha de Pago:</td><td class="val">{$fecha}</td></tr>
        <tr><td class="lbl">Cuenta Bancaria:</td><td class="val">{$cuentaInfo}</td></tr>
        <tr><td class="lbl">Comprobante:</td><td class="val">{$egreso->comprobante}</td></tr>
        <tr><td class="lbl">Registrado por:</td><td class="val">{$registradorInfo}</td></tr>
      </table>
    </div>
  </div>
</div>

<div class="section">
  <div class="section-title">Datos de la Venta</div>
  <table class="data">
    <tr>
      <td class="lbl">N° Venta:</td><td class="val">{$ventaInfo}</td>
      <td class="lbl">Propiedad:</td><td class="val">{$propInfo}</td>
    </tr>
    <tr>
      <td class="lbl">Cliente:</td><td class="val">{$clienteInfo}</td>
      <td class="lbl">Monto Total Venta:</td><td class="val">{$montoVenta}</td>
    </tr>
  </table>
</div>

<div class="section">
  <div class="section-title">Concepto</div>
  <table class="data">
    <tr><td class="lbl">Descripción:</td><td class="val">{$egreso->concepto}</td></tr>
    <tr><td class="lbl">Observaciones:</td><td class="val">{$egreso->observaciones}</td></tr>
  </table>
</div>

<div class="footer">
  {$nombreEmpresa} &nbsp;|&nbsp; Este documento es un comprobante oficial de pago de comisión. &nbsp;|&nbsp; Generado el {$fechaReg}
</div>

</body>
</html>
HTML;
    }
}
