# Gestión de Imágenes de Propiedades - Documentación de API

Esta documentación detalla los endpoints y la estructura de datos para la gestión de imágenes de propiedades en el sistema.

## 1. Estructura de Datos (Modelo ImagenPropiedad)

Cada objeto de imagen contiene:

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `id` | Integer | ID único de la imagen |
| `propiedad_id` | Integer | ID de la propiedad a la que pertenece |
| `url` | String | URL pública de la imagen (Ej: `/storage/propiedades/1/abc.jpg`) |
| `es_principal` | Boolean | Indica si es la imagen de portada/principal |

---

## 2. Endpoints de Imágenes

### A. Subir Imágenes
Permite subir una o varias imágenes para una propiedad específica.

*   **URL:** `/api/propiedades/{propiedad_id}/imagenes`
*   **Método:** `POST`
*   **Content-Type:** `multipart/form-data`
*   **Body:**
    *   `imagenes[]`: Archivo(s) (jpeg, png, jpg, gif. Máx 2MB por archivo)
*   **Comportamiento:** Si la propiedad no tiene imágenes, la primera que se suba se marcará automáticamente como `es_principal = true`.

### B. Eliminar Imagen
Elimina el registro de la base de datos y el archivo físico del servidor.

*   **URL:** `/api/imagenes-propiedades/{id}`
*   **Método:** `DELETE`

### C. Establecer Imagen Principal
Marca una imagen específica como la principal de la propiedad y desmarca todas las demás.

*   **URL:** `/api/imagenes-propiedades/{id}/principal`
*   **Método:** `PATCH`

---

## 3. Integración con Propiedades

El endpoint de propiedades (`/api/propiedades`) ha sido actualizado para incluir las imágenes en su respuesta.

### Ejemplo de Respuesta (`GET /api/propiedades/{id}`)

```json
{
    "id": 1,
    "codigo": "CASA-001",
    "tipo": "Casa",
    "precio_venta": "150000.00",
    "moneda": "USD",
    "imagenes": [
        {
            "id": 10,
            "propiedad_id": 1,
            "url": "/storage/propiedades/1/foto1.jpg",
            "es_principal": true
        },
        {
            "id": 11,
            "propiedad_id": 1,
            "url": "/storage/propiedades/1/foto2.jpg",
            "es_principal": false
        }
    ],
    "propietario": { ... },
    "zona": { ... }
}
```

## 4. Notas para Frontend

1.  **URL de Imágenes:** Las URLs devueltas son relativas a la raíz del servidor. Asegúrate de concatenar el dominio base (ej: `http://localhost:8000`) si es necesario.
2.  **FormData:** Para el endpoint de subida, es obligatorio usar `FormData` en JavaScript para enviar archivos.
3.  **Filtrado:** Para mostrar la miniatura en una lista de propiedades, puedes buscar en el array `imagenes` aquella que tenga `es_principal: true` o simplemente tomar la primera del array si el orden es importante.
4.  **Almacenamiento:** Las imágenes se guardan en el servidor en la carpeta `public/storage/propiedades/{propiedad_id}/`.
