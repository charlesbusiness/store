<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Routes
|--------------------------------------------------------------------------
|
| All frontend routes are handled by the React SPA.
| API routes are in routes/api.php
|
*/

// Public, signed receipt link. It must stay ahead of the SPA catch-all.
Route::get('/receipts/sales/{sale}', [ReceiptController::class, 'show'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('receipts.public');

// SPA catch-all: serve the React app for all non-API routes
Route::get('/{any}', fn () => view('app'))
    ->where('any', '^(?!api|storage|broadcasting).*$')
    ->name('spa');
