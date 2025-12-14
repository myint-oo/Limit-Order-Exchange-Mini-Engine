<?php

use App\Http\Controllers\AsssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TradeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/orderbook', [OrderController::class, 'getOrderBook']);

    Route::get('/assets', [AsssetController::class, 'getUserAssets']);
    
    Route::get('/orders', [OrderController::class, 'getUserOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::delete('/orders/{order}', [OrderController::class, 'cancel']);

    Route::get('/trades', [TradeController::class, 'getUserTrades']);

    // Broadcasting auth endpoint for Pusher private channels
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });
});

