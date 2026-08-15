<?php

use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
*/

Route::view('/', 'pages::marketing.home')->name('home');

Route::livewire('vehicles', 'pages::marketing.browse')->name('vehicles.index');
Route::get('vehicles/{slug}', [VehicleController::class, 'show'])->name('vehicles.show');

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
});

require __DIR__.'/settings.php';
