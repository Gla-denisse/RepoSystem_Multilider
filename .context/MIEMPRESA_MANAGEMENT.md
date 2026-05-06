# Gestión de MiEmpresa - Documentación de Administración

Esta guía detalla cómo el frontend debe interactuar con el backend para gestionar la información corporativa, branding y el slider de la landing page.

---

## 1. Endpoints de Gestión

### A. Obtener Información Actual (Para precargar el formulario)
*   **URL:** `/api/landing`
*   **Método:** `GET`
*   **Acceso:** Público (pero se usa en el admin para ver los valores actuales).

### B. Actualizar Información y Branding
*   **URL:** `/api/empresa`
*   **Método:** `POST`
*   **Headers:** 
    *   `Authorization: Bearer {token}`
    *   `Content-Type: multipart/form-data`
*   **Permiso Requerido:** `acceso_empresa`
*   **Body (FormData):**

#### Datos Generales y Contacto
| Campo | Tipo | Requerido | Descripción |
| :--- | :--- | :--- | :--- |
| `nombre` | String | Sí | Nombre de la empresa |
| `eslogan` | String | No | Frase corta de marca |
| `descripcion_nosotros`| Text | No | Texto largo para la sección "Nosotros" |
| `mision` | Text | No | Misión de la empresa |
| `vision` | Text | No | Visión de la empresa |
| `valores` | Text | No | Valores separados por comas o saltos de línea |
| `direccion` | String | No | Dirección física |
| `telefono` | String | No | Teléfono fijo o de oficina |
| `whatsapp` | String | No | Número en formato internacional (ej: 59170000000) |
| `email` | Email | No | Correo de contacto |

#### Redes Sociales (URLs válidas)
| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `facebook`, `instagram`, `tiktok`, `youtube` | URL | Link completo a la red social |

#### Branding y Slider (Archivos)
| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `logo` | Archivo | Logo principal (PNG recomendado) |
| `color_primario` | String | Hexadecimal (ej: #1e40af) |
| `color_secundario` | String | Hexadecimal (ej: #ffffff) |
| `hero_image_1` | Archivo | Imagen 1 del Slider Hero |
| `hero_title_1` | String | Título que aparece sobre la imagen 1 |
| `hero_subtitle_1` | String | Subtítulo sobre la imagen 1 |
| `hero_image_2` | Archivo | Imagen 2 del Slider Hero |
| `hero_title_2` | String | Título sobre la imagen 2 |
| `hero_subtitle_2` | String | Subtítulo sobre la imagen 2 |
| `hero_image_3` | Archivo | Imagen 3 del Slider Hero |
| `hero_title_3` | String | Título sobre la imagen 3 |
| `hero_subtitle_3` | String | Subtítulo sobre la imagen 3 |

---

## 2. Recomendaciones de Implementación (Frontend)

### Manejo de FormData
Dado que el formulario incluye archivos, **debes usar `FormData`**.
```javascript
const formData = new FormData();
formData.append('nombre', state.nombre);
if (state.logoFile) {
    formData.append('logo', state.logoFile);
}
// Repetir para todos los campos...

const response = await axios.post('/api/empresa', formData);
```

### Visualización Previa (Preview)
Para una mejor UX, muestra una previsualización de las imágenes antes de enviarlas:
```javascript
const handleFile = (e) => {
    const file = e.target.files[0];
    previewUrl.value = URL.createObjectURL(file);
    state.logoFile = file;
};
```

### Paleta de Colores
Usa un input de tipo color (`<input type="color">`) para los campos `color_primario` y `color_secundario` para que el usuario pueda seleccionar el tono exacto de su marca.

---

## 3. Notas del Backend
1.  **Eliminación Automática:** El servidor borra automáticamente la imagen vieja cuando subes una nueva para el mismo campo, manteniendo el almacenamiento limpio.
2.  **Validación de Imágenes:** El servidor acepta `jpeg, png, jpg, gif` con un tamaño máximo de **2MB** por archivo.
3.  **Mapa:** El campo `mapa_iframe` acepta el código `<iframe>` completo que entrega Google Maps (Compartir > Insertar mapa).
