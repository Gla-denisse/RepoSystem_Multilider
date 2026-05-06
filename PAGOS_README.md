# Sistema de Gestión de Pagos - README

## 📋 Descripción General

Sistema completo de gestión de pagos para ventas inmobiliarias, soportando:
- **Ventas al Contado**: Registro de pago completo en una transacción
- **Ventas al Crédito**: Gestión de cuota inicial + cuotas mensuales

---

## 📁 Archivos Creados/Modificados

### Base de Datos
- ✅ **Migración**: `2026_05_06_000000_create_pagos_table.php`
  - Tabla `pagos` con relaciones FK a `notas_ventas` y `cuotas`
  - Campos: concepto_pago, fecha_pago, monto, estado, observaciones
  - Índices para optimización

### Modelos Eloquent
- ✅ **Nuevo**: `app/Models/Pago.php`
  - Relaciones: notaVenta(), cuota()
  - Casts para decimales y fechas
  
- ✅ **Actualizado**: `app/Models/NotaVenta.php`
  - Relación: pagos() - hasMany
  
- ✅ **Actualizado**: `app/Models/Cuota.php`
  - Relación: pagos() - hasMany
  - Agregado: casts para decimales

### Controllers
- ✅ **Nuevo**: `app/Http/Controllers/Api/PagoController.php`
  - 9 métodos REST:
    - `index()` - Listar pagos con filtros
    - `store()` - Crear pago
    - `show()` - Ver detalle
    - `update()` - Actualizar
    - `destroy()` - Eliminar
    - `pagosPorVenta()` - Resumen de pagos por venta
    - `resumenPorCliente()` - Resumen por cliente
    - `cancelar()` - Cancelar pago
    - `reportePeriodo()` - Reporte de período

### Servicios
- ✅ **Nuevo**: `app/Services/PagoService.php`
  - 9 métodos auxiliares para uso en controladores/modelos:
    - registrarCuotaInicial()
    - registrarPagoContado()
    - registrarPagoCuota()
    - obtenerTotalPagado()
    - obtenerPendiente()
    - obtenerPorcentajePago()
    - obtenerResumenCuotas()
    - cancelarPago()
    - Y métodos de validación

### Rutas API
- ✅ **Actualizado**: `routes/api.php`
  - Rutas CRUD para pagos
  - Rutas especializadas: resumen, cancelar, reporte

### Documentación
- ✅ **Documentación Completa**: `DOCUMENTACION_PAGOS.md`
  - Estructura de tabla
  - Todos los endpoints con ejemplos
  - Validaciones
  - Flujos típicos

- ✅ **Guía Práctica**: `GUIA_PRACTICA_PAGOS.md`
  - Ejemplos con cURL
  - Código PHP/Laravel
  - Casos de uso
  - Troubleshooting

---

## 🚀 Instalación

### 1. Ejecutar Migración

```bash
php artisan migrate
```

### 2. Verificar Tabla

```bash
php artisan tinker
>>> DB::table('pagos')->count();
>>> 0  // Tabla vacía lista para usar
```

### 3. Limpiar Cache (si es necesario)

```bash
php artisan cache:clear
php artisan config:clear
```

---

## 📊 Estructura de la Tabla PAGO

```sql
CREATE TABLE pagos (
  id BIGINT UNSIGNED PRIMARY KEY,
  nota_venta_id BIGINT UNSIGNED NOT NULL FOREIGN KEY,
  cuota_id BIGINT UNSIGNED NULLABLE FOREIGN KEY,
  concepto_pago ENUM('CUOTA_INICIAL','CUOTA','VENTA_CONTADO','OTRO'),
  fecha_pago DATE NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  estado VARCHAR(50) DEFAULT 'Registrado',
  observaciones TEXT NULLABLE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX(nota_venta_id),
  INDEX(cuota_id),
  INDEX(fecha_pago),
  INDEX(estado)
);
```

---

## 🔗 Relaciones

```
Pago
├── belongsTo(NotaVenta)
└── belongsTo(Cuota)  [nullable]

NotaVenta
└── hasMany(Pago)

Cuota
└── hasMany(Pago)
```

---

## 📡 API Endpoints

### Operaciones CRUD
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/pagos` | Listar pagos con filtros |
| POST | `/api/pagos` | Crear pago |
| GET | `/api/pagos/:id` | Ver detalle |
| PUT | `/api/pagos/:id` | Actualizar |
| DELETE | `/api/pagos/:id` | Eliminar |

### Operaciones Especializadas
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/pagos/venta/:id/resumen` | Resumen de pagos de venta |
| GET | `/api/pagos/cliente/:id/resumen` | Resumen por cliente |
| PUT | `/api/pagos/:id/cancelar` | Cancelar pago |
| POST | `/api/pagos/reportes/periodo` | Reporte por período |

