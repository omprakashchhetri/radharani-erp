<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Movement\VaultCounterMove;
use App\Livewire\Movement\KarigarDispatch;
use App\Livewire\Movement\KarigarReturn;
use App\Livewire\Movement\HallmarkDispatch;
use App\Livewire\Movement\HallmarkReturn;
use App\Livewire\Movement\CustomPurposeMove;
use App\Livewire\Movement\PendingReviewQueue;
use App\Livewire\Movement\MovementLog;

// Movements module — every stock-location change, append-only downstream
// via the movements table. See CLAUDE.md non-negotiable rule 1.
Route::middleware(['auth'])->prefix('movements')->name('movements.')->group(function () {
    Route::get('/vault-counter', VaultCounterMove::class)->name('vault-counter');
    Route::get('/karigar-dispatch', KarigarDispatch::class)->name('karigar-dispatch');
    Route::get('/karigar-return', KarigarReturn::class)->name('karigar-return');
    Route::get('/hallmark-dispatch', HallmarkDispatch::class)->name('hallmark-dispatch');
    Route::get('/hallmark-return', HallmarkReturn::class)->name('hallmark-return');
    Route::get('/custom-purpose', CustomPurposeMove::class)->name('custom-purpose');
    // #9: only admins close pending items into stock.
    Route::get('/pending-review', PendingReviewQueue::class)->middleware('permission:movement.approve')->name('pending-review');
    Route::get('/log', MovementLog::class)->name('log');
});
