<?php

use App\Http\Controllers\BeanScanController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| No auth. Rate limited, because every recipe and every scan costs a Gemini
| request. The bag scanner gets a tighter limit: image requests are the most
| expensive thing here.
*/

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/recipes/generate', [RecipeController::class, 'generate']);
    Route::post('/recipes/adjust', [RecipeController::class, 'adjust']);
    Route::post('/recipes/translate', [RecipeController::class, 'translate']);

    // Brew log.
    Route::get('/brews', [RecipeController::class, 'index']);
    Route::post('/brews/{brew}/feedback', [RecipeController::class, 'feedback']);
});

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/beans/scan', BeanScanController::class);
});
