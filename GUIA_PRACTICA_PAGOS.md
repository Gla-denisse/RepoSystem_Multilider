# Guía Práctica de Uso del Sistema de Pagos

## Instalación y Migración

```bash
# Ejecutar la migración
php artisan migrate

# Verificar que la tabla se creó correctamente
php artisan tinker
>>> DB::table('pagos')->count();
```

---

## Ejemplos de Uso con cURL

### 1. Registrar una Venta al Contado y su Pago

```bash
# Primero, crear la venta al contado
curl -X POST http://localhost/api/ventas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "asesor_id": 1,
    "cliente_id": 1,
    "propiedad_id": 1,
    "fecha": "2026-05-06",
    "monto_total": 50000,
    "tipo_venta": "CONTADO",
    "descuento": 0,
    "monto_liquido": 50000
  }'

# Respuesta esperada (nota el id de la venta)
{
  "message": "Venta registrada con éxito",
  "data": {
    "id": 1,
    "tipo_venta": "CONTADO",
    "monto_liquido": 50000,
    ...
  }
}

# Ahora registrar el pago
curl -X POST http://localhost/api/pagos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "nota_venta_id": 1,
    "concepto_pago": "VENTA_CONTADO",
    "fecha_pago": "2026-05-06",
    "monto": 50000,
    "observaciones": "Pago completo realizado en efectivo"
  }'

# Ver resumen de pagos
curl -X GET http://localhost/api/pagos/venta/1/resumen \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Venta al Crédito con Cuota Inicial

```bash
# Crear venta al crédito
curl -X POST http://localhost/api/ventas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "asesor_id": 1,
    "cliente_id": 2,
    "propiedad_id": 2,
    "fecha": "2026-05-06",
    "monto_total": 100000,
    "cuota_inicial": 20000,
    "saldo_credito": 80000,
    "tipo_venta": "CREDITO",
    "numero_cuotas": 24,
    "tasa_interes": 5.5,
    "fecha_inicio_pago": "2026-06-06"
  }'

# Respuesta: venta creada con id = 2, plan de pagos generado automáticamente

# Registrar cuota inicial
curl -X POST http://localhost/api/pagos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "nota_venta_id": 2,
    "concepto_pago": "CUOTA_INICIAL",
    "fecha_pago": "2026-05-06",
    "monto": 20000,
    "observaciones": "Cuota inicial pagada en cheque"
  }'

# Ver detalle de cuotas
curl -X GET http://localhost/api/ventas/2 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Respuesta incluirá: planPago.cuotas con todas las 24 cuotas
```

### 3. Pagar Cuotas Mensualmente

```bash
# Obtener cuota 1 (del endpoint anterior)
# Supongamos cuota_id = 1

curl -X POST http://localhost/api/pagos \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "nota_venta_id": 2,
    "cuota_id": 1,
    "concepto_pago": "CUOTA",
    "fecha_pago": "2026-06-06",
    "monto": 3500.50,
    "observaciones": "Pago de cuota 1"
  }'

# El sistema automáticamente actualizará la cuota a 'Pagada' si monto = monto_cuota
```

### 4. Reportes y Estadísticas

```bash
# Reporte de pagos en período
curl -X POST http://localhost/api/pagos/reportes/periodo \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "fecha_inicio": "2026-05-01",
    "fecha_fin": "2026-05-31"
  }'

# Resumen de pagos de un cliente
curl -X GET http://localhost/api/pagos/cliente/2/resumen \
  -H "Authorization: Bearer YOUR_TOKEN"

# Listar pagos con filtros
curl -X GET "http://localhost/api/pagos?cliente_id=2&estado=Registrado&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Ejemplos en Código PHP/Laravel

### En un Controlador

```php
<?php

namespace App\Http\Controllers;

use App\Services\PagoService;
use App\Models\NotaVenta;

class MiControlador extends Controller
{
    public function procesarPago()
    {
        try {
            // Registrar pago de venta al contado
            $pago = PagoService::registrarPagoContado(
                notaVentaId: 1,
                monto: 50000,
                fechaPago: now()->toDateString(),
                observaciones: 'Pago realizado en sucursal'
            );

            // Obtener resumen
            $totalPagado = PagoService::obtenerTotalPagado(1);
            $pendiente = PagoService::obtenerPendiente(1);
            $porcentaje = PagoService::obtenerPorcentajePago(1);

            return response()->json([
                'success' => true,
                'pago' => $pago,
                'resumen' => [
                    'total_pagado' => $totalPagado,
                    'pendiente' => $pendiente,
                    'porcentaje' => $porcentaje
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function registrarCuotaInicial()
    {
        $pago = PagoService::registrarCuotaInicial(
            notaVentaId: 2,
            monto: 20000,
            fechaPago: '2026-05-06',
            observaciones: 'Cuota inicial pagada'
        );

        return response()->json($pago);
    }

    public function registrarPagoCuota()
    {
        $pago = PagoService::registrarPagoCuota(
            cuotaId: 1,
            monto: 3500.50,
            fechaPago: '2026-06-06'
        );

        return response()->json($pago);
    }

    public function obtenerResumen($notaVentaId)
    {
        $notaVenta = NotaVenta::findOrFail($notaVentaId);
        $totalPagado = PagoService::obtenerTotalPagado($notaVentaId);
        $pendiente = PagoService::obtenerPendiente($notaVentaId);
        $porcentaje = PagoService::obtenerPorcentajePago($notaVentaId);

        if ($notaVenta->planPago) {
            $resumenCuotas = PagoService::obtenerResumenCuotas($notaVenta->planPago->id);
        }

        return response()->json([
            'venta' => $notaVenta,
            'total_pagado' => $totalPagado,
            'pendiente' => $pendiente,
            'porcentaje_pagado' => $porcentaje,
            'resumen_cuotas' => $resumenCuotas ?? null
        ]);
    }
}
```

