<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Api\FileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('queue')->group(function () {
    // Queue status and information
    Route::get('/', [QueueController::class, 'index']);
    Route::get('/status', [QueueController::class, 'queueStatus']);

    // Dispatch notification job
    Route::post('/dispatch-notification', [QueueController::class, 'dispatchNotification']);

    // Dispatch log writing job
    Route::post('/dispatch-log', [QueueController::class, 'dispatchLog']);

    // Dispatch bulk jobs
    Route::post('/dispatch-bulk', [QueueController::class, 'dispatchBulk']);

    // File processing endpoints
    Route::post('/upload-file', [QueueController::class, 'uploadFile']);
    Route::get('/file-status/{fileId}', [QueueController::class, 'fileStatus']);
    Route::get('/download/{fileId}', [QueueController::class, 'downloadProcessed']);
});

// Professional API routes (v2) - Using Service Layer Architecture
Route::prefix('v2/files')->name('api.file.')->group(function () {
    Route::post('/upload', [FileController::class, 'upload'])->name('upload');
    Route::get('/{fileId}/status', [FileController::class, 'status'])->name('status');
    Route::get('/{fileId}/download', [FileController::class, 'download'])->name('download');
    Route::get('/statistics', [FileController::class, 'statistics'])->name('statistics');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toDateTimeString(),
        'services' => [
            'queue' => 'operational',
            'database' => 'operational',
        ],
    ]);
});
