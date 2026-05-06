# Contexto de Desarrollo: Tabla `propiedades`

Este documento sirve como guía técnica para IAs y desarrolladores Backend sobre el funcionamiento, reglas de negocio y estructura de la tabla de propiedades.

## 🏗️ Estructura de Datos (Esquema)

| Campo | Tipo | Descripción | Reglas |
| :--- | :--- | :--- | :--- |
| `id` | BigInt | Identificador único | Primaria, Autoincrementable |
| `propietario_id` | ForeignID | Relación con `propietarios` | Requerido, `constrained()` |
| `zona_id` | ForeignID | Relación con `zonas` | Requerido, `constrained()` |
| `ubicacion_id` | ForeignID | Relación con `ubicaciones` (GPS) | Opcional, `unique`, `nullOnDelete()` |
| `codigo` | String | Código interno único (ej: VILLA-01) | Requerido, Único |
| `tipo` | String | Tipo de inmueble | Casa, Lote, Local, etc. |
| `precio_venta` | Decimal | Precio comercial | Requerido, (12, 2) |
| `moneda` | Enum | Divisa de la transacción | `USD` (por defecto), `BOB` |
| `superficie_m2` | Decimal | Superficie total del terreno | Requerido, (10, 2) |
| `superficie_construida_m2`| Decimal | Superficie techada | Opcional (usado en casas) |
| `frente_mts` | Decimal | Medida frontal del terreno | Opcional |
| `fondo_mts` | Decimal | Medida de profundidad | Opcional |
| `habitaciones` | Integer | Cantidad de cuartos | Por defecto 0 |
| `banos` | Integer | Cantidad de baños | Por defecto 0 |
| `es_esquina` | Boolean | Indica si el lote está en esquina | Por defecto `false` |
| `estado` | String | Estado de disponibilidad | `Disponible`, `Vendido`, `Reservado` |
| `activo` | Boolean | Eliminación lógica | Por defecto `true` |

## 🔗 Relaciones Eloquent

- **`propietario()`**: Una propiedad pertenece a un propietario (`belongsTo`).
- **`zona()`**: Pertenece a una zona (`belongsTo`), la cual a su vez pertenece a una ciudad.
- **`ubicacion()`**: Relación 1:1 opcional con coordenadas GPS y referencias (`belongsTo`).
- **`caracteristicas()`**: Relación de muchos a muchos (`belongsToMany`) a través de la tabla pivote `caracteristica_propiedad`.

## 🛠️ Reglas de Negocio Importantes

1.  **Código Único**: El `codigo` debe ser único y descriptivo. No se puede repetir en el sistema.
2.  **Gestión de Estados**: 
    - Al crear una propiedad, el estado inicial es siempre `Disponible`.
    - Al registrar una venta en `NotaVentaController`, el estado debe cambiar automáticamente a `Vendido`.
    - Si una venta se anula, la propiedad debe volver al estado `Disponible`.
3.  **Eliminación Lógica**: No se deben borrar físicamente las propiedades. Se debe utilizar el campo `activo` para inhabilitarlas del sistema.
4.  **Jerarquía de Ubicación**: Para obtener la ubicación completa, se debe cargar la relación: `propiedad.zona.ciudad`.

## 🚀 Guía para Generación de Código (IA)

- **Filtros**: Al listar propiedades, siempre incluir filtros por `zona_id`, `ciudad_id`, `tipo` y `rango de precios`.
- **Eager Loading**: Siempre cargar `with(['propietario', 'zona.ciudad', 'caracteristicas'])` para evitar el problema de consultas N+1.
- **Validación**: Validar que el `propietario_id` y `zona_id` existan antes de insertar o actualizar.
- **Respuestas API**: Mantener el formato estándar de respuesta: `return response()->json(['message' => '...', 'data' => $propiedad], 200);`.

---
*Última actualización: Mayo 2026*
