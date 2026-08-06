<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Setup check for the frontend.
 *
 * The browser calls this on mount so a missing key or a stopped backend shows a
 * clear banner immediately, instead of failing only when the user presses
 * "generate".
 *
 * It reports *whether* a key is configured — never the key itself, or any part
 * of it.
 */
class HealthController extends Controller
{
    /** GET /api/health */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'key_configured' => filled(config('gemini.api_key')),
            'model' => config('gemini.model'),
        ]);
    }
}
