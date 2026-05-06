# Documentación API de Pagos

## Tabla PAGO - Estructura

```sql
Tabla: pagos
├── id (PK)
├── nota_venta_id (FK → notas_ventas)
├── cuota_id (FK → cuotas, nullable)
├── concepto_pago (ENUM: CUOTA_INICIAL, CUOTA, VENTA_CONTADO, OTRO)
├── fecha_pago (DATE)
├── monto (DECIMAL 12,2)
├── estado (VARCHAR 50: Registrado, Cancelado, Rechazado)
├── observaciones (TEXT)
├── timestamps (created_at, updated_at)
```

## Relaciones

- **Pago → NotaVenta** (belongsTo): Un pago pertenece a una nota de venta
- **Pago → Cuota** (belongsTo, nullable): Un pago puede estar asociado a una cuota
- **NotaVenta → Pagos** (hasMany): Una venta puede tener múltiples pagos
- **Cuota → Pagos** (hasMany): Una cuota puede tener múltiples pagos parciales

## Endpoints API

### 1. Listar Pagos
```http
GET /api/pagos
```

**Parámetros (Query):**
- `fecha_inicio` (date) - Filtro por fecha inicial
- `fecha_fin` (date) - Filtro por fecha final
- `nota_venta_id` (integer) - Filtro por nota de venta
- `cuota_id` (integer) - Filtro por cuota
- `concepto_pago` (enum) - Filtro por concepto
- `estado` (enum) - Filtro por estado
- `cliente_id` (integer) - Filtro por cliente (indirecto)
- `per_page` (integer) - Registros por página (default: 10)

**Ejemplo:**
```bash
curl -X GET "http://localhost/api/pagos?fecha_inicio=2026-05-01&fecha_fin=2026-05-06&per_page=20" \
  -H "Authorization: Bearer TOKEN"
```

**Respuesta (200):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "nota_venta_id": 5,
      "cuota_id": null,
      "concepto_pago": "VENTA_CONTADO",
      "fecha_pago": "2026-05-06",
      "monto": "50000.00",
      "estado": "Registrado",
      "observaciones": null,
      "created_at": "2026-05-06T10:30:00",
      "updated_at": "2026-05-06T10:30:00",
      "nota_venta": {
        "id": 5,
        "cliente": {...}
      }
    }
  ],
  "per_page": 10,
  "total": 1
}
```

---

### 2. Registrar Nuevo Pago
```http
POST /api/pagos
```

**Datos (JSON):**
```json
{
  "nota_venta_id": 5,
  "cuota_id": null,
  "concepto_pago": "VENTA_CONTADO",
  "fecha_pago": "2026-05-06",
  "monto": 50000.00,
  "estado": "Registrado",
  "observaciones": "Pago realizado en sucursal principal"
}
```

**Ejemplos por tipo de pago:**

#### Pago de Cuota Inicial (Venta al Crédito)
```json
{
  "nota_venta_id": 1,
  "concepto_pago": "CUOTA_INICIAL",
  "fecha_pago": "2026-05-06",
  "monto": 10000.00
}
```

#### Pago Completo (Venta al Contado)
```json
{
  "nota_venta_id": 2,
  "concepto_pago": "VENTA_CONTADO",
  "fecha_pago": "2026-05-06",
  "monto": 50000.00
}
```

#### Pago de Cuota (Venta al Crédito)
```json
{
  "nota_venta_id": 1,
  "cuota_id": 3,
  "concepto_pago": "CUOTA",
  "fecha_pago": "2026-05-06",
  "monto": 2500.00
}
```

**Respuesta (201):**
```json
{
  "message": "Pago registrado con éxito",
  "data": {
    "id": 1,
    "nota_venta_id": 5,
    "cuota_id": null,
    "concepto_pago": "VENTA_CONTADO",
    "fecha_pago": "2026-05-06",
    "monto": "50000.00",
    "estado": "Registrado",
    "observaciones": null,
    "created_at": "2026-05-06T10:30:00",
    "updated_at": "2026-05-06T10:30:00",
    "nota_venta": {...},
    "cuota": null
  }
}
```

---

### 3. Ver Detalle de Pago
```http
GET /api/pagos/:id
```

**Respuesta (200):**
```json
{
  "id": 1,
  "nota_venta_id": 5,
  "cuota_id": null,
  "concepto_pago": "VENTA_CONTADO",
  "fecha_pago": "2026-05-06",
  "monto": "50000.00",
  "estado": "Registrado",
  "observaciones": null,
  "created_at": "2026-05-06T10:30:00",
  "updated_at": "2026-05-06T10:30:00",
  "nota_venta": {
    "id": 5,
    "cliente": {...},
    "asesor": {...},
    "propiedad": {...}
  },
  "cuota": null
}
```

---

### 4. Actualizar Pago
```http
PUT /api/pagos/:id
```

**Datos (JSON):**
```json
{
  "estado": "Cancelado",
  "observaciones": "Cancelado por solicitud del cliente"
}
```

**Respuesta (200):**
```json
{
  "message": "Pago actualizado con éxito",
  "data": {...}
}
```

---

### 5. Eliminar Pago
```http
DELETE /api/pagos/:id
```

**Respuesta (200):**
```json
{
  "message": "Pago eliminado con éxito"
}
```

---

### 6. Obtener Pagos de una Venta (Resumen)
```http
GET /api/pagos/venta/:notaVentaId/resumen
```

**Respuesta (200):**
```json
{
  "nota_venta": {
    "id": 5,
    "tipo_venta": "CONTADO",
    "monto_liquido": 50000.00,
    "estado": "Completada"
  },
  "pagos": [
    {
      "id": 1,
      "concepto_pago": "VENTA_CONTADO",
      "fecha_pago": "2026-05-06",
      "monto": "50000.00"
    }
  ],
  "resumen": {
    "total_pagado": 50000.00,
    "total_pendiente": 0.00,
    "porcentaje_pagado": 100.0
  }
}
```

---

### 7. Resumen de Pagos por Cliente
```http
GET /api/pagos/cliente/:clienteId/resumen
```

**Respuesta (200):**
```json
{
  "cliente_id": 3,
  "total_pagado": 150000.00,
  "total_ventas": 4,
  "ventas_credito": 2,
  "pagos": [...]
}
```

---

### 8. Cancelar Pago
```http
PUT /api/pagos/:id/cancelar
```

**Datos (JSON, opcional):**
```json
{
  "observaciones": "Cancelado por error en registro"
}
```

**Respuesta (200):**
```json
{
  "message": "Pago cancelado con éxito",
  "data": {...}
}
```

---

### 9. Reporte de Pagos por Período
```http
POST /api/pagos/reportes/periodo
```

**Datos (JSON):**
```json
{
  "fecha_inicio": "2026-05-01",
  "fecha_fin": "2026-05-31"
}
```

**Respuesta (200):**
```json
{
  "periodo": {
    "inicio": "2026-05-01",
    "fin": "2026-05-31"
  },
  "resumen": {
    "total_periodo": 350000.00,
    "total_contado": 100000.00,
    "total_cuota_inicial": 50000.00,
    "total_cuotas": 200000.00,
    "cantidad_pagos": 15,
    "pagos_registrados": 14,
    "pagos_cancelados": 1
  },
  "pagos": [...]
}
```

---

## Servicio PagoService (PHP)

Uso interno en controladores o modelos:

```php
use App\Services\PagoService;

