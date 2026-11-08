<?php

use Illuminate\Support\Facades\Route;
use Modules\Servers\Livewire\ServerIndex;
use Modules\Servers\Livewire\ServerShow;

Route::middleware('auth')->group(function () {
    Route::livewire('/servers', ServerIndex::class)
        ->middleware('strict-query:q,status,customer,sort,dir,page')
        ->name('servers.index');

    Route::livewire('/servers/{server}', ServerShow::class)->name('servers.show');
});
