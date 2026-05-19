<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\RolPermisoController;
use App\Http\Controllers\Api\RolPermisoUsuarioController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropietarioController;
use App\Http\Controllers\Api\PropiedadController;
use App\Http\Controllers\Api\AsesorController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\NotaVentaController;
use App\Http\Controllers\Api\CiudadController;
use App\Http\Controllers\Api\DistritoController;
use App\Http\Controllers\Api\SectorUrbanoController;
use App\Http\Controllers\Api\CaracteristicaController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\ImagenPropiedadController;
use App\Http\Controllers\Api\LandingController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\MetodoPagoController;
use App\Http\Controllers\Api\CuentaBancariaController;
use App\Http\Controllers\Api\MetodoPagoCuentaDefaultController;
use App\Http\Controllers\Api\PagoPublicoController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\EgresoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas Públicas (Landing Page + Portal de Pagos)
Route::prefix('public')->group(function () {
    Route::post('clientes/buscar',           [PagoPublicoController::class, 'buscarCliente']);
    Route::post('pagos/procesar',            [PagoPublicoController::class, 'procesarPago']);
    Route::get('pagos/verificar/{id}',       [PagoPublicoController::class, 'verificarEstado']);
    Route::post('pagos/callback',            [PagoPublicoController::class, 'callbackLibelula']);
    Route::post('pagos/confirmar-retorno',   [PagoPublicoController::class, 'confirmarRetorno']);
});

Route::get('/landing', [LandingController::class, 'getLandingData']);
Route::get('/landing/propiedades', [LandingController::class, 'getPropiedades']);
Route::get('/landing/propiedades/{id}', [LandingController::class, 'getPropiedad']);
Route::get('/landing/propiedades/{id}/similares', [LandingController::class, 'getSimilares']);
Route::get('/landing/ciudades', [LandingController::class, 'getCiudades']);
Route::get('/landing/distritos', [LandingController::class, 'getDistritos']);
Route::get('/landing/sectores-urbanos/{distritoId}', [LandingController::class, 'getSectoresUrbanos']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas de Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Perfil del usuario autenticado
    Route::get('/perfil', [ProfileController::class, 'show']);
    Route::put('/perfil', [ProfileController::class, 'update']);
    Route::put('/perfil/cambiar-password', [ProfileController::class, 'changePassword']);

    Route::apiResource('usuarios', UserController::class);
    Route::get('usuarios/{id}/asignaciones', [UserController::class, 'getAsignaciones']);
    Route::post('usuarios/{id}/asignaciones/sync', [UserController::class, 'syncAsignaciones']);

    Route::apiResource('roles', RolController::class);
    Route::get('roles/{id}/permisos', [RolController::class, 'getPermisos']);
    Route::post('roles/{id}/permisos/sync', [RolController::class, 'syncPermisos']);

    Route::apiResource('permisos', PermisoController::class);
    
    Route::apiResource('asignar-permisos', RolPermisoController::class);

    Route::apiResource('propietarios', PropietarioController::class);
    Route::apiResource('ubicaciones', UbicacionController::class);
    Route::apiResource('propiedades', PropiedadController::class);
    Route::post('propiedades/{id}/caracteristicas/sync', [PropiedadController::class, 'syncCaracteristicas']);
    
    // Gestión de Imágenes de Propiedades
    Route::post('propiedades/{id}/imagenes', [ImagenPropiedadController::class, 'store']);
    Route::delete('imagenes-propiedades/{id}', [ImagenPropiedadController::class, 'destroy']);
    Route::patch('imagenes-propiedades/{id}/principal', [ImagenPropiedadController::class, 'setPrincipal']);

    Route::apiResource('asesores', AsesorController::class);
    Route::apiResource('clientes', ClienteController::class);
    Route::apiResource('ciudades', CiudadController::class);
    Route::apiResource('distritos', DistritoController::class);
    Route::get('sectores-urbanos/por-distrito/{distritoId}', [SectorUrbanoController::class, 'porDistrito']);
    Route::apiResource('sectores-urbanos', SectorUrbanoController::class);
    Route::apiResource('caracteristicas', CaracteristicaController::class);
    Route::apiResource('metodos-pago', MetodoPagoController::class);
    Route::apiResource('cuentas-bancarias', CuentaBancariaController::class);
    Route::apiResource('mapeo-metodos-cuentas', MetodoPagoCuentaDefaultController::class);
    Route::get('mapeo-metodos-cuentas/obtener-cuenta/{metodoId}', [MetodoPagoCuentaDefaultController::class, 'obtenerCuentaPorMetodo']);

    // Configuración de Empresa
    Route::post('empresa', [LandingController::class, 'updateEmpresa'])->middleware('permission:acceso_empresa');
    
    Route::apiResource('ventas', NotaVentaController::class)->except(['destroy', 'update']);
    Route::put('ventas/{id}/anular', [NotaVentaController::class, 'anular']);

    // Módulo de Contratos
    Route::middleware('permission:acceso_contratos')->group(function () {
        Route::get('contratos', [ContratoController::class, 'index']);
        Route::post('contratos/{id}/gestionar', [ContratoController::class, 'gestionar']);
        Route::put('contratos/{id}/anular', [ContratoController::class, 'anular']);
        Route::get('contratos/{id}/descargar', [ContratoController::class, 'descargar']);
    });

    // Gestión de Pagos
    Route::middleware('permission:acceso_pagos')->group(function () {
        Route::post('pagos/bulk', [PagoController::class, 'storeBulk']);
        Route::apiResource('pagos', PagoController::class);
        Route::get('pagos/venta/{notaVentaId}/resumen', [PagoController::class, 'pagosPorVenta']);
        Route::get('pagos/cliente/{clienteId}/resumen', [PagoController::class, 'resumenPorCliente']);
        Route::put('pagos/{id}/cancelar', [PagoController::class, 'cancelar']);
        // Route::post('pagos/reportes/periodo', [PagoController::class, 'reportePeriodo']);
        Route::get('pagos/pendientes/listar', [PagoController::class, 'pagosPendientes']);
        Route::post('pagos/{id}/procesar', [PagoController::class, 'procesarPagoPendiente']);
    });
    
    // Ruta de reportes podría tener un permiso más específico si se desea,
    // pero por ahora lo dejamos bajo acceso_pagos o libre para admin
    Route::post('pagos/reportes/periodo', [PagoController::class, 'reportePeriodo'])->middleware('permission:acceso_reportes_pagos');

    // Módulo de Finanzas: Ingresos
    Route::middleware('permission:acceso_ingresos')->group(function () {
        Route::get('ingresos', [IngresoController::class, 'index']);
        Route::post('ingresos', [IngresoController::class, 'store']);
        Route::get('ingresos/resumen', [IngresoController::class, 'resumen']);
        Route::get('ingresos/{id}', [IngresoController::class, 'show']);
        Route::put('ingresos/{id}', [IngresoController::class, 'update']);
        Route::put('ingresos/{id}/anular', [IngresoController::class, 'anular']);
    });

    // Módulo de Finanzas: Egresos
    Route::middleware('permission:acceso_egresos')->group(function () {
        Route::get('egresos', [EgresoController::class, 'index']);
        Route::post('egresos', [EgresoController::class, 'store']);
        Route::get('egresos/resumen', [EgresoController::class, 'resumen']);
        Route::get('egresos/{id}', [EgresoController::class, 'show']);
        Route::put('egresos/{id}', [EgresoController::class, 'update']);
        Route::put('egresos/{id}/pagar', [EgresoController::class, 'pagar']);
        Route::put('egresos/{id}/anular', [EgresoController::class, 'anular']);
    });

});

