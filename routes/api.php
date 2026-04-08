<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\RolPermisoController;
use App\Http\Controllers\Api\RolPermisoUsuarioController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('usuarios', UserController::class);
Route::get('usuarios/{id}/asignaciones', [UserController::class, 'getAsignaciones']);
Route::post('usuarios/{id}/asignaciones/sync', [UserController::class, 'syncAsignaciones']);

Route::apiResource('roles', RolController::class);
Route::get('roles/{id}/permisos', [RolController::class, 'getPermisos']);
Route::post('roles/{id}/permisos/sync', [RolController::class, 'syncPermisos']);

Route::apiResource('permisos', PermisoController::class);

Route::apiResource('asignar-permisos', RolPermisoController::class)->except(['update']);
Route::apiResource('asignar-usuarios', RolPermisoUsuarioController::class)->except(['update']);
