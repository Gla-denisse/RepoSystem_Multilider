# Guía de Integración: Landing Page Multilider System

Esta guía proporciona las especificaciones técnicas del backend y las mejores prácticas para el desarrollo del frontend de la Landing Page.

---

## 1. Especificaciones del Backend

### 1.1 Modelos y Migraciones
Se han implementado dos cambios estructurales principales:

*   **Tabla `mi_empresa`**: Almacena la identidad corporativa y configuración de la landing.
*   **Campo `es_destacado` (Propiedades)**: Flag booleano para filtrar las mejores ofertas en la sección Hero o Destacados.

### 1.2 Endpoints de API

#### `GET /api/landing` [PÚBLICO]
Recupera todos los datos necesarios para renderizar la landing completa en una sola petición.

**Estructura de Respuesta:**
```json
{
  "empresa": {
    "nombre": "Multilider System",
    "logo": "/storage/empresa/logo.png",
    "hero_image_1": "/storage/empresa/h1.jpg",
    "hero_title_1": "Título del Slider 1",
    "hero_subtitle_1": "Subtítulo del Slider 1",
    "hero_image_2": "/storage/empresa/h2.jpg",
    "hero_title_2": "Título del Slider 2",
    "hero_subtitle_2": "Subtítulo del Slider 2",
    "hero_image_3": "/storage/empresa/h3.jpg",
    "hero_title_3": "Título del Slider 3",
    "hero_subtitle_3": "Subtítulo del Slider 3",
    "descripcion_nosotros": "...",
    "color_primario": "#1e40af",
    "whatsapp": "+591...",
    "facebook": "...",
    "mapa_iframe": "<iframe>...</iframe>"
  },
  "propiedades_destacadas": [
    {
      "id": 1,
      "codigo": "LOTE-001",
      "precio_venta": "50000.00",
      "imagenes": [{ "url": "...", "es_principal": true }]
    }
  ],
  "ultimas_propiedades": [...],
  "asesores": [...]
}
```

#### `POST /api/empresa` [PRIVADO - Sanctum]
Actualiza la información de contacto, "Nosotros" y branding.
*   **Permiso Requerido:** `acceso_empresa`
*   **Validaciones:** Todos los campos de redes sociales deben ser URLs válidas. El logo y las imágenes del hero deben ser archivos de imagen (jpeg, png, etc) de máx 2MB.
*   **Nota:** Al ser una subida de archivos, se debe usar `multipart/form-data`. Si la imagen ya existe, el backend la reemplaza automáticamente.

---

## 2. Guía de Desarrollo Frontend (Recomendada)

### 2.1 Arquitectura de Software
Se recomienda el uso de **Vue 3 con Composition API** y **Vite** por su velocidad y manejo eficiente de componentes.

*   **Estado Global:** Utilizar **Pinia** para almacenar la información de `empresa`. Esto permite acceder al logo y redes sociales desde el Header y Footer sin peticiones redundantes.
*   **Router:** **Vue Router** para manejar la navegación interna (Inicio, Nosotros, Propiedades).

### 2.2 Organización de Componentes
Dividir la landing en componentes atómicos y de sección:

1.  **Layouts:** `MainLayout.vue` (Header + RouterView + Footer).
2.  **Secciones (Views/Landing.vue):**
    *   `HeroSection.vue`: Sliders con propiedades destacadas.
    *   `StatsSection.vue`: Años de experiencia, clientes felices.
    *   `PropertyGrid.vue`: Grid de las últimas propiedades (Cards).
    *   `AboutSection.vue`: Misión, Visión (datos de `mi_empresa`).
    *   `AdvisorSection.vue`: Carrusel de asesores.
    *   `ContactSection.vue`: Formulario + Google Maps Iframe.

### 2.3 Consumo de API y Buenas Prácticas
*   **Cliente HTTP:** Axios con una instancia preconfigurada (`baseURL`).
*   **Manejo de Errores:** Interceptores para manejar errores 401 (sesión expirada) o 500.
*   **Loading States:** Usar Skeletons para las Cards de propiedades mientras se cargan los datos.

```javascript
// Ejemplo de Composable para la Landing
export function useLanding() {
    const data = ref(null);
    const loading = ref(true);

    const fetchLanding = async () => {
        try {
            const res = await axios.get('/api/landing');
            data.value = res.data;
        } finally {
            loading.value = false;
        }
    };
    return { data, loading, fetchLanding };
}
```

### 2.4 Optimización de Rendimiento (Performance)
1.  **Lazy Loading de Imágenes:** Usar el atributo nativo `loading="lazy"` para las fotos de propiedades.
2.  **WebP:** Procesar las imágenes en el backend (o usar servicios CDN) para servirlas en formato WebP.
3.  **Code Splitting:** Cargar las rutas de administración de forma diferida.

### 2.5 UX/UI y Accesibilidad
*   **Responsive Design:** Mobile-First. Priorizar la visualización del botón de WhatsApp en móviles.
*   **Accesibilidad:** Usar etiquetas `aria-label` en los links de redes sociales y `alt` descriptivo en las imágenes de propiedades.
*   **Branding Dinámico:** Utilizar las variables `color_primario` y `color_secundario` devueltas por la API para aplicar estilos en línea o variables CSS (`--primary-color`).

### 2.6 Integración de Formularios
*   **Frontend:** Validación reactiva (ej: `Vuelidate` o `VeeValidate`).
*   **Backend:** El sistema ya cuenta con validaciones en `PropiedadController` y `LandingController`. Los errores 422 deben mostrarse claramente bajo cada input del formulario.

---

## 3. Detalle de Implementación Visual (CSS)
Recomendamos usar **TailwindCSS** para una maquetación rápida y consistente.

```html
<!-- Ejemplo de Card de Propiedad -->
<div class="rounded-xl overflow-hidden shadow-lg hover:scale-105 transition">
  <img :src="propiedad.imagenes[0].url" class="h-48 w-full object-cover">
  <div class="p-4">
    <span class="text-blue-600 font-bold text-xl">${{ propiedad.precio_venta }}</span>
    <p class="text-gray-600 text-sm">{{ propiedad.zona.ciudad.nombre }}</p>
  </div>
</div>
```
