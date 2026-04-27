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
use App\Http\Controllers\Api\ManzanoController;
use App\Http\Controllers\Api\UbicacionController;
use App\Http\Controllers\Api\PropiedadController;
use App\Http\Controllers\Api\AsesorController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\NotaVentaController;

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


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas de Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('usuarios', \App\Http\Controllers\Api\UserController::class);
    Route::get('usuarios/{id}/asignaciones', [\App\Http\Controllers\Api\UserController::class, 'getAsignaciones']);
    Route::post('usuarios/{id}/asignaciones/sync', [\App\Http\Controllers\Api\UserController::class, 'syncAsignaciones']);

    Route::apiResource('roles', \App\Http\Controllers\Api\RolController::class);
    Route::get('roles/{id}/permisos', [\App\Http\Controllers\Api\RolController::class, 'getPermisos']);
    Route::post('roles/{id}/permisos/sync', [\App\Http\Controllers\Api\RolController::class, 'syncPermisos']);

    Route::apiResource('permisos', \App\Http\Controllers\Api\PermisoController::class);
    
    Route::apiResource('asignar-permisos', \App\Http\Controllers\Api\RolPermisoController::class);

    Route::apiResource('propietarios', PropietarioController::class);
    Route::apiResource('manzanos', ManzanoController::class);
    Route::apiResource('ubicaciones', UbicacionController::class);
    Route::apiResource('propiedades', PropiedadController::class);
    Route::apiResource('asesores', AsesorController::class);
    Route::apiResource('clientes', ClienteController::class);

    Route::apiResource('ventas', NotaVentaController::class)->except(['destroy', 'update']);
    Route::put('ventas/{id}/anular', [NotaVentaController::class, 'anular']);
});