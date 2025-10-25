<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;

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

    // Dispatch notification job
    Route::post('/dispatch-notification', [QueueController::class, 'dispatchNotification']);

    // Dispatch log writing job
    Route::post('/dispatch-log', [QueueController::class, 'dispatchLog']);

    // Dispatch bulk jobs
    Route::post('/dispatch-bulk', [QueueController::class, 'dispatchBulk']);
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
