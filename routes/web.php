<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Payments\PaymentReturnController;
use App\Http\Controllers\Payments\PayMongoWebhookController;
use App\Http\Controllers\Payments\SimulatedCheckoutController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
*/

Route::view('/', 'pages::marketing.home')->name('home');

Route::livewire('vehicles', 'pages::marketing.browse')->name('vehicles.index');
Route::livewire('compare', 'pages::marketing.compare')->name('vehicles.compare');
Route::get('vehicles/{vehicle:slug}', [VehicleController::class, 'show'])->name('vehicles.show');

Route::view('how-it-works', 'pages::marketing.how-it-works')->name('how-it-works');
Route::view('about', 'pages::marketing.about')->name('about');
Route::livewire('contact', 'pages::marketing.contact')->name('contact');
Route::view('faq', 'pages::marketing.faq')->name('faq');
Route::view('terms', 'pages::marketing.terms')->name('terms');

/*
|--------------------------------------------------------------------------
| Links from before rental companies replaced vehicle owners
|--------------------------------------------------------------------------
|
| Emails and bookmarks still point at the old owner URLs.
|
*/

Route::permanentRedirect('owner/{path?}', '/company/{path?}')->where('path', '.*');
Route::permanentRedirect('admin/owner-applications', '/admin/company-applications');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Shared with administrators, who open these while reviewing.
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('bookings/{booking}/contract', [RentalContractController::class, 'show'])->name('bookings.contract');
    Route::livewire('bookings/{booking}/tracking', 'pages::tracking.show')->name('bookings.tracking');

    // Renter accounts: finding, booking, and taking trips, once two IDs are approved.
    Route::middleware(['role:renter', 'identity.verified'])->group(function () {
        Route::livewire('renter', 'pages::renter.dashboard')->name('renter.dashboard');

        Route::livewire('trips', 'pages::trips.index')->name('trips.index');
        Route::livewire('trips/{booking}', 'pages::trips.show')->name('trips.show');
        Route::livewire('trips/{booking}/checkout', 'pages::trips.checkout')->name('trips.checkout');

        Route::get('payments/{payment}/return', PaymentReturnController::class)->name('payments.return');
        Route::get('payments/{payment}/simulated', [SimulatedCheckoutController::class, 'show'])->middleware('signed')->name('payments.simulated.show');
        Route::post('payments/{payment}/simulated', [SimulatedCheckoutController::class, 'complete'])->name('payments.simulated.complete');
    });

    // Rental company accounts: verification, then listing vehicles and handling bookings.
    Route::middleware('role:company')->group(function () {
        Route::livewire('company/apply', 'pages::company.apply')->name('company.apply');

        Route::middleware('can:list-vehicles')->group(function () {
            Route::livewire('company', 'pages::company.dashboard')->name('company.dashboard');

            Route::prefix('company/vehicles')->name('company.vehicles.')->group(function () {
                Route::livewire('/', 'pages::company.vehicles.index')->name('index');
                Route::livewire('create', 'pages::company.vehicles.form')->name('create');
                Route::livewire('{vehicle}/edit', 'pages::company.vehicles.form')->name('edit');
            });

            Route::prefix('company/bookings')->name('company.bookings.')->group(function () {
                Route::livewire('/', 'pages::company.bookings.index')->name('index');
                Route::livewire('{booking}', 'pages::company.bookings.show')->name('show');
            });
        });
    });
});

/*
|--------------------------------------------------------------------------
| Payment gateway webhooks (signature-verified, CSRF-exempt)
|--------------------------------------------------------------------------
*/

Route::post('webhooks/paymongo', PayMongoWebhookController::class)->name('webhooks.paymongo');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