// Registrar cuota inicial
PagoService::registrarCuotaInicial(
    notaVentaId: 1,
    monto: 10000.00,
    fechaPago: '2026-05-06',
    observaciones: 'Cuota inicial registrada'
);

// Registrar pago al contado
PagoService::registrarPagoContado(
    notaVentaId: 2,
    monto: 50000.00,
    fechaPago: '2026-05-06'
);

// Registrar pago de cuota
PagoService::registrarPagoCuota(
    cuotaId: 5,
    monto: 2500.00,
    fechaPago: '2026-05-06'
);

// Obtener total pagado
$totalPagado = PagoService::obtenerTotalPagado(notaVentaId: 1);

// Obtener pendiente
$pendiente = PagoService::obtenerPendiente(notaVentaId: 1);

// Obtener porcentaje
$porcentaje = PagoService::obtenerPorcentajePago(notaVentaId: 1);

// Resumen de cuotas
$resumen = PagoService::obtenerResumenCuotas(planPagoId: 1);

// Cancelar pago
PagoService::cancelarPago(pagoId: 1, observaciones: 'Cancelado');
```

---

## Validaciones

- Cuota inicial: Solo en ventas al crédito, monto ≤ cuota_inicial
- Venta al contado: Solo en ventas al contado, monto ≤ monto_liquido
- Pago de cuota: Requiere cuota_id, cuota no debe estar pagada, monto ≤ monto_cuota
- Estados válidos: 'Registrado', 'Cancelado', 'Rechazado'

---

## Flujo Típico

### Venta al Contado
1. Crear NotaVenta con `tipo_venta = 'CONTADO'`
2. Registrar pago con `concepto_pago = 'VENTA_CONTADO'`
3. Consultar resumen con `/api/pagos/venta/{id}/resumen`

### Venta al Crédito
1. Crear NotaVenta con `tipo_venta = 'CREDITO'`
2. Plan de pagos se genera automáticamente
3. Registrar cuota inicial con `concepto_pago = 'CUOTA_INICIAL'`
4. Registrar pagos de cuotas con `concepto_pago = 'CUOTA'` y `cuota_id`
5. Sistema actualiza automáticamente el estado de la cuota a 'Pagada'
