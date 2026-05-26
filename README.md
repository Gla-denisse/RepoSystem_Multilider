# System Multilíder — Backend API (Laravel 10+)

Este es el repositorio de backend y API REST de **System Multilíder**, un sistema empresarial completo de gestión inmobiliaria, ventas (contado y crédito), planes de pago, cobros y contratos digitales.

## 🚀 Documentación Completa y Análisis Técnico

Hemos generado un documento integral que detalla de forma exhaustiva toda la arquitectura, esquema de base de datos relacional, glosario completo de modelos y controladores, análisis detallado de flujos de negocio (ventas, amortización de cuotas, contratos, pasarelas de cobro digital e integración de webhooks), mapa de rutas de la API, y guía paso a paso de despliegue.

👉 **[Leer Documento de Análisis y Documentación Completa del Backend](file:///C:/laragon/www/system_multilider/backend/ANALISIS_Y_DOCUMENTACION.md)**

---

## 🛠️ Stack Tecnológico
*   **Lenguaje:** PHP 8.1+
*   **Framework:** Laravel 10.x
*   **Base de Datos:** MariaDB / MySQL 8.0
*   **Autenticación:** JWT (JSON Web Tokens)
*   **Pasarelas de Pago:** Libélula (QR Dinámico) & Todotix (Enlace de Pago / Tarjeta)

---

## 💻 Guía Rápida de Instalación

1. **Instalar Dependencias de Composer:**
   ```bash
   composer install
   ```

2. **Configurar el Entorno:**
   * Duplica el archivo `.env.example` y nómbralo `.env`.
   * Configura las credenciales de tu base de datos y llaves de API en el archivo `.env`.

3. **Generar Claves de Seguridad:**
   ```bash
   php artisan key:generate
   php artisan jwt:secret
   ```

4. **Correr Migraciones y Seeders (Población Semilla):**
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Enlazar el Storage para Archivos y Contratos:**
   ```bash
   php artisan storage:link
   ```

6. **Iniciar el Servidor de Desarrollo:**
   ```bash
   php artisan serve
   ```
   La API estará lista y escuchando en `http://127.0.0.1:8000/api/`.

---
*Diseñado y desarrollado por el equipo técnico de Advanced Agentic Coding.*
