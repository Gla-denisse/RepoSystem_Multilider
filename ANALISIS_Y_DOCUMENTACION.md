# Análisis y Documentación Técnica del Backend — System Multilíder

Este documento contiene un análisis exhaustivo y la documentación completa de la arquitectura, estructura de base de datos, lógica de negocio y API del backend del sistema **System Multilíder**, construido sobre el framework **Laravel 10+** (PHP 8.1+).

---

## 1. Visión General del Sistema

**System Multilíder** es una plataforma corporativa integral para la gestión del catálogo inmobiliario (casas y lotes), control de clientes y asesores, facturación, cobranza en cuotas, contratos digitales e integraciones con pasarelas de pago electrónico.

### Módulos Principales del Ecosistema
*   **Gestión Inmobiliaria (Catálogo):** Inventario de propiedades (Lotes y Casas) categorizadas por Ubicación (Ciudad -> Distrito -> Sector Urbano) con sus dimensiones, colindancias, precios y galería de imágenes.
*   **Ventas y Cobranzas (Amortización):** Emisión de notas de venta de dos tipos:
    *   *CONTADO:* Liquidación completa del precio con registro inmediato de ingresos.
    *   *CRÉDITO:* Generación automática de planes de pagos estructurados con un calendario de cuotas (capital, interés, amortizaciones y estados de vencimiento).
*   **Contratos Digitales:** Creación automática de registros de contratos tras cada venta, permitiendo cargar, descargar, firmar y anular documentos PDF correspondientes.
*   **Pasarela de Pagos (Cobro Electrónico):** Integración nativa para la recaudación mediante códigos QR (Libélula) y enlaces de pago / tarjetas de crédito (Todotix), procesados de forma automatizada mediante Webhooks.
*   **Comisiones de Asesores:** Liquidación y control del historial de comisiones comerciales para asesores de venta de acuerdo con porcentajes parametrizados y estados de los pagos de cuotas correspondientes.

---

## 2. Arquitectura Técnica y Stack

El backend está diseñado como una **REST API stateless** que expone endpoints seguros consumidos por una SPA en Vue 3.

*   **Framework Principal:** Laravel 10.x.
*   **Lenguaje:** PHP 8.1+.
*   **Manejador de Base de Datos:** MariaDB / MySQL 8.0.
*   **Autenticación:** JWT (JSON Web Tokens) mediante cabeceras de autorización `Bearer`.
*   **Patrón Arquitectónico:** MVC adaptado a API, donde las peticiones HTTP son capturadas por rutas, validadas a través de `FormRequest`, procesadas por controladores especializados, y formateadas en respuestas estructuradas JSON.
*   **Manejo de Transacciones:** Uso riguroso de `DB::transaction()` en el registro de ventas, reprogramaciones y cobros para salvaguardar la integridad relacional de la base de datos ante fallos.

---

## 3. Arquitectura de Base de Datos y Entidades

El diseño relacional se compone de **7 módulos lógicos** interconectados. Un cambio arquitectónico crítico del sistema fue la **Refactorización de Zonas**, eliminando la entidad residual `zonas` y reemplazándola por una estructura jerárquica de localización limpia:

```
[Ciudad] ───hasMany───> [Distrito] ───hasMany───> [Sector Urbano] ───hasMany───> [Propiedad]
```

### Módulos del Schema de Base de Datos

### A. Módulo de Autenticación y Seguridad
*   `users`: Credenciales de acceso del personal (Administradores, Asesores) y Clientes.
*   `roles` y `permisos`: Definición de perfiles y accesos.
*   `rol_permiso` (Pivot): Asignación N:M de permisos a roles.
*   `rol_permiso_usuario` (Pivot): Asignación granular y directa de permisos por usuario individual, permitiendo excepciones de seguridad.

### B. Módulo de Localización e Inmuebles
*   `ciudades`: Ciudades base del proyecto inmobiliario (ej. Santa Cruz, La Paz).
*   `distritos`: Distritos de ordenamiento territorial asociados a una ciudad (antiguas zonas).
*   `sectores_urbanos`: Barrios, urbanizaciones o condominios asociados a un distrito. Soporta de forma opcional campos de maquetación técnica como `uv` y `manzano`.
*   `propiedades`: Lotes y Casas en venta, referenciados a un propietario, un sector urbano y coordenadas GPS.
*   `propietarios`: Registro de socios o dueños originales del suelo.
*   `caracteristicas` y `caracteristica_propiedad` (Pivot): Catálogo de amenidades y servicios básicos (ej. "Agua Potable", "Energía Eléctrica", "Alcantarillado").
*   `imagenes_propiedades`: Galería fotográfica de soporte comercial para los inmuebles.

