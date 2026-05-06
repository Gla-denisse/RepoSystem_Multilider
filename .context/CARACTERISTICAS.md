# Contexto de Desarrollo: Gestión de Características

Este documento detalla el funcionamiento del sistema de características (amenidades/servicios) y su relación con las propiedades. Es una guía técnica para IAs, desarrolladores Backend y Frontend.

## 🏗️ Estructura de Datos

### 1. Tabla `caracteristicas`
Define los atributos que pueden asignarse a una propiedad (ej: "Piscina", "Gas Domiciliario").

| Campo | Tipo | Descripción | Reglas |
| :--- | :--- | :--- | :--- |
| `id` | BigInt | Identificador único | Primaria |
| `nombre` | String | Nombre de la característica | Requerido, Único |
| `tipo` | String | Categoría para agrupación | `Servicios`, `Interna`, `Entorno` |
| `estado` | Boolean | Disponibilidad en el sistema | Por defecto `true` |

### 2. Tabla Pivote `caracteristica_propiedad`
Gestiona la relación de Muchos a Muchos entre Propiedades y Características.

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `propiedad_id` | ForeignID | ID de la propiedad |
| `caracteristica_id` | ForeignID | ID de la característica |

## 🔗 Relaciones Eloquent

### En el Modelo `Caracteristica`
- **`propiedades()`**: Una característica puede estar presente en muchas propiedades (`belongsToMany`).

### En el Modelo `Propiedad`
- **`caracteristicas()`**: Una propiedad posee múltiples características (`belongsToMany`).
- **Uso recomendado**: `$propiedad->caracteristicas()->sync($ids)` para actualizar la lista.

## 🛠️ Reglas de Negocio

1.  **Categorización (`tipo`)**:
    - **Servicios**: Luz, Agua, Gas, Internet.
    - **Interna**: Habitaciones, tipo de piso, aire acondicionado.
    - **Entorno**: Colegios, parques, transporte público, centros comerciales.
2.  **Sincronización**: Al guardar una propiedad desde el Frontend, se deben enviar los IDs de las características seleccionadas para sincronizarlas en la tabla pivote.
3.  **Eliminación Lógica**: Las características utilizan el campo `estado`. Si una característica está desactivada (`false`), no debe aparecer como opción para nuevas propiedades.

## 🌐 API Endpoints (`/api/caracteristicas`)

- `GET /caracteristicas`: Lista todas las características activas (soporta `search` y `per_page`).
- `POST /caracteristicas`: Registra una nueva característica.
- `GET /caracteristicas/{id}`: Detalle de una característica.
- `PUT /caracteristicas/{id}`: Actualiza nombre o tipo.
- `DELETE /caracteristicas/{id}`: Toggle de `estado` (Inhabilitar/Habilitar).

## 🎨 Guía para el Frontend

- **Visualización**: Se recomienda agrupar las características por su campo `tipo` en el formulario de la propiedad (usando Checkboxes o Multi-select chips).
- **Icons**: El Frontend puede mapear el `nombre` o `tipo` a iconos específicos (ej: `tipo: Servicios` -> Icono de rayo/gota).
- **Carga de Datos**: Al mostrar una propiedad, acceder a las características mediante el atributo `caracteristicas` del objeto propiedad.

## 🚀 Guía para IA (Contexto)

- **Queries**: Al buscar propiedades por características, usar `whereHas('caracteristicas', function($q) { ... })`.
- **Eager Loading**: Usar siempre `$propiedad->load('caracteristicas')` para evitar latencia en el listado.
- **Validación**: Asegurar que los IDs enviados en la sincronización existan en la tabla `caracteristicas`.

---
*Última actualización: Mayo 2026*
