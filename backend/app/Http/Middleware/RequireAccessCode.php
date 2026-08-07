<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the endpoints that cost money behind a shared access code.
 *
 * When `APP_ACCESS_CODE` is empty the gate is off and every request passes —
 * that is the local-development default, and it is why adding this middleware
 * changed no existing behaviour or test.
 *
 * This is not authentication. It identifies nobody and grants no per-user
 * anything. It exists to stop a stranger who finds the URL from spending the
 * owner's Gemini quota, which per-IP rate limiting does not prevent.
 */
class RequireAccessCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('security.access_code');

        // Not configured: the gate is disabled.
        if ($expected === '') {
            return $next($request);
        }

        $given = (string) $request->header('X-Access-Code', '');

        // hash_equals is constant-time, so a wrong code cannot be discovered one
        // character at a time by measuring how long the comparison takes.
        if (! hash_equals($expected, $given)) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'A valid access code is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
