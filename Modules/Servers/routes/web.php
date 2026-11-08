<?php

use Illuminate\Support\Facades\Route;
use Modules\Servers\Http\Controllers\ServersController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('servers', ServersController::class)->names('servers');
});
