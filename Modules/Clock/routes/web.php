<?php

use Illuminate\Support\Facades\Route;
use Modules\Clock\Http\Controllers\ClockController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('clocks', ClockController::class)->names('clock');
});
