<?php

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

// SPA catch-all: serve the React app for all non-API routes
Route::get('/{any}', fn () => view('app'))
    ->where('any', '^(?!api|storage|broadcasting).*$')
    ->name('spa');
