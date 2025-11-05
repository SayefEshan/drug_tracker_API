<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DrugSearchController;
use App\Http\Controllers\UserMedicationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public drug search endpoint with rate limiting
Route::get('/drugs/search', [DrugSearchController::class, 'search'])
    ->middleware('throttle:60,1'); // 60 requests per minute

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User medication routes
    Route::get('/medications', [UserMedicationController::class, 'index']);
    Route::post('/medications', [UserMedicationController::class, 'store']);
    Route::delete('/medications/{id}', [UserMedicationController::class, 'destroy']);
});
