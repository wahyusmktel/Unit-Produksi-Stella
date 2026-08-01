<?php

use App\Http\Controllers\AdminUp\ProductCategoryController;
use App\Http\Controllers\AdminUp\ProductController;
use App\Http\Controllers\Auth\SisfoSsoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SisfoSsoController::class, 'login'])->name('login');
    Route::get('/auth/sisfo', [SisfoSsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sisfo/callback', [SisfoSsoController::class, 'callback'])->name('sso.callback');
});

Route::middleware('auth')->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
    Route::post('/logout', [SisfoSsoController::class, 'logout'])->name('logout');

    Route::middleware('adminup')->prefix('adminup')->name('adminup.')->group(function () {
        Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::delete('product-categories/{category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
    });
});
