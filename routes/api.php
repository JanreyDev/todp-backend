<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/contributes/leaderboard', [ContributeController::class, 'publicLeaderboard']);
Route::get('/contributes/approved', [ContributeController::class, 'approved']);
Route::get('/contributes/approved/{id}', [ContributeController::class, 'showApproved']);
Route::get('/contributes/approved/{id}/data', [ContributeController::class, 'getFileData']); // NEW LINE

// Public: Fetch categories and tags
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/tags', [TagController::class, 'index']);

// Protected routes
Route::middleware('auth:api')->group(function(){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Contributions
    Route::post('/contributes', [ContributeController::class, 'store']);
    Route::get('/contributes/my', [ContributeController::class, 'myContributions']);
    Route::get('/contributes', [ContributeController::class, 'index']);
    Route::get('/contributes/{id}', [ContributeController::class, 'show']);
    Route::put('/contributes/{id}', [ContributeController::class, 'update']);
    
    // Categories & Tags (Admin only - add middleware if needed)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::post('/categories/find-or-create', [CategoryController::class, 'findOrCreate']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::post('/tags/find-or-create', [TagController::class, 'findOrCreate']);
});