---

## 💻 Ejemplos de Uso

### Venta al Contado

```bash
# 1. Crear venta
POST /api/ventas
{
  "cliente_id": 1,
  "propiedad_id": 1,
  "tipo_venta": "CONTADO",
  "monto_total": 50000,
  "monto_liquido": 50000
}

# 2. Registrar pago completo
POST /api/pagos
{
  "nota_venta_id": 1,
  "concepto_pago": "VENTA_CONTADO",
  "fecha_pago": "2026-05-06",
  "monto": 50000
}

# 3. Ver resumen
GET /api/pagos/venta/1/resumen
```

### Venta al Crédito

```bash
# 1. Crear venta (genera plan automáticamente)
POST /api/ventas
{
  "cliente_id": 2,
  "propiedad_id": 2,
  "tipo_venta": "CREDITO",
  "cuota_inicial": 20000,
  "saldo_credito": 80000,
  "numero_cuotas": 24,
  "tasa_interes": 5.5,
  "fecha_inicio_pago": "2026-06-06"
}

# 2. Registrar cuota inicial
POST /api/pagos
{
  "nota_venta_id": 2,
  "concepto_pago": "CUOTA_INICIAL",
  "fecha_pago": "2026-05-06",
  "monto": 20000
}

# 3. Registrar cuota mensual
POST /api/pagos
{
  "nota_venta_id": 2,
  "cuota_id": 1,
  "concepto_pago": "CUOTA",
  "fecha_pago": "2026-06-06",
  "monto": 3500.50
}
```

---

## 🛡️ Validaciones

### Por Concepto de Pago

| Concepto | Validación |
|----------|-----------|
| CUOTA_INICIAL | • Venta debe ser CREDITO<br>• Monto ≤ cuota_inicial |
| VENTA_CONTADO | • Venta debe ser CONTADO<br>• Monto ≤ monto_liquido |
| CUOTA | • Requiere cuota_id<br>• Cuota no pagada<br>• Monto ≤ monto_cuota |
| OTRO | • Sin validaciones especiales |

---

## 🔄 Comportamiento Automático

- ✅ Pago de cuota completo → Cuota marcada como 'Pagada'
- ✅ Cancelación de pago → Cuota vuelve a 'Pendiente'
- ✅ Validación de montos en tiempo real
- ✅ Transacciones ACID para integridad de datos

---

## 📈 Consultas Útiles

### Resumen de Pagos por Venta
```bash
GET /api/pagos/venta/1/resumen
# Retorna: total_pagado, total_pendiente, porcentaje_pagado
```

### Historial de Pagos por Cliente
```bash
GET /api/pagos/cliente/2/resumen
# Retorna: total_pagado, total_ventas, ventas_credito
```

### Reporte Mensual
```bash
POST /api/pagos/reportes/periodo
{
  "fecha_inicio": "2026-05-01",
  "fecha_fin": "2026-05-31"
}
# Retorna: totales por concepto, cantidad de pagos, estadísticas
```

---

## 🧪 Testing (Próximo)

Recomendación para crear tests:

```php
// tests/Feature/PagoControllerTest.php
public function test_registrar_pago_contado()
public function test_registrar_pago_credito()
public function test_registrar_cuota()
public function test_validaciones_monto()
public function test_cancelar_pago()
public function test_resumen_venta()
```

---

## 📝 Próximos Pasos Opcionales

- [ ] Crear Factory y Seeder para tests
- [ ] Implementar soft deletes en Pago
- [ ] Agregar logging de cambios
- [ ] Crear dashboard de cobranzas en frontend
- [ ] Exportar reportes a PDF/Excel
- [ ] Notificaciones por vencimiento de cuota
- [ ] SMS/Email a clientes con cuotas vencidas

---

## 📞 Soporte

Para dudas sobre los endpoints, revisar:
1. `DOCUMENTACION_PAGOS.md` - Referencia técnica
2. `GUIA_PRACTICA_PAGOS.md` - Ejemplos prácticos
3. Comentarios en `app/Services/PagoService.php`

---

## ✅ Checklist de Implementación

- [x] Crear migración de tabla PAGO
- [x] Crear modelo Pago
- [x] Actualizar modelos NotaVenta y Cuota
- [x] Crear PagoController con 9 métodos
- [x] Crear PagoService con helpers
- [x] Agregar rutas en api.php
- [x] Documentación técnica
- [x] Guía práctica con ejemplos
- [x] Validaciones completas
- [x] Transacciones ACID

Sistema listo para producción ✨
