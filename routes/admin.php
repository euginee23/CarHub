<?php

use App\Http\Controllers\Admin\ReportExportController;
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
    Route::livewire('payments', 'pages::admin.payments.index')->name('payments.index');
    Route::livewire('vehicles', 'pages::admin.vehicles.index')->name('vehicles.index');
    Route::livewire('activity', 'pages::admin.activity')->name('activity');
    Route::livewire('reports', 'pages::admin.reports')->name('reports');
    Route::get('reports/export/{report}', ReportExportController::class)->name('reports.export');
});

// GPS tracker test page; switched off by config('carhub.tracking.test_page').
Route::livewire('test-track-gps-map', 'pages::admin.tracking-test')
    ->middleware(['auth', 'verified', 'can:access-admin'])
    ->name('tracking.test');
