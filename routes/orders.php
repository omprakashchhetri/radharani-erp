<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Orders\NewOrderEntry;
use App\Livewire\Orders\StatusBoard;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Orders\ReminderQueue;

// Custom Orders — frontend only for now. No custom_orders table exists in
// the schema yet; see the note atop App\Livewire\Orders\NewOrderEntry.
Route::middleware(['auth', 'permission:orders.manage'])->prefix('orders')->name('orders.')->group(function () {
    Route::get('/new', NewOrderEntry::class)->name('new');
    Route::get('/board', StatusBoard::class)->name('board');
    Route::get('/reminders/queue', ReminderQueue::class)->name('reminders');
    Route::get('/{order}', OrderDetail::class)->name('show');
});
