<?php

use App\Enums\RecordType;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/verify-reset-otp', [AuthController::class, 'verifyPasswordOtp']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/accept-invite', [AuthController::class, 'acceptInvitation']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');

// Protected API routes
Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    Route::get('/tenant', [TenantController::class, 'current']);
    Route::patch('/tenant', [TenantController::class, 'update']);
    Route::get('/tenant/members', [TenantController::class, 'members']);
    Route::post('/tenant/members', [TenantController::class, 'storeMember']);
    Route::patch('/tenant/members/{user}', [TenantController::class, 'updateMember']);
    Route::put('/tenant/members/{user}/branches', [TenantController::class, 'updateMemberBranches']);
    Route::post('/tenant/members/{user}/invite', [TenantController::class, 'resendInvite']);
    Route::apiResource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/transfers', [StockTransferController::class, 'index']);
    Route::post('/transfers', [StockTransferController::class, 'store']);
    // Items
    Route::apiResource('items', ItemController::class);

    // Units
    Route::apiResource('units', UnitController::class)->only(['index', 'show']);

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchases/form', [PurchaseController::class, 'form']);
    Route::post('/purchases', [PurchaseController::class, 'store'])
        ->name('purchases.store')
        ->defaults('type', RecordType::Purchase->value);
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy']);
    Route::delete('/purchases/{purchase}/lines/{line}', [PurchaseController::class, 'destroyLine']);
    Route::patch('/purchases/{purchase}/lines/{line}', [PurchaseController::class, 'updateLine']);

    // Sales
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/form', [SaleController::class, 'form']);
    Route::post('/sales', [SaleController::class, 'store'])
        ->name('sales.store')
        ->defaults('type', RecordType::Sale->value);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'download'])->name('sales.receipt');
    Route::get('/sales/{sale}/receipt-link', [ReceiptController::class, 'shareLink'])->name('sales.receipt-link');
    Route::patch('/sales/{sale}/customer', [SaleController::class, 'updateCustomer']);
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    Route::delete('/sales/{sale}/lines/{line}', [SaleController::class, 'destroyLine']);
    Route::patch('/sales/{sale}/lines/{line}', [SaleController::class, 'updateLine']);
    Route::patch('/sales/{sale}/paid', [SaleController::class, 'markPaid']);

    // Reports
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/trend', [ReportController::class, 'trend']);

    // Stock
    Route::get('/stock', [StockController::class, 'index']);

    // File uploads
    Route::post('/upload', [UploadController::class, 'store']);
    Route::delete('/upload', [UploadController::class, 'destroy']);
});
