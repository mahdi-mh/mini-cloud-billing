<?php

use Illuminate\Support\Facades\Route;
use Modules\Network\Http\Controllers\NetworkController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('networks', NetworkController::class)->names('network');
});
