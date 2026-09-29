<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Reports\DailyLogbook;
use App\Livewire\Reports\StaffActivityReport;
use App\Livewire\Reports\LocationReport;

Route::middleware(['auth'])->prefix('reports')->group(function () {
    Route::get('/logbook', DailyLogbook::class)
        ->middleware('permission:audit.view')->name('reports.logbook');

    Route::get('/staff-activity', StaffActivityReport::class)
        ->middleware('permission:audit.view')->name('reports.staff-activity');

    Route::get('/location', LocationReport::class)
        ->middleware('permission:audit.view')->name('reports.location');
});
