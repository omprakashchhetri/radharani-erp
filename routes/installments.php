<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Installments\SchemeEnrolment;
use App\Livewire\Installments\MonthlyPaymentStatus;
use App\Livewire\Installments\SchemeList;
use App\Livewire\Installments\SchemeImport;

Route::middleware(['auth'])->prefix('installments')->group(function () {
    Route::get('/enrol', SchemeEnrolment::class)
        ->middleware('permission:customer.manage')->name('installments.enrol');

    Route::get('/import', SchemeImport::class)
        ->middleware('permission:customer.manage')->name('installments.import');

    Route::get('/monthly-status', MonthlyPaymentStatus::class)
        ->middleware('permission:customer.manage')->name('installments.monthly-status');

    Route::get('/', SchemeList::class)
        ->middleware('permission:customer.manage')->name('installments.list');
});