### C. Módulo de Ventas e Historial
*   `notas_ventas`: Registro maestro del contrato comercial de la venta. Guarda importes finales, cuotas iniciales, saldo financiado, tipo de venta y estado.
*   `planes_pagos`: Configuración del crédito para ventas a plazos (número de cuotas, tasa de interés, plazo y fecha de inicio).
*   `cuotas`: Calendario detallado de vencimientos de amortización. Calcula por cada cuota la porción de capital, el interés generado, el saldo del capital y el estado de pago.

### D. Módulo de Recaudación y Caja
*   `pagos`: Transacciones asociadas a una venta o amortización de cuota.
*   `metodos_pago`: Catálogo de vías de cobro (Efectivo, Transferencia, Todotix, Libélula).
*   `cuentas_bancarias`: Registro de cajas físicas y cuentas de banco corporativas.
*   `metodo_pago_cuenta_default`: Tabla que asocia cada método de pago con la cuenta bancaria por defecto de la empresa para automatizar la conciliación de ingresos en el libro diario.

### E. Módulo de Contratos y Documentos
*   `contratos`: Registro 1:1 con la nota de venta que gestiona el estado del contrato legal, la fecha de firma y la ubicación del archivo físico en PDF (`url_doc`).

---

## 4. Glosario de Modelos (29 Modelos Eloquent)

El backend de Laravel cuenta con **29 modelos de Eloquent** ubicados en `app/Models/` que encapsulan la lógica de base de datos:

1.  **Asesor:** Representa a los ejecutivos de ventas comerciales. Vinculado 1:1 con `User` y 1:N con `NotaVenta`.
2.  **CampanaCorreo:** Registra el historial y configuración de campañas masivas de correo electrónico de marketing.
3.  **Caracteristica:** Catálogo de amenidades asociadas a propiedades (servicios básicos, alcantarillado, pavimentación, etc.).
4.  **Ciudad:** Ciudades del país donde se ejecutan los proyectos inmobiliarios de la empresa.
5.  **Cliente:** Datos de perfil de los compradores. Vinculado 1:1 con `User` y 1:N con `NotaVenta`.
6.  **Contrato:** Control de documentos y firmas contractuales legales de las ventas.
7.  **CuentaBancaria:** Cuentas de banco y cajas físicas de la empresa donde fluyen los depósitos y retiros.
8.  **Cuota:** Cada uno de los plazos de cobro autogenerados en un plan de crédito.
9.  **Distrito:** División de la ciudad que agrupa múltiples sectores urbanos.
10. **Egreso:** Control de salidas de capital por concepto de gastos operativos o de desarrollo.
11. **ImagenPropiedad:** Rutas de archivos y prioridades de las imágenes de las propiedades.
12. **Ingreso:** Ingresos misceláneos de la empresa fuera de ventas inmobiliarias directas.
13. **MetodoPago:** Métodos activos para procesar transacciones (Efectivo, Transferencia, QR, Enlace de Pago).
14. **MetodoPagoCuentaDefault:** Relación para derivar automáticamente los fondos cobrados a la cuenta corporativa adecuada.
15. **MiEmpresa:** Configuración del Singleton institucional (Branding, eslogan, redes sociales y sliders del portal público).
16. **NotaVenta:** Entidad medular de transacciones comerciales. Almacena las ventas al contado y crédito.
17. **Pago:** Registro histórico contable de ingresos transaccionales asociados a amortizaciones o ventas directas.
18. **Permiso:** Catálogo de acciones protegidas del sistema.
19. **PlanPago:** Cabecera del plan de amortización del crédito que calcula el interés global de una nota de venta.
20. **Propiedad:** Inmuebles. Contiene campos de áreas, precios, colindancias, número de lote y estados comerciales.
21. **Propietario:** Proveedores externos o socios de la empresa dueños de los terrenos administrados.
22. **Reprogramacion:** Historial de reestructuración de calendarios de cuotas para clientes con moras.
23. **Rol:** Grupos funcionales de seguridad para asignar permisos de manera corporativa.
24. **RolPermiso:** Pivote técnico relacional de roles con permisos específicos.
25. **RolPermisoUsuario:** Pivote que permite el control de acceso fino, saltando la jerarquía de roles estándar si es necesario.
26. **SectorUrbano:** Representa urbanizaciones, barrios o condominios específicos dentro de un distrito.
27. **Ubicacion:** Almacena coordenadas de geolocalización latitud/longitud y enlaces directos de Google Maps.
28. **User:** Modelo de autenticación core de Laravel. Almacena contraseñas hasheadas mediante Bcrypt.
29. **Zona:** Modelo residual mantenido por compatibilidad histórica para migraciones y herencia de datos (actualmente deprecated en favor de Distrito).

