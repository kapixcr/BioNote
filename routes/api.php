<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VeterinariaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PruebaController;
use App\Http\Controllers\AdminAuthController;

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
    Route::post('/login', [AuthController::class, 'login']); // veterinaria
});

// NUEVO: rutas públicas de autenticación para admin
Route::prefix('auth/admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// Rutas públicas para veterinarias
Route::prefix('veterinarias')->group(function () {
    Route::get('/paises', [VeterinariaController::class, 'getPaises']);
    Route::post('/registro', [VeterinariaController::class, 'store']);
});

// Rutas protegidas que requieren autenticación con token (admin o veterinaria)
Route::middleware('auth:api,admin')->group(function () {
    // Rutas protegidas de veterinaria (token de veterinaria)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Rutas protegidas para veterinarias (comportamiento depende del rol/guard)
    Route::prefix('veterinarias')->group(function () {
        Route::get('/', [VeterinariaController::class, 'index']);
        Route::get('/{id}', [VeterinariaController::class, 'show']);
        Route::put('/{id}', [VeterinariaController::class, 'update']);
        // Ruta POST alternativa para actualización con archivos (method spoofing)
        Route::post('/{id}/update', [VeterinariaController::class, 'update']);
        Route::delete('/{id}', [VeterinariaController::class, 'destroy']);
    });

    // NUEVO: rutas protegidas de autenticación para admin (solo admin guard)
    Route::prefix('auth/admin')->middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
    });
});

// Rutas protegidas exclusivamente para admin (users CRUD)
Route::prefix('users')->middleware('auth:admin')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
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
    Route::get('/{id}', [PruebaController::class, 'show'])->whereNumber('id');
    Route::get('/my-pruebas', [PruebaController::class, 'myPruebas'])->middleware('auth:api,admin');
    Route::post('/', [PruebaController::class, 'store']);
    Route::put('/{id}', [PruebaController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [PruebaController::class, 'destroy'])->whereNumber('id');
});