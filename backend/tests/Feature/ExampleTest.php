<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The app shell route.
 *
 * Laravel's scaffold test asserted that `/` returned 200 because it rendered the
 * welcome view. `/` now serves the built React app instead, so the correct
 * behaviour depends on whether the frontend has been built:
 *
 *   built     -> 200 with the SPA
 *   not built -> 503 with instructions
 *
 * Both are correct, and the test suite must run in both states: CI builds the
 * frontend in a separate job, so the backend job never sees public/app.
 */
class ExampleTest extends TestCase
{
    public function test_the_root_route_serves_the_app_or_explains_why_it_cannot(): void
    {
        $built = is_file(public_path('app/index.html'));

        $response = $this->get('/');

        if ($built) {
            $response->assertOk();
            $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

            // The shell must never be cached: it references hashed asset names
            // that change on every deploy.
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

            return;
        }

        // A backend deployed without building the frontend should say so plainly
        // rather than returning a blank page.
        $response->assertStatus(503)->assertSee('npm run build', false);
    }
}