---

## 5. Glosario de Controladores de la API (33 Controladores)

La lógica de control de peticiones y respuestas se distribuye en **33 controladores REST** ubicados en `app/Http/Controllers/Api/`:

1.  **AsesorController:** CRUD de asesores de ventas, vinculación de usuarios de acceso y cambio de estados comerciales de cuentas.
2.  **AuthController:** Controla el ciclo de vida de la sesión (Login, Logout, refresco de Tokens JWT y obtención del usuario autenticado).
3.  **CaracteristicaController:** Operaciones CRUD del catálogo de amenidades y servicios básicos del inmueble.
4.  **CiudadController:** CRUD de ciudades donde opera la organización.
5.  **ClienteController:** Gestión de expedientes de clientes, carga de cédulas de identidad (CI), asignación de cuentas y accesos al portal privado del cliente.
6.  **ComisionAsesorController:** Control de liquidación de comisiones acumuladas por cada asesor, calculadas por los cobros reales devengados en el sistema.
7.  **ContratoController:** Lógica del módulo de contratos. Permite listar, adjuntar PDFs, marcar como firmado o anular contratos.
8.  **CorreoMasivoController:** Envío de campañas promocionales de propiedades a base de datos de clientes registrados mediante plantillas HTML.
9.  **CuentaBancariaController:** Gestión de cuentas corporativas y flujo de saldos iniciales y actuales.
10. **DashboardController:** Consulta analítica optimizada. Retorna métricas clave: volumen de ventas mensual, cartera vencida, cobranza diaria, inventario disponible, y rankings de asesores destacados.
11. **DistritoController:** Operaciones de distritos de la ciudad.
12. **EgresoController:** Registro de egresos corporativos vinculándolos a una cuenta bancaria y restando el importe de su saldo disponible.
13. **ImagenPropiedadController:** Carga física al servidor y borrado de imágenes de galería de inmuebles.
14. **IngresoController:** Registro de entradas de dinero extraordinarias afectando de forma aditiva el saldo de las cuentas bancarias.
15. **LandingController:** Controlador público y abierto (no requiere token JWT) que provee el catálogo web comercial, filtros de búsqueda avanzada de lotes, detalles de contacto e información de Branding.
16. **MetodoPagoController:** CRUD básico de parametrización de métodos de pago.
17. **MetodoPagoCuentaDefaultController:** Configura la matriz de conciliación automática de cuentas corporativas por método de pago.
18. **NotaVentaController:** Registro complejo de transacciones de ventas comerciales. Controla la validación de inventario y previene la doble venta de un inmueble.
19. **PagoController:** Lógica administrativa de cobranzas presenciales y registro manual de depósitos o transferencias para cuotas iniciales y mensuales.
20. **PagoPublicoController:** Expone endpoints públicos protegidos con firmas URL para que los clientes realicen el pago en línea de sus cuotas pendientes.
21. **PermisoController:** Retorna la nómina de permisos del sistema para la asignación dinámica en roles de usuario.
22. **ProfileController:** Permite a los usuarios activos actualizar sus contraseñas, correos y avatars.
23. **PropiedadController:** Gestión integral del catálogo técnico del inmueble.
24. **PropietarioController:** CRUD de socios/propietarios de los terrenos.
25. **ReporteController:** Generación masiva de datos y exportación en formatos planos/PDF sobre estados financieros, mora de cuotas, proyecciones de cobro y rendimientos de asesores.
26. **ReprogramacionController:** Lógica financiera de reprogramación de deudas vencidas, recalculando cuotas futuras.
27. **RolController:** Operaciones CRUD sobre roles.
28. **RolPermisoController:** Asignación masiva de permisos a roles.
29. **RolPermisoUsuarioController:** Override directo de permisos específicos a usuarios.
30. **SectorUrbanoController:** CRUD y listados dependientes de distritos para los sectores urbanos.
31. **UbicacionController:** Asignación de geolocalización a las propiedades de la base de datos.
32. **UserController:** Registro, edición y suspensión de usuarios generales.
33. **ZonaController:** Controlador de compatibilidad heredada que redirige llamadas a endpoints de distritos.

