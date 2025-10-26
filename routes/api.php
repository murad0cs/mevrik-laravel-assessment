<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\HealthController;

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
    // Queue status and information (relaxed rate limit - 60/min)
    Route::middleware(['rate.limit:relaxed'])->group(function () {
        Route::get('/', [QueueController::class, 'index']);
        Route::get('/status', [QueueController::class, 'queueStatus']);
        Route::get('/file-status/{fileId}', [QueueController::class, 'fileStatus']);
    });

    // Notification and logging endpoints (moderate rate limit - 30/min)
    Route::middleware(['rate.limit:moderate'])->group(function () {
        Route::post('/dispatch-notification', [QueueController::class, 'dispatchNotification']);
        Route::post('/dispatch-log', [QueueController::class, 'dispatchLog']);
    });

    // Bulk operations (strict rate limit - 5/min)
    Route::middleware(['rate.limit:bulk'])->group(function () {
        Route::post('/dispatch-bulk', [QueueController::class, 'dispatchBulk']);
    });

    // File processing endpoints (moderate rate limit - 30/min)
    Route::middleware(['rate.limit:moderate'])->group(function () {
        Route::post('/upload-file', [QueueController::class, 'uploadFile']);
        Route::get('/download/{fileId}', [QueueController::class, 'downloadProcessed'])->name('queue.download');
    });
});

// Professional API routes (v2) - Using Service Layer Architecture
Route::prefix('v2/files')->name('api.file.')->middleware(['rate.limit:moderate'])->group(function () {
    Route::post('/upload', [FileController::class, 'upload'])->name('upload');
    Route::get('/{fileId}/status', [FileController::class, 'status'])->name('status');
    Route::get('/{fileId}/download', [FileController::class, 'download'])->name('download');
    Route::get('/statistics', [FileController::class, 'statistics'])->name('statistics');
});

// Health check endpoints (no rate limit - monitoring purposes)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/ping', [HealthController::class, 'ping']);
