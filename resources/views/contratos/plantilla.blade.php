<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10.5pt;
    line-height: 1.65;
    color: #111;
    margin: 0;
    padding: 0;
  }
  .encabezado {
    text-align: center;
    margin-bottom: 22px;
  }
  .encabezado p {
    margin: 3px 0;
  }
  h1 {
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 18px 0 6px 0;
    border-bottom: 1px solid #555;
    padding-bottom: 3px;
  }
  h2 {
    font-size: 10.5pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 12px 0 4px 0;
  }
  p {
    margin: 5px 0;
    text-align: justify;
  }
  .clausula {
    margin-bottom: 14px;
  }
  .firma-bloque {
    margin-top: 50px;
  }
  .firma-tabla {
    width: 100%;
  }
  .firma-tabla td {
    width: 50%;
    vertical-align: top;
    padding-top: 10px;
  }
  .firma-linea {
    border-top: 1px solid #333;
    width: 200px;
    margin-top: 35px;
    margin-bottom: 4px;
  }
  .dato-campo {
    font-weight: bold;
    text-decoration: underline;
  }
  .separador {
    border: none;
    border-top: 1px dashed #aaa;
    margin: 16px 0;
  }
  .monto-destacado {
    font-size: 11pt;
    font-weight: bold;
  }
  .logo-header {
    margin-bottom: 10px;
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
</style>
</head>
<body>

<!-- ENCABEZADO -->
<div class="encabezado">
  <p class="logo-header">MULTILIDER BIENES RAÍCES</p>
  <p><strong>MINUTA DE COMPRA Y VENTA DE INMUEBLE</strong></p>
  <p>Código de Contrato: <span class="dato-campo">{{ $contrato->codigo_contrato }}</span></p>
  <p>Fecha de Emisión: <span class="dato-campo">{{ \Carbon\Carbon::parse($contrato->fecha_emision)->format('d \d\e F \d\e Y') }}</span></p>
</div>

<p style="text-align:justify; margin-bottom:16px;">
  <strong>SEÑOR NOTARIO DE FE PÚBLICA</strong><br>
  Entre las escrituras a su cargo, sírvase incorporar la presente minuta de compra y venta de inmueble,
  suscrita de acuerdo con las siguientes cláusulas:
</p>

<!-- PRIMERA: CONTRATANTES -->
<h1>PRIMERA.- (CONTRATANTES)</h1>

<div class="clausula">
  <h2>1.1 VENDEDORES</h2>
  <p>Son parte del presente contrato los señores:</p>

  @forelse($propietarios as $p)
  <p>
    <span class="dato-campo">{{ strtoupper($p->nombre_completo) }}</span>,
    mayor de edad, con Cédula de Identidad N°
    <span class="dato-campo">{{ $p->ci }}{{ $p->lugar_expedicion ? ' ' . strtoupper($p->lugar_expedicion) : '' }}</span>,
    @if($p->tipo === 'empresa')
      representante de la empresa <span class="dato-campo">{{ strtoupper($p->nombre_empresa ?? '') }}</span>,
    @endif
    domiciliado en <span class="dato-campo">{{ $p->direccion ?? '—' }}</span>.
  </p>
  @empty
  <p><em>(Sin propietarios registrados)</em></p>
  @endforelse

  <p>
    @if($propietarios->count() > 1)
      Todos hábiles por derecho, quienes en adelante serán denominados como <strong>LOS VENDEDORES</strong>.
    @else
      Hábil por derecho, quien en adelante será denominado como <strong>EL VENDEDOR</strong>.
    @endif
  </p>
</div>

<div class="clausula">
  <h2>1.2 COMPRADORES</h2>
  <p>Son parte del presente contrato:</p>

  <p>
    <span class="dato-campo">{{ strtoupper($cliente->nombre_completo) }}</span>,
    mayor de edad, con Cédula de Identidad N°
    <span class="dato-campo">{{ $cliente->ci }}{{ $cliente->lugar_expedicion ? ' ' . strtoupper($cliente->lugar_expedicion) : '' }}</span>,
    domiciliado en <span class="dato-campo">{{ $cliente->direccion ?? '—' }}</span>@if($cliente->telefono), con teléfono <span class="dato-campo">{{ $cliente->telefono }}</span>@endif.
  </p>

  <p>Hábil por ley, quien en adelante será denominado como <strong>EL COMPRADOR</strong>.</p>
</div>

<hr class="separador">

<!-- SEGUNDA: ANTECEDENTES -->
<h1>SEGUNDA.- (ANTECEDENTES Y DERECHO PROPIETARIO)</h1>

<div class="clausula">
  <p>
    @if($propietarios->count() > 1) LOS VENDEDORES declaran @else EL VENDEDOR declara @endif
    ser
    @if($propietarios->count() > 1) legítimos propietarios @else legítimo propietario @endif
    del inmueble tipo <span class="dato-campo">{{ strtoupper($propiedad->tipo ?? '—') }}</span> ubicado en:
  </p>

  <p>
    &bull; <strong>Zona / Sector:</strong> <span class="dato-campo">{{ $sector?->nombre ?? '—' }}</span><br>
    @if($sector?->uv)
    &bull; <strong>Unidad Vecinal (UV):</strong> <span class="dato-campo">{{ $sector->uv }}</span><br>
    @endif
    @if($sector?->manzano)
    &bull; <strong>Manzano:</strong> <span class="dato-campo">{{ $sector->manzano }}</span><br>
    @endif
    @if($propiedad->nro_lote)
    &bull; <strong>Lote N°:</strong> <span class="dato-campo">{{ $propiedad->nro_lote }}</span><br>
    @endif
    &bull; <strong>Distrito:</strong> <span class="dato-campo">{{ $distrito?->nombre ?? '—' }}</span><br>
    &bull; <strong>Ciudad / Departamento:</strong> <span class="dato-campo">{{ $ciudad?->nombre ?? '—' }}</span>
  </p>

  @if($propiedad->direccion)
  <p>&bull; <strong>Dirección:</strong> <span class="dato-campo">{{ $propiedad->direccion }}</span></p>
  @endif

  <p>
    Con una superficie de <span class="dato-campo">{{ $propiedad->superficie_m2 ?? '—' }} m²</span>
    @if($propiedad->superficie_construida_m2)
      y una superficie construida de <span class="dato-campo">{{ $propiedad->superficie_construida_m2 }} m²</span>
    @endif
    (Frente: <span class="dato-campo">{{ $propiedad->frente_mts ?? '—' }} mts</span>,
     Fondo: <span class="dato-campo">{{ $propiedad->fondo_mts ?? '—' }} mts</span>).
  </p>
</div>

<hr class="separador">

<!-- TERCERA: OBJETO Y PRECIO -->
<h1>TERCERA.- (OBJETO Y PRECIO)</h1>

<div class="clausula">
  <p>
    @if($propietarios->count() > 1) LOS VENDEDORES transfieren @else EL VENDEDOR transfiere @endif
    en calidad de venta real y definitiva el inmueble anteriormente descrito a favor de EL COMPRADOR
    por la suma libremente convenida de:
  </p>

  <p class="monto-destacado" style="margin: 10px 0 4px 0; text-align: center;">
    Bs. {{ number_format($nv->monto_liquido ?? $nv->monto_total, 2, '.', ',') }}
  </p>
  <p style="text-align: center;">
    (<strong>{{ $montoLiteral }} BOLIVIANOS</strong>)
  </p>

  @if($nv->tipo_venta === 'CREDITO' || $nv->tipo_venta === 'CRÉDITO')
  <p>
    La venta se realiza a <strong>CRÉDITO</strong>, con una cuota inicial de
    <span class="dato-campo">Bs. {{ number_format($nv->cuota_inicial ?? 0, 2, '.', ',') }}</span>
    y un saldo de <span class="dato-campo">Bs. {{ number_format($nv->saldo_credito ?? 0, 2, '.', ',') }}</span>
    a ser cancelado conforme al plan de pagos acordado.
  </p>
  @else
  <p>
    La venta se realiza al <strong>CONTADO</strong>.
    @if($propietarios->count() > 1) LOS VENDEDORES declaran @else EL VENDEDOR declara @endif
    recibir la totalidad del dinero a su entera satisfacción en moneda de curso legal.
  </p>
  @endif

  <p>Asimismo, EL COMPRADOR podrá entrar en posesión inmediata del inmueble.</p>
</div>

<hr class="separador">

<!-- CUARTA: EVICCIÓN -->
<h1>CUARTA.- (EVICCIÓN, SANEAMIENTO E IMPUESTOS)</h1>

<div class="clausula">
  <p>
    @if($propietarios->count() > 1) LOS VENDEDORES garantizan @else EL VENDEDOR garantiza @endif
    que el inmueble:
  </p>
  <p>
    &bull; No posee gravámenes hipotecarios.<br>
    &bull; No registra deudas pendientes.<br>
    &bull; Se encuentra libre de litigios.
  </p>
  <p>
    Asimismo, @if($propietarios->count() > 1) LOS VENDEDORES se obligan @else EL VENDEDOR se obliga @endif
    al saneamiento y evicción conforme a ley.
  </p>
  <p>
    También se hace entrega de comprobantes de impuestos al día, documentación legal del inmueble
    y planos complementarios.
  </p>
</div>

<hr class="separador">

<!-- QUINTA: LÍMITES -->
<h1>QUINTA.- (LÍMITES DEL INMUEBLE)</h1>

<div class="clausula">
  <p>El inmueble objeto de la presente transferencia tiene los siguientes límites:</p>
  <p>
    &bull; <strong>NORTE:</strong> <span class="dato-campo">{{ $propiedad->colinda_norte ?? '—' }}</span><br>
    &bull; <strong>SUR:</strong> <span class="dato-campo">{{ $propiedad->colinda_sur ?? '—' }}</span><br>
    &bull; <strong>ESTE:</strong> <span class="dato-campo">{{ $propiedad->colinda_este ?? '—' }}</span><br>
    &bull; <strong>OESTE:</strong> <span class="dato-campo">{{ $propiedad->colinda_oeste ?? '—' }}</span>
  </p>
  <p>
    La transferencia comprende todos los usos, costumbres, mejoras y servidumbres existentes
    sin reserva alguna.
  </p>
</div>

<hr class="separador">

<!-- SEXTA: ACEPTACIÓN -->
<h1>SEXTA.- (ACEPTACIÓN)</h1>

<div class="clausula">
  <p>
    Las partes intervinientes declaran su total conformidad con todas y cada una de las cláusulas
    del presente contrato.
  </p>
  <p>
    Asimismo, acuerdan que en caso de que el presente documento no sea elevado a instrumento público,
    tendrá plena validez como documento privado reconocido conforme a ley.
  </p>
</div>

<hr class="separador">

<!-- LUGAR Y FECHA -->
<p style="margin-top: 20px;">
  <strong>{{ $ciudad?->nombre ?? '—' }}, {{ \Carbon\Carbon::parse($contrato->fecha_emision)->format('d \d\e F \d\e Y') }}</strong>
</p>

<!-- FIRMAS -->
<div class="firma-bloque">
  <table class="firma-tabla">
    <tr>
      <!-- Vendedores -->
      <td>
        @forelse($propietarios as $p)
        <div class="firma-linea"></div>
        <p style="margin:0; font-size:10pt;">{{ strtoupper($p->nombre_completo) }}</p>
        <p style="margin:0; font-size:9pt; color:#444;">C.I. {{ $p->ci }}{{ $p->lugar_expedicion ? ' ' . strtoupper($p->lugar_expedicion) : '' }}</p>
        <p style="margin:2px 0 14px 0; font-size:9pt; color:#666;"><em>VENDEDOR</em></p>
        @empty
        <div class="firma-linea"></div>
        <p style="margin:0; font-size:10pt;">VENDEDOR</p>
        @endforelse
      </td>
      <!-- Comprador -->
      <td style="text-align: right;">
        <div class="firma-linea" style="margin-left: auto;"></div>
        <p style="margin:0; font-size:10pt;">{{ strtoupper($cliente->nombre_completo) }}</p>
        <p style="margin:0; font-size:9pt; color:#444;">C.I. {{ $cliente->ci }}{{ $cliente->lugar_expedicion ? ' ' . strtoupper($cliente->lugar_expedicion) : '' }}</p>
        <p style="margin:2px 0 14px 0; font-size:9pt; color:#666;"><em>COMPRADOR</em></p>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
