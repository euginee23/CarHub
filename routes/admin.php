<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', 'admin/owner-applications')->name('index');

    Route::livewire('owner-applications', 'pages::admin.owner-applications')->name('owner-applications');
});
