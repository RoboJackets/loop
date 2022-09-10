<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentDownloadController;
use App\Models\DocuSignEnvelope;
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

Route::bind('uuid', static fn (string $uuid): DocuSignEnvelope => DocuSignEnvelope::fromEnvelopeUuid($uuid));

Route::get('/v1/document/{uuid}', DocumentDownloadController::class)
    ->name('document.download')
    ->middleware(['signed']);

Route::webhooks('/v1/postmark/inbound', 'postmark-inbound');

Route::webhooks('/v1/sensible', 'sensible')
    ->middleware(['signed']);
