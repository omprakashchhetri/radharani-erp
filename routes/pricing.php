<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Pricing\DailyRateEntry;
use App\Livewire\Pricing\RateHistoryLog;
use App\Livewire\Pricing\MakingChargeConfig;
use App\Livewire\Pricing\DiscountRulesManager;
use App\Livewire\Pricing\AdditionalChargesConfig;

Route::middleware(['auth'])->prefix('pricing')->name('pricing.')->group(function () {
    Route::get('/rates', DailyRateEntry::class)->middleware('permission:rate.update')->name('rates');
    Route::get('/rates/history', RateHistoryLog::class)->middleware('permission:rate.update')->name('rates.history');
    Route::get('/making-charges', MakingChargeConfig::class)->middleware('permission:rate.update')->name('making-charges');
    Route::get('/discounts', DiscountRulesManager::class)->middleware('permission:discount.manage')->name('discounts');
    Route::get('/additional-charges', AdditionalChargesConfig::class)->middleware('permission:rate.update')->name('additional-charges');
});