---

## 6. Análisis de Procesos de Negocio Críticos

### A. Flujo de Registro de Ventas (Contado vs. Crédito)
El método principal en `NotaVentaController` ejecuta la creación de una nota de venta bajo una transacción blindada de base de datos:

1.  **Validación de Disponibilidad:** Se verifica que la propiedad en `propiedades` tenga el estado `'Disponible'` y que su propiedad `activo` sea `true`.
2.  **Cálculo de Comisiones:** De forma automática, el sistema obtiene el porcentaje asignado al asesor comercial (`asesores.porcentaje_comision`) y precalcula el `monto_comision` aplicando dicho factor sobre el precio neto de venta (`monto_liquido`).
3.  **Generación de Amortizaciones (Si la venta es `CREDITO`):**
    *   Se crea un registro en `planes_pagos`.
    *   Se calcula la diferencia a financiar: `Saldo = Monto Total - Cuota Inicial`.
    *   Se ejecuta un bucle iterativo para calcular el importe base de cada cuota: `Monto Base = Saldo / Número de Cuotas`.
    *   Se crean las filas correspondientes en la tabla `cuotas` asignando las fechas de vencimiento de acuerdo con el plazo del crédito (mensual, bimestral, etc.).
    *   El interés por cuota es autocalculado utilizando el método de amortización correspondiente y sumado al `monto_cuota` final.
4.  **Actualización de Inventario:** La propiedad se marca automáticamente en base de datos como `'Vendido'` (o `'Reservado'` si la venta requiere verificación de pago inicial), bloqueándola para cualquier otra nota de venta.

---

### B. Módulo de Contratos y Flujos de Firma
El módulo de contratos está diseñado para centralizar la documentación jurídica y formalizar la propiedad legal de las ventas efectuadas:

```
[Nota de Venta Creada] ───Event Listener───> [Crear Contrato con Estado: 'Pendiente']
                                                   │
   ┌───────────────────────────────────────────────┴───────────────┐
   ▼                                                               ▼
[Subir PDF / Registrar Fecha de Firma]                      [Anular Venta]
   │                                                               │
   ▼                                                               ▼
[Contrato: 'Firmado']                                      [Contrato: 'Anulado']
```

1.  **Creación Automatizada:** Al completarse la transacción de venta, un `Observer` o disparador interno crea de forma transparente un registro en la tabla `contratos` heredando el `tipo_venta` y asociándolo mediante la clave foránea `nota_venta_id`. Se autogenera un código único `codigo_contrato` bajo el formato correlativo de la corporación.
2.  **Estado Inicial:** El contrato nace con el estado `'Pendiente de firma'` (`estado = 'Pendiente'`).
3.  **Gestión Documental (PDF):** `ContratoController@gestionar` procesa la subida de archivos binarios tipo PDF. El sistema valida el formato de entrada, almacena el archivo de forma segura en el storage (`storage/app/public/contratos/`) y guarda la ruta en el campo `url_doc`.
4.  **Flujo de Firma:** Cuando el asesor activa el interruptor de firmado y proporciona la `fecha_firma`, el estado del registro cambia a `'Firmado'` de forma atómica.
5.  **Anulación Legal:** En caso de resolución de contrato o anulación de la venta, el controlador actualiza el estado a `'Anulado'`. Esta acción bloquea la subida de nuevos documentos y deshabilita la descarga de copias del storage.

---

