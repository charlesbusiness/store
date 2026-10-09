<?php

use App\Enums\RecordType;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stockly
|--------------------------------------------------------------------------
|
| One shared ledger behind a login. Pages are server rendered; the only
| interactivity is the line-item builder, which posts an ordinary form.
|
*/

Route::middleware('guest')->group(function () {
    // Named "login", which is the name the framework's auth middleware
    // redirects guests to.
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/', 'reports')->name('home');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('sales', [SaleController::class, 'create'])->name('sales.create');
    Route::post('sales', [SaleController::class, 'store'])
        ->defaults('type', RecordType::Sale->value)
        ->name('sales.store');

    Route::get('purchases', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])
        ->defaults('type', RecordType::Purchase->value)
        ->name('purchases.store');

    Route::get('stock', [StockController::class, 'index'])->name('stock.index');

    // One controller for both tabs, so a purchase line cannot be deleted
    // through the sales URL.
    Route::delete('records/lines/{line}', [LineController::class, 'destroy'])->name('records.lines.destroy');
    Route::delete('records/{transaction}', [LineController::class, 'destroyTransaction'])->name('records.destroy');
    Route::patch('records/{transaction}/paid', [LineController::class, 'markPaid'])->name('records.paid');

    Route::resource('items', ItemController::class);
    Route::resource('units', UnitController::class);
});
