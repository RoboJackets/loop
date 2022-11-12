<?php

declare(strict_types=1);

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\ExpenseReportLineController;
use App\Http\Controllers\ExternalCommitteeMemberController;
use App\Http\Controllers\WorkdaySyncController;
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

    Route::resource('expense-reports', ExpenseReportController::class)->only('store', 'update');

    Route::put('expense-reports/{expense_report}/lines/{line}', ExpenseReportLineController::class)->scopeBindings();

    Route::post('attachments/{attachment}', AttachmentController::class);

    Route::get('sync', [WorkdaySyncController::class, 'getResourcesToSync']);
    Route::post('sync', [WorkdaySyncController::class, 'syncComplete']);
});

Route::webhooks('/v1/postmark/inbound', 'postmark-inbound');

Route::webhooks('/v1/sensible', 'sensible')
    ->middleware(['signed']);
