<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Mevrik Laravel Assessment - Queue System',
        'version' => '1.0.0',
        'status' => 'operational',
        'documentation' => [
            'api_endpoint' => '/api/queue',
            'queue_driver' => 'database',
        ],
    ]);
});
