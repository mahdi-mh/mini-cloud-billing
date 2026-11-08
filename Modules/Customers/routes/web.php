<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Livewire\CustomerIndex;
use Modules\Customers\Livewire\CustomerShow;

Route::middleware('auth')->group(function () {
    Route::livewire('/customers', CustomerIndex::class)
        ->middleware('strict-query:q,page')
        ->name('customers.index');

    Route::livewire('/customers/{customer}', CustomerShow::class)->name('customers.show');
});
