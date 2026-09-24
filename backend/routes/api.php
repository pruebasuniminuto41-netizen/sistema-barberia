<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\SedeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Rutas públicas de autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de usuario
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rutas de sedes (acceso para todos los roles autenticados)
    Route::get('/sedes', [SedeController::class, 'index']);
    Route::get('/sedes/{id}', [SedeController::class, 'show']);
    Route::get('/sedes/{id}/barberos', [SedeController::class, 'barberos']);
    Route::get('/sedes/{id}/supervisores', [SedeController::class, 'supervisores']);

    // Rutas de servicios (acceso para todos los roles autenticados)
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);

    // Rutas de productos (acceso para todos los roles autenticados)
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos/{id}', [ProductoController::class, 'show']);

    // Rutas de citas (acceso para todos los roles autenticados)
    Route::get('/citas', [CitaController::class, 'index']);
    Route::get('/citas/{id}', [CitaController::class, 'show']);
    Route::post('/citas', [CitaController::class, 'store']);
    Route::put('/citas/{id}', [CitaController::class, 'update']);

    // Rutas específicas por rol
    Route::middleware('role:admin')->group(function () {
        // CRUD completo para admin
        Route::post('/sedes', [SedeController::class, 'store']);
        Route::put('/sedes/{id}', [SedeController::class, 'update']);
        Route::delete('/sedes/{id}', [SedeController::class, 'destroy']);
        Route::post('/sedes/{id}/barberos', [SedeController::class, 'agregarBarbero']);

        Route::post('/servicios', [ServicioController::class, 'store']);
        Route::put('/servicios/{id}', [ServicioController::class, 'update']);
        Route::delete('/servicios/{id}', [ServicioController::class, 'destroy']);

        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);

        // Gestión de usuarios
        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
        Route::put('/users/{id}', [AuthController::class, 'update']);
        Route::delete('/users/{id}', [AuthController::class, 'destroy']);

        // Métricas y reportes
        Route::get('/admin/metricas', [CitaController::class, 'metricas']);

        // Validación de comprobantes
        Route::post('/citas/{id}/validar-comprobante', [CitaController::class, 'validarComprobante']);
    });

    Route::middleware('role:supervisor')->group(function () {
        // CRUD productos de su sede
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);

        // Agregar productos a citas
        Route::post('/citas/{id}/productos', [CitaController::class, 'agregarProductos']);

        // Validación de comprobantes
        Route::post('/citas/{id}/validar-comprobante', [CitaController::class, 'validarComprobante']);
    });

    Route::middleware('role:barbero')->group(function () {
        // Actualizar estado de sus citas
        Route::put('/citas/{id}', [CitaController::class, 'update']);
    });
});
