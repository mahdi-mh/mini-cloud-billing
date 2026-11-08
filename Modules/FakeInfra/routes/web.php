<?php

use Illuminate\Support\Facades\Route;
use Modules\FakeInfra\Http\Controllers\FakeInfraController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('fakeinfras', FakeInfraController::class)->names('fakeinfra');
});
