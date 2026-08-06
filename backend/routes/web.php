<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Laravel serves the built React app, so the whole product deploys as one
| thing on one origin. The frontend is built into public/app by
| `npm run build` (see frontend/vite.config.js).
|
| Everything that is not an API call returns index.html, because the SPA owns
| its own routing. The regex excludes:
|   api   — the JSON API, which must keep returning JSON (including 404s)
|   app   — the built assets, served directly from disk by the web server
|   up    — Laravel's health probe
*/

Route::get('/{path?}', function () {
    $index = public_path('app/index.html');

    if (! is_file($index)) {
        // A clear message beats a blank 404 when someone deploys the backend
        // without building the frontend — an easy mistake, and otherwise a
        // confusing one to diagnose.
        return response(
            'Frontend not built. Run: cd frontend && npm ci && npm run build',
            Response::HTTP_SERVICE_UNAVAILABLE
        )->header('Content-Type', 'text/plain');
    }

    // The HTML must never be cached: it references hashed asset filenames, and
    // a stale copy would point at assets that no longer exist after a deploy.
    return response()
        ->file($index, [
            'Cache-Control' => 'no-store, must-revalidate',
        ]);
})->where('path', '^(?!api|app|up).*$')->name('spa');
