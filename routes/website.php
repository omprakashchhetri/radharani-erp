<?php

use App\Livewire\Website\CategoryManager;
use App\Livewire\Website\CollectionManager;
use App\Livewire\Website\ListingManager;
use App\Livewire\Website\SiteSettings;
use Illuminate\Support\Facades\Route;

// Staff side of the public website: what it lists and how it's organised.
Route::middleware(['auth', 'permission:website.manage'])->prefix('website')->name('website.')->group(function () {
    Route::get('/listings', ListingManager::class)->name('listings');
    Route::get('/categories', CategoryManager::class)->name('categories');
    Route::get('/collections', CollectionManager::class)->name('collections');
    Route::get('/settings', SiteSettings::class)->name('settings');
});
