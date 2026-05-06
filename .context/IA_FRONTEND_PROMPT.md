# Contexto para IA: Desarrollo de Frontend Multilider System

Este archivo está diseñado para ser entregado a una IA de generación de código (Claude, GPT, etc.) para que comprenda exactamente la estructura del backend y los requerimientos del negocio.

---

## 1. Stack Tecnológico Objetivo
- **Framework:** Vue 3 (Composition API + `<script setup>`).
- **CSS:** TailwindCSS.
- **Store:** Pinia.
- **Icons:** Lucide-Vue o HeroIcons.

---

## 2. Estructura del API (Respuesta de `GET /api/landing`)

La IA debe usar este JSON como base para sus componentes y tipos:

```json
{
  "empresa": {
    "nombre": "Multilider System",
    "logo": "url_imagen",
    "hero_image_1": "url_imagen",
    "hero_title_1": "Título Impactante",
    "hero_subtitle_1": "Subtítulo de apoyo",
    "hero_image_2": "url_imagen",
    "hero_image_3": "url_imagen",
    "eslogan": "Frase de marca",
    "color_primario": "#1e40af",
    "color_secundario": "#ffffff",
    "whatsapp": "+591...",
    "facebook": "url",
    "mapa_iframe": "<iframe>...</iframe>"
  },
  "propiedades_destacadas": [
    {
      "id": 1,
      "codigo": "LOTE-01",
      "precio_venta": "50000.00",
      "moneda": "USD",
      "tipo": "Lote",
      "imagenes": [{ "url": "url", "es_principal": true }]
    }
  ],
  "asesores": [
    {
      "nombre_completo": "Juan Perez",
      "telefono": "70000000",
      "correo": "juan@mail.com"
    }
  ]
}
```

---

## 3. Lógica de Componentes Requerida

### A. Hero Slider (Sección Principal)
- **Componente:** `HeroSlider.vue`.
- **Lógica:** Recorrer `empresa.hero_image_1`, `2` y `3`. 
- **Efecto:** Transición suave (fade o slide) cada 5 segundos.
- **Overlay:** Texto dinámico usando `hero_title_X` y `hero_subtitle_X`.

### B. Branding Dinámico
- El frontend **debe** aplicar el color primario dinámicamente:
```javascript
// En App.vue o MainLayout.vue
onMounted(() => {
  document.documentElement.style.setProperty('--p-color', empresa.color_primario);
});
```

### C. Catálogo de Propiedades
- **Filtros:** Reactivos por `tipo` (Casa/Lote) y `precio`.
- **Cards:** Mostrar precio con formato, código de propiedad y badge de "Destacado".

---

## 4. Prompt Maestro para Generación de Código

*"Actúa como un desarrollador Frontend Senior experto en Vue 3 y TailwindCSS. Basándote en el JSON y la estructura de backend proporcionada, genera el componente `HeroSlider.vue`. El slider debe ser responsive, usar Swiper.js o una solución nativa de Tailwind, e integrar los campos `hero_image_n`, `hero_title_n` y `hero_subtitle_n` del objeto `empresa`. Asegúrate de que el diseño sea elegante, minimalista y orientado al sector de bienes raíces de lujo."*

---

## 5. Notas de Seguridad y Performance
- Las imágenes de las propiedades deben tener `loading="lazy"`.
- El botón de WhatsApp debe generarse dinámicamente usando el campo `empresa.whatsapp` con el formato: `https://wa.me/XXXXXXXX`.
