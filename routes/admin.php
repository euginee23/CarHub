<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('owner-applications', 'pages::admin.owner-applications')->name('owner-applications');
    Route::livewire('id-reviews', 'pages::admin.id-reviews')->name('id-reviews');
    Route::livewire('users', 'pages::admin.users')->name('users');
    Route::livewire('bookings', 'pages::admin.bookings.index')->name('bookings.index');
    Route::livewire('bookings/{booking}', 'pages::admin.bookings.show')->name('bookings.show');
});