### C. Módulo de Pagos y Conciliación Automática
El registro de un ingreso por cobro a través de `PagoController` o `PagoPublicoController` ejecuta la siguiente secuencia de validación contable y financiera:

1.  **Validación de Importes:** Se verifica que el monto abonado coincida o sea menor al saldo adeudado del concepto correspondiente (`CUOTA_INICIAL`, `CUOTA`, `VENTA_CONTADO`).
2.  **Conciliación Bancaria Automática:**
    *   El sistema identifica el `metodo_pago_id` de la petición.
    *   Busca la relación en `metodo_pago_cuenta_default` para la empresa activa.
    *   Extrae el ID de la cuenta bancaria por defecto (`cuentas_bancarias.id`).
    *   Asigna automáticamente dicho ID en el campo `cuenta_id` del registro de `pagos`.
    *   **Afectación Aditiva:** Suma el importe del pago de forma matemática en el campo `saldo_inicial` (saldo actual) de la cuenta bancaria seleccionada, garantizando la trazabilidad de los fondos en tesorería.
3.  **Amortización de Cuota (Si el concepto es `CUOTA`):**
    *   Busca la cuota (`cuotas`) por `cuota_id`.
    *   Si el monto cubre el saldo de la cuota, el estado de la cuota se actualiza a `'Pagado'`.
    *   Si el monto es parcial, se actualiza el saldo de la cuota restando el abono y manteniéndola como `'Pendiente'` con registro de saldo de amortización.

---

### D. Integración de Pasarelas de Pago Electrónico (Libélula y Todotix)
El backend procesa la recaudación digital conectándose mediante clientes HTTP (ej. Guzzle / Http Facade) a dos pasarelas nacionales:

*   **Pasarela Libélula (Generación de QR Dinámico):**
    *   El endpoint solicita un QR enviando los datos de la cuota e importe.
    *   La pasarela retorna la imagen base64 del código QR y un ID transaccional único.
    *   El backend crea un registro de pago en estado `'PENDIENTE_PAGO'`.
    *   **Webhook / Callback:** Al realizarse el pago, Libélula envía una notificación HTTP POST al endpoint público del backend. Este valida la firma digital de la petición, localiza el pago por su ID transaccional, actualiza el estado de la transacción a `'PAGADO'` y dispara el proceso de amortización de cuota y conciliación bancaria correspondiente de forma asíncrona.
*   **Pasarela Todotix (Enlace de Pago y Tarjeta):**
    *   Permite generar links de cobro seguros donde el cliente puede pagar con tarjeta de débito o crédito nacional/internacional.
    *   Sigue el mismo flujo de registro en estado pendiente y confirmación transaccional segura mediante Webhook de retorno firmado.

---

### E. Liquidación de Comisiones de Asesores
El motor de comisiones de `ComisionAsesorController` funciona bajo el principio de **caja devengada**:

1.  La comisión se precalcula al crear la nota de venta, pero **no se liquida de inmediato** para evitar fraudes en ventas al crédito canceladas de forma temprana.
2.  El controlador calcula la comisión real devengada sumando los montos efectivos cobrados en la tabla `pagos` en estado `'PAGADO'`.
3.  **Fórmula de Liquidación de Comisiones:**
    $$\text{Comisión Devengada} = \text{Monto del Pago (PAGADO)} \times \left( \frac{\text{Porcentaje de Comisión del Asesor}}{100} \right)$$
4.  El sistema provee interfaces para que los administradores liquiden y registren los egresos hacia los asesores marcando los registros de comisiones de la venta como `'Pagado'`.

---

## 7. Catálogo de Rutas de la API (`routes/api.php`)

Las rutas principales están protegidas por el middleware `auth:api` del JWT. El mapa general de la API expone:

### Autenticación y Perfil
```http
POST   /api/auth/login            -> AuthController@login (Público)
POST   /api/auth/logout           -> AuthController@logout (Protegido)
POST   /api/auth/refresh          -> AuthController@refresh (Protegido)
GET    /api/auth/me               -> AuthController@me (Protegido)
PUT    /api/profile/update        -> ProfileController@update (Protegido)
```

### Inmuebles e Inventario
```http
GET    /api/propiedades           -> PropiedadController@index
POST   /api/propiedades           -> PropiedadController@store
GET    /api/propiedades/{id}      -> PropiedadController@show
PUT    /api/propiedades/{id}      -> PropiedadController@update
DELETE /api/propiedades/{id}      -> PropiedadController@destroy
```