### En una Vista Blade (Ejemplo)

```blade
@foreach($pagos as $pago)
    <tr>
        <td>{{ $pago->id }}</td>
        <td>{{ $pago->notaVenta->cliente->nombre }}</td>
        <td>{{ $pago->concepto_pago }}</td>
        <td>${{ number_format($pago->monto, 2) }}</td>
        <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
        <td>
            @if($pago->estado === 'Registrado')
                <span class="badge bg-success">Registrado</span>
            @elseif($pago->estado === 'Cancelado')
                <span class="badge bg-danger">Cancelado</span>
            @else
                <span class="badge bg-warning">Rechazado</span>
            @endif
        </td>
        <td>
            <a href="/pagos/{{ $pago->id }}" class="btn btn-sm btn-info">Ver</a>
            <button class="btn btn-sm btn-danger" onclick="cancelarPago({{ $pago->id }})">Cancelar</button>
        </td>
    </tr>
@endforeach
```

---

## Casos de Uso Típicos

### Caso 1: Venta de Propiedad al Contado
1. Crear NotaVenta con tipo_venta='CONTADO'
2. Registrar Pago con concepto_pago='VENTA_CONTADO'
3. Consultar resumen en `/api/pagos/venta/{id}/resumen`

### Caso 2: Venta Inmediata al Crédito con Cuota Inicial
1. Crear NotaVenta con tipo_venta='CREDITO'
2. Sistema genera automáticamente 24 cuotas
3. Registrar Pago con concepto_pago='CUOTA_INICIAL'
4. Esperar fecha de primer vencimiento para cobrar cuotas

### Caso 3: Seguimiento de Cuotas
1. Consultar `/api/ventas/{id}` para ver planPago.cuotas
2. Registrar pagos de cuotas a medida que se vencen
3. Sistema actualiza automáticamente estado de cuotas
4. Generar reportes con `/api/pagos/reportes/periodo`

### Caso 4: Dashboard de Cobranzas
```bash
# Ver todas las cuotas pendientes
curl -X GET "http://localhost/api/pagos?concepto_pago=CUOTA&estado=Registrado" \
  -H "Authorization: Bearer TOKEN"

# Ver resumen de cliente moroso
curl -X GET "http://localhost/api/pagos/cliente/5/resumen" \
  -H "Authorization: Bearer TOKEN"

# Generar reporte mensual
curl -X POST http://localhost/api/pagos/reportes/periodo \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"fecha_inicio": "2026-05-01", "fecha_fin": "2026-05-31"}'
```

---

## Estructura Recomendada para Frontend

### Componente de Registro de Pagos

```javascript
// Vue.js / React example
const registrarPago = async (formData) => {
  const response = await fetch('/api/pagos', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      nota_venta_id: formData.ventaId,
      cuota_id: formData.cuotaId || null,
      concepto_pago: formData.conceptoPago,
      fecha_pago: formData.fechaPago,
      monto: formData.monto,
      observaciones: formData.observaciones
    })
  });

  return response.json();
};
```

---

## Validaciones y Restricciones

| Restricción | Descripción |
|---|---|
| CUOTA_INICIAL | Solo ventas CREDITO, monto ≤ cuota_inicial |
| VENTA_CONTADO | Solo ventas CONTADO, monto ≤ monto_liquido |
| CUOTA | Requiere cuota_id, cuota no pagada, monto ≤ monto_cuota |
| Cancelación | Solo de pagos Registrados, revierte estado de cuota |

---

## Troubleshooting

**Error: "Esta propiedad ya fue vendida"**
- La propiedad no puede ser vendida dos veces
- Solución: Anular la venta anterior con `PUT /api/ventas/{id}/anular`

**Error: "No se puede registrar cuota inicial en una venta al contado"**
- Verificar que `tipo_venta = 'CREDITO'`

**Error: "El monto no puede exceder la cuota"**
- Verificar que el monto registrado no sea mayor al de la cuota
- Las cuotas incluyen capital e interés

**La cuota no cambia a Pagada**
- Verificar que el monto del pago sea igual al `monto_cuota`
- Pagos parciales no marcan como pagada automáticamente

---

## Performance Tips

1. Usar índices en campos: `nota_venta_id`, `cuota_id`, `fecha_pago`, `estado`
2. Cargar relaciones con `with()`: `.with(['notaVenta.cliente', 'cuota'])`
3. Paginar resultados: usar `per_page` en listados
4. Cache de reportes mensuales para consultas frecuentes
