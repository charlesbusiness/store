<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

// Public auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    // Items
    Route::apiResource('items', ItemController::class);
    
    // Units
    Route::apiResource('units', UnitController::class);
    
    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchases/form', [PurchaseController::class, 'form']);
    Route::post('/purchases', [PurchaseController::class, 'store'])
        ->name('purchases.store')
        ->defaults('type', \App\Enums\RecordType::Purchase->value);
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy']);
    Route::delete('/purchases/{purchase}/lines/{line}', [PurchaseController::class, 'destroyLine']);
    Route::patch('/purchases/{purchase}/lines/{line}', [PurchaseController::class, 'updateLine']);
    
    // Sales
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/form', [SaleController::class, 'form']);
    Route::post('/sales', [SaleController::class, 'store'])
        ->name('sales.store')
        ->defaults('type', \App\Enums\RecordType::Sale->value);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    Route::delete('/sales/{sale}/lines/{line}', [SaleController::class, 'destroyLine']);
    Route::patch('/sales/{sale}/lines/{line}', [SaleController::class, 'updateLine']);
    Route::patch('/sales/{sale}/paid', [SaleController::class, 'markPaid']);
    
    // Reports
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/trend', [ReportController::class, 'trend']);
    
    // Stock
    Route::get('/stock', [StockController::class, 'index']);
    
    // Broadcast auth
    Route::post('/broadcasting/auth', function (Request $request) {
        return broadcast($request->all());
    });
    
    // File uploads
    Route::post('/upload', [UploadController::class, 'store']);
    Route::delete('/upload', [UploadController::class, 'destroy']);
});

// Broadcast auth for private channels
Broadcast::routes(['middleware' => 'auth:sanctum']);
