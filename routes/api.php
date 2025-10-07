<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VeterinariaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PruebaController;

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

// Rutas públicas de autenticación
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Rutas públicas para veterinarias
Route::prefix('veterinarias')->group(function () {
    // Obtener países válidos (público)
    Route::get('/paises', [VeterinariaController::class, 'getPaises']);
    
    // Registro de veterinaria (público)
    Route::post('/registro', [VeterinariaController::class, 'store']);
});

// Rutas protegidas que requieren autenticación con token
Route::middleware('auth:api')->group(function () {
    
    // Rutas de autenticación protegidas
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
    
    // Rutas protegidas para veterinarias
    Route::prefix('veterinarias')->group(function () {
        // CRUD completo
        Route::get('/', [VeterinariaController::class, 'index']);
        Route::get('/{id}', [VeterinariaController::class, ']);
        Route::put('/{id}', [VeterinariaController::class, 'update']);
        Route::delete('/{id}', [VeterinariaController::class, 'destroy']);
    });

    // Rutas protegidas para usuarios
    Route::prefix('users')->group(function () {
        // CRUD completo
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    // Rutas protegidas para pruebas (CRU)
    Route::prefix('pruebas')->group(function () {
        Route::get('/', [PruebaController::class, 'index']);
        Route::get('/{id}', [PruebaController::class, 'show']);
        Route::post('/', [PruebaController::class, 'store']);
        Route::put('/{id}', [PruebaController::class, 'update']);
        Route::delete('/{id}', [PruebaController::class, 'destroy']);
    });
});