<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence-in-depth response headers.
 *
 * This API only ever returns JSON, which lets the policy be far stricter than a
 * normal web app: nothing here should be framed, sniffed, indexed, or allowed to
 * load a resource of any kind.
 *
 * These headers cost nothing and close off whole classes of attack that do not
 * depend on a bug in our code — content-type confusion, clickjacking, and
 * referrer leakage of API paths to third parties.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // PHP advertises its version by default, which tells an attacker exactly
        // which CVEs to try. Nothing needs it.
        header_remove('X-Powered-By');

        $response = $next($request);

        $headers = [
            // Never let a browser second-guess the declared Content-Type. Without
            // this, a JSON response containing attacker-influenced text can be
            // sniffed as HTML and executed.
            'X-Content-Type-Options' => 'nosniff',

            // An API has no legitimate reason to appear in a frame.
            'X-Frame-Options' => 'DENY',

            // Do not leak API paths (which include brew ids) to third parties.
            'Referrer-Policy' => 'no-referrer',

            'Content-Security-Policy' => $this->contentSecurityPolicy($request),

            // No browser feature is needed by a JSON response.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',

            // Keep API responses out of search indexes.
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        // HSTS is only meaningful over HTTPS, and sending it from a plaintext
        // local server would be wrong (and ignored). Production only.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Do not clobber a header a controller set deliberately.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * The policy differs by what is being served, because one policy cannot fit
     * both.
     *
     * A blanket `default-src 'none'` is exactly right for JSON and exactly wrong
     * for the app shell: it blocks the script tag, the stylesheet and the web
     * font, so the page loads as an empty <div id="root">. That is not a
     * theoretical risk — it is what happened when this middleware was first
     * added without the split.
     */
    private function contentSecurityPolicy(Request $request): string
    {
        // The API: no resource of any kind should ever load from a JSON response.
        if ($request->is('api/*')) {
            return "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
        }

        // The app shell. `script-src 'self'` with no 'unsafe-inline' is the point
        // of the exercise — it is why the theme bootstrap lives in its own file
        // instead of an inline <script>.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            // Style needs 'unsafe-inline' for the Google Fonts stylesheet and any
            // style attributes. Inline style is a far smaller risk than inline
            // script, and React escapes all interpolated text regardless.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src https://fonts.gstatic.com',
            // blob: and data: cover the bag-photo preview created by URL.createObjectURL.
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
