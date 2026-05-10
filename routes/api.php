<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\HermesController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/hermes/command', [HermesController::class, 'processCommand']);
    Route::post('/hermes/products/create', [HermesController::class, 'createProduct']);
    Route::post('/hermes/products/bulk', [HermesController::class, 'bulkCreateProducts']);
    Route::post('/hermes/images/upload', [HermesController::class, 'uploadImage']);
    Route::post('/hermes/store/config/update', [HermesController::class, 'updateStoreConfig']);
    Route::post('/hermes/orders/manage', [HermesController::class, 'manageOrders']);
    Route::post('/hermes/inventory/update', [HermesController::class, 'updateInventory']);
});