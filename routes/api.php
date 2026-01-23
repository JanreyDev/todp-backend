<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/contributes/leaderboard', [ContributeController::class, 'publicLeaderboard']);
Route::get('/contributes/approved', [ContributeController::class, 'approved']);
Route::get('/contributes/approved/{id}', [ContributeController::class, 'showApproved']);
Route::get('/contributes/approved/{id}/data', [ContributeController::class, 'getFileData']);
// Get data from a specific file
Route::get('/contributes/approved/{id}/data/{fileId}', [ContributeController::class, 'getFileData']);

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


// File download route with CORS support
Route::get('/download/{filename}', function ($filename) {
    // Construct the full path
    $filePath = 'uploads/' . $filename;

    // Check if file exists in storage
    if (!Storage::disk('public')->exists($filePath)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    $fullPath = Storage::disk('public')->path($filePath);

    return response()->download($fullPath);
})->where('filename', '.*');
