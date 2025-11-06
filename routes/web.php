<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordResetController;

Route::get('/', function () {
    return view('welcome');
});

// Vista para restablecimiento de contraseña por correo
Route::get('/reset-password', function () {
    return view('auth.reset_password');
})->name('password.reset.view');

// Opción alternativa: permitir POST web que reutiliza el controlador API
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset.submit');
