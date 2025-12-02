<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // Product endpoints
    Route::get('/products/{id}', [ProductController::class, 'show']);
    
    // Hold endpoints
    Route::post('/holds', [HoldController::class, 'store']);
    
    // Order endpoints
    Route::post('/orders', [OrderController::class, 'store']);
    
    // Payment webhook
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handleWebhook']);
    Route::post('/payments/process-pending', [PaymentWebhookController::class, 'processPendingWebhooks']);
});