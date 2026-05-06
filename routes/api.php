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
use App\Http\Controllers\Api\ZonaController;
use App\Http\Controllers\Api\CaracteristicaController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\ImagenPropiedadController;
use App\Http\Controllers\Api\LandingController;
use App\Http\Controllers\Api\PagoController;

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

// Rutas Públicas (Landing Page)
Route::get('/landing', [LandingController::class, 'getLandingData']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas de Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
    Route::apiResource('zonas', ZonaController::class);
    Route::apiResource('caracteristicas', CaracteristicaController::class);
    
    // Configuración de Empresa
    Route::post('empresa', [LandingController::class, 'updateEmpresa'])->middleware('permission:acceso_empresa');
    
    Route::apiResource('ventas', NotaVentaController::class)->except(['destroy', 'update']);
    Route::put('ventas/{id}/anular', [NotaVentaController::class, 'anular']);

    // Gestión de Pagos
    Route::apiResource('pagos', PagoController::class);
    Route::get('pagos/venta/{notaVentaId}/resumen', [PagoController::class, 'pagosPorVenta']);
    Route::get('pagos/cliente/{clienteId}/resumen', [PagoController::class, 'resumenPorCliente']);
    Route::put('pagos/{id}/cancelar', [PagoController::class, 'cancelar']);
    Route::post('pagos/reportes/periodo', [PagoController::class, 'reportePeriodo']);

});

