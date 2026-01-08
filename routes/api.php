<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributeController;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public leaderboard (no auth required)
Route::get('/contributes/leaderboard', [ContributeController::class, 'publicLeaderboard']);

// Public approved contributions (no auth required)
Route::get('/contributes/approved', [ContributeController::class, 'approved']);

// Protected routes (authentication required)
Route::middleware('auth:api')->group(function(){
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Contribution routes
    Route::post('/contributes', [ContributeController::class, 'store']); // Any authenticated user can submit
    Route::get('/contributes/my', [ContributeController::class, 'myContributions']); // User's own contributions
    Route::get('/contributes', [ContributeController::class, 'index']); // Admin: view all
    Route::get('/contributes/{id}', [ContributeController::class, 'show']); // View single contribution
    Route::put('/contributes/{id}', [ContributeController::class, 'update']); // Admin: update status
});