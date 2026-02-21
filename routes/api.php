<?php

use App\Http\Controllers\Api\CryptoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the ApiRouteServiceProvider and are prefixed
| with /api. All JSON responses follow the standard: success, data, message.
|
*/

Route::get('/crypto/data', [CryptoController::class, 'index']);
Route::get('/crypto/search', [CryptoController::class, 'search']);
Route::post('/portfolio', [CryptoController::class, 'storePortfolio']);
Route::delete('/portfolio/{id}', [CryptoController::class, 'destroyPortfolio']);
Route::get('/crypto/history/{cmc_id}', [CryptoController::class, 'history']);
