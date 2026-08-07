<?php

use App\Http\Controllers\BeanScanController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Rate limited, because every recipe and every scan costs a Gemini request.
| The bag scanner gets a tighter limit: image requests are the most expensive
| thing here.
|
| Everything that spends money also sits behind the access code (see
| config/security.php). /api/health stays open so the frontend can boot and
| discover whether a code is required — it returns booleans and a model name,
| never a secret.
*/

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/health', HealthController::class);
});

Route::middleware(['throttle:30,1', 'access.code'])->group(function () {
    // Cheap endpoint whose only job is to tell the frontend the code is valid.
    Route::get('/access/check', fn () => response()->json(['ok' => true]));

    Route::post('/recipes/generate', [RecipeController::class, 'generate']);
    Route::post('/recipes/adjust', [RecipeController::class, 'adjust']);
    Route::post('/recipes/translate', [RecipeController::class, 'translate']);

    // Brew log.
    Route::get('/brews', [RecipeController::class, 'index']);
    Route::post('/brews/{brew}/feedback', [RecipeController::class, 'feedback']);
});

Route::middleware(['throttle:10,1', 'access.code'])->group(function () {
    Route::post('/beans/scan', BeanScanController::class);
});
