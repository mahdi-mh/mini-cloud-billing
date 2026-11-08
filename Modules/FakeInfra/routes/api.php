<?php

use Illuminate\Support\Facades\Route;
use Modules\FakeInfra\Http\Controllers\InfraServerController;

Route::get('servers', [InfraServerController::class, 'index']);
Route::get('servers/{token}', [InfraServerController::class, 'show']);
Route::put('servers/{token}', [InfraServerController::class, 'store']);
Route::post('servers/{token}/stop', [InfraServerController::class, 'stop']);
Route::delete('servers/{token}', [InfraServerController::class, 'destroy']);
