# Refactorización completa: Zona → Distrito + Sectores Urbanos

## Objetivo

Realizar una refactorización integral del sistema (backend, frontend, API y base de datos) para reemplazar completamente la entidad **Zona** por **Distrito**, introducir la entidad **Sector Urbano**, y redefinir las relaciones de propiedades.

---

## 1. Renombrar tabla `zonas` a `distritos`

Aplicar refactorización global:

- Tabla: `zonas` → `distritos`
- Modelo: `Zona` → `Distrito`
- Controladores
- Servicios
- Repositories
- Requests
- Seeders
- Factories
- Policies
- Resources
- Rutas y endpoints
- Migraciones
- Constraints e índices
- Traducciones / labels

---

## 2. Eliminar relación directa entre propiedades y zonas

Eliminar completamente:

- Foreign key `zona_id` en `propiedades`
- Relaciones ORM:
  - `Propiedad belongsTo Zona`
  - `Zona hasMany Propiedades`

Nueva arquitectura:

```text
Distrito
   └── SectoresUrbanos
          └── Propiedades
```

---

## 3. Crear tabla `sectores_urbanos`

```sql
CREATE TABLE sectores_urbanos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    distrito_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('Barrio', 'Urbanización', 'Condominio') NOT NULL DEFAULT 'Barrio',
    uv VARCHAR(20) NULL,
    manzano VARCHAR(20) NULL,
    CONSTRAINT fk_sector_distrito FOREIGN KEY (distrito_id)
        REFERENCES distritos(id) ON DELETE CASCADE
);
```

---

## 4. Relaciones ORM

### Distrito

```php
hasMany(SectorUrbano::class)
```

### SectorUrbano

```php
belongsTo(Distrito::class)
hasMany(Propiedad::class)
```

### Propiedad

```php
belongsTo(SectorUrbano::class)
```

---

## 5. Modificar tabla `propiedades`

Agregar:

```sql
sector_urbano_id BIGINT UNSIGNED NOT NULL
```

Constraint:

```sql
FOREIGN KEY (sector_urbano_id)
REFERENCES sectores_urbanos(id)
ON DELETE CASCADE
```

Eliminar:

```sql
zona_id
```

---

## 6. Actualizar API

Eliminar:

```json
{
  "zona_id": 1
}
```

Reemplazar por:

```json
{
  "distrito_id": 1,
  "sector_urbano_id": 15
}
```

Actualizar:

- Requests
- Responses
- DTOs
- Serializers
- Documentación

---

## 7. Migración segura de datos

Secuencia:

1. Renombrar `zonas` → `distritos`
2. Crear `sectores_urbanos`
3. Crear sector temporal si hay datos legacy
4. Migrar propiedades
5. Eliminar `zona_id`
6. Actualizar constraints

---

# FRONTEND

## 8. Renombrado global

Reemplazar:

- `zona` → `distrito`
- `zonas` → `distritos`

Eliminar:

- `zona_id`

Agregar:

- `sector_urbano_id`

Aplicar en:

- Componentes
- Vistas
- Formularios
- Stores
- Hooks/composables
- Interfaces
- Servicios API
- Validaciones

---

## 9. Formularios de propiedades

Debe existir:

### Selector Distrito
Carga distritos disponibles.

### Selector Sector Urbano
Dependiente del distrito seleccionado.

Reglas:

- Cargar dinámicamente sectores por distrito
- Limpiar sector al cambiar distrito
- Validación obligatoria

---

## 10. CRUD administrativos

### Distritos
- Listar
- Crear
- Editar
- Eliminar

### Sectores Urbanos
- Listar
- Crear
- Editar
- Eliminar
- Filtrar por distrito

---

## 11. Listados de propiedades

Antes:

```text
Zona
```

Ahora:

```text
Distrito / Sector Urbano
```

Ejemplo:

```text
Distrito 4 / Barrio Equipetrol
```

---

## 12. Filtros

Eliminar:

- Filtro por zona

Agregar:

- Filtro por distrito
- Filtro por sector urbano
- Filtro jerárquico

---

## 13. Tipado frontend

Antes:

```ts
interface Propiedad {
  zona_id: number
}
```

Ahora:

```ts
interface Propiedad {
  distrito_id: number
  sector_urbano_id: number
}
```

---

## Resultado final esperado

Relación definitiva:

```text
Propiedad → SectorUrbano → Distrito
```

No debe existir ninguna referencia residual a:

- zona
- zonas
- zona_id
- relación directa propiedad ↔ distrito
