<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExternalCommitteeMemberController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/v1/document/{envelope}', DocumentDownloadController::class)
    ->name('document.download')
    ->middleware(['signed']);

Route::prefix('/v1/workday/')->middleware(['auth:sanctum', 'can:access-workday'])->group(static function () {
    Route::post('external-committee-members', ExternalCommitteeMemberController::class);

    Route::post('workers', WorkerController::class);
});

Route::webhooks('/v1/postmark/inbound', 'postmark-inbound');

Route::webhooks('/v1/sensible', 'sensible')
    ->middleware(['signed']);
