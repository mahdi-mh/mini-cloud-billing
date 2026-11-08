<?php

use Illuminate\Support\Facades\Route;
use Modules\Network\Livewire\IpIndex;

Route::middleware('auth')->group(function () {
    Route::livewire('/ips', IpIndex::class)
        ->middleware('strict-query:q,state,page')
        ->name('ips.index');
});
