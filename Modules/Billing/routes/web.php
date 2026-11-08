<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('billings', BillingController::class)->names('billing');
});
