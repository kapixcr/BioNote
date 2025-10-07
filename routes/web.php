<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas básicas para que la vista welcome funcione
Route::get('/login', function () {
    return redirect('/'); // O redirigir a tu frontend
})->name('login');

Route::get('/register', function () {
    return redirect('/'); // O redirigir a tu frontend  
})->name('register');

Route::get('/dashboard', function () {
    return redirect('/'); // O redirigir a tu frontend
})->name('dashboard');
