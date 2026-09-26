<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
*/

Route::view('/', 'pages::marketing.home')->name('home');

Route::livewire('vehicles', 'pages::marketing.browse')->name('vehicles.index');
Route::get('vehicles/{vehicle:slug}', [VehicleController::class, 'show'])->name('vehicles.show');

Route::view('how-it-works', 'pages::marketing.how-it-works')->name('how-it-works');
Route::view('about', 'pages::marketing.about')->name('about');
Route::livewire('contact', 'pages::marketing.contact')->name('contact');
Route::view('faq', 'pages::marketing.faq')->name('faq');
Route::view('terms', 'pages::marketing.terms')->name('terms');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::livewire('owner/apply', 'pages::owner.apply')->name('owner.apply');

    Route::middleware('can:list-vehicles')->prefix('owner/vehicles')->name('owner.vehicles.')->group(function () {
        Route::livewire('/', 'pages::owner.vehicles.index')->name('index');
        Route::livewire('create', 'pages::owner.vehicles.form')->name('create');
        Route::livewire('{vehicle}/edit', 'pages::owner.vehicles.form')->name('edit');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
