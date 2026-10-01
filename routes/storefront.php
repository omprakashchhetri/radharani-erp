<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

// Public website — no auth. The home page is the site's main entry point;
// staff and customers sign in from /sign-in (linked in the footer).
Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'shop'])->name('storefront.catalog');
Route::get('/shop/{slug}', [StorefrontController::class, 'product'])->name('storefront.product');

Route::get('/sign-in', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
})->name('sign-in');
