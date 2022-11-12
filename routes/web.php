<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\QuickBooksAuthenticationController;

Route::prefix('quickbooks')->middleware(['can:access-quickbooks'])->group(static function (): void {
    Route::get('/', [QuickBooksAuthenticationController::class, 'redirectToQuickBooks'])->name('quickbooks.start');
    
    Route::get('complete', [QuickBooksAuthenticationController::class, 'handleCallback'])->name('quickbooks.complete');
});
