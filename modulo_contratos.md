# Implementación del módulo Contratos

Ver especificación completa para backend, API y frontend.

## Requerimientos

- Tabla contratos relacionada 1:1 con ventas
- Creación automática al crear venta
- Estado inicial: Pendiente de firma
- Subida de PDF
- Descarga
- Firmado
- Anulación
- Listado paginado con filtros
- Nuevo menú: Contratos y Entregas

## Estructura SQL

```sql
CREATE TABLE contratos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id BIGINT UNSIGNED NOT NULL UNIQUE,
    codigo_contrato VARCHAR(50) NOT NULL UNIQUE,
    fecha_emision_contrato DATE NOT NULL,
    fecha_firma_contrato DATE NULL,
    tipo_venta VARCHAR(100) NOT NULL,
    url_doc TEXT NULL,
    estado ENUM('Pendiente de firma','Firmado','Anulado') NOT NULL DEFAULT 'Pendiente de firma'
);
```

## Flujo

1. Crear venta
2. Crear contrato automático
3. Subir PDF
4. Firmar opcionalmente
5. Descargar
6. Anular si aplica

## Frontend

- Listado
- Filtros
- Paginación
- Modal subir PDF
- Toggle firmado
- Botón descargar
- Botón anular