### Clientes y Asesores
```http
GET    /api/clientes              -> ClienteController@index
POST   /api/clientes              -> ClienteController@store
PUT    /api/clientes/{id}         -> ClienteController@update
DELETE /api/clientes/{id}         -> ClienteController@destroy (Suspende cuenta)

GET    /api/asesores              -> AsesorController@index
POST   /api/asesores              -> AsesorController@store
PUT    /api/asesores/{id}         -> AsesorController@update
```

### Ventas y Amortizaciones
```http
GET    /api/ventas                -> NotaVentaController@index
POST   /api/ventas                -> NotaVentaController@store
GET    /api/ventas/{id}           -> NotaVentaController@show
```

### Gestión de Contratos
```http
GET    /api/contratos             -> ContratoController@index
POST   /api/contratos/{id}/gestionar -> ContratoController@gestionar (Carga PDF y estado)
GET    /api/contratos/{id}/descargar -> ContratoController@descargar (Descarga PDF del Storage)
PUT    /api/contratos/{id}/anular   -> ContratoController@anular
```

### Pagos e Ingresos
```http
GET    /api/pagos                 -> PagoController@index
POST   /api/pagos                 -> PagoController@store (Registro manual de cobro)
PUT    /api/pagos/{id}/cancelar   -> PagoController@cancelar
GET    /api/pagos/venta/{id}/resumen -> PagoController@resumenVenta
```

### Webhooks de Pasarelas (Públicos)
```http
POST   /api/pagos/webhook/libelula  -> PagoPublicoController@webhookLibelula (Público)
POST   /api/pagos/webhook/todotix   -> PagoPublicoController@webhookTodotix (Público)
```

### Reportería y Estadísticas
```http
GET    /api/dashboard/stats       -> DashboardController@getStats
POST   /api/reportes/ventas       -> ReporteController@ventas
POST   /api/reportes/morosidad    -> ReporteController@morosidad
```

---

## 8. Guía de Instalación, Configuración y Despliegue

Sigue estos pasos para desplegar el entorno de backend de desarrollo o producción:

### Requisitos Previos del Servidor
*   PHP >= 8.1 con las extensiones `openssl`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `gd`, `zip`.
*   Composer 2.x instalado globalmente.
*   Servidor web Apache o Nginx.

### Paso 1: Clonar y Descargar Dependencias
Acceder a la carpeta del backend y ejecutar:
```bash
composer install
```

### Paso 2: Configurar las Variables del Entorno
Duplicar el archivo `.env.example` y nombrarlo `.env`. Configurar las credenciales clave:
```ini
APP_NAME="System Multilider"
APP_ENV=local
APP_KEY=base64:AUTOMATIC_GENERATION_BY_ARTISAN
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=system_multilider
DB_USERNAME=root
DB_PASSWORD=

# Configuración JWT
JWT_SECRET=tu_secreto_jwt_super_seguro

# Pasarelas de Pago
LIBELULA_API_KEY=tu_key_de_libelula
TODOTIX_API_KEY=tu_key_de_todotix
```

### Paso 3: Generar la Clave de Aplicación y JWT Token
Ejecutar los siguientes comandos Artisan para inicializar las llaves criptográficas:
```bash
php artisan key:generate
php artisan jwt:secret
```

### Paso 4: Ejecutar Migraciones y Poblar Base de Datos
Para generar la base de datos relacional limpia junto con los registros maestros de prueba (roles, permisos, usuario administrador semilla y datos de demostración inmobiliarios), ejecutar:
```bash
php artisan migrate:fresh --seed
```

### Paso 5: Enlazar el Storage
Para habilitar la visualización y descarga de fotos de propiedades y contratos PDF a través de la web, crea el enlace simbólico del storage ejecutando:
```bash
php artisan storage:link
```

### Paso 6: Ejecutar Servidor Local
Para correr el backend en el entorno local de desarrollo de forma independiente, ejecuta:
```bash
php artisan serve
```
El API quedará expuesta y lista en: `http://127.0.0.1:8000/`.

---
*Fin del documento de Análisis y Documentación Técnica del Backend.*
