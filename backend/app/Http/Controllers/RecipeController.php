<?php

namespace App\Http\Controllers;

use App\Exceptions\AgentException;
use App\Http\Requests\AdjustRecipeRequest;
use App\Http\Requests\GenerateRecipeRequest;
use App\Http\Requests\TranslateRecipeRequest;
use App\Models\Brew;
use App\Services\Gemini\GeminiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The two recipe endpoints.
 *
 * Both answer in exactly one of two shapes, so the frontend has a single thing
 * to handle:
 *   success -> { "recipe": { ... } }
 *   failure -> { "error": "MODEL_NOT_FOUND", "message": "..." }
 *
 * `message` is a short developer-facing hint. The user-facing sentence is chosen
 * by the frontend from `error`, which is why nothing here is translated.
 */
class RecipeController extends Controller
{
    public function __construct(private readonly GeminiAgent $agent) {}

    /** POST /api/recipes/generate */
    public function generate(GenerateRecipeRequest $request): JsonResponse
    {
        $setup = $request->setup();

        return $this->respond(
            fn () => $this->agent->generate($setup, $request->string('language')->toString()),
            $setup,
        );
    }

    /** POST /api/recipes/adjust */
    public function adjust(AdjustRecipeRequest $request): JsonResponse
    {
        $setup = $request->setup();

        return $this->respond(
            fn () => $this->agent->adjust(
                $setup,
                $request->recipe(),
                $request->string('feedback')->toString(),
                $request->string('language')->toString(),
            ),
            $setup,
        );
    }

    /**
     * POST /api/brews/{brew}/feedback
     *
     * Records how the cup actually tasted. This is what `get_brew_history` later
     * aggregates, so the agent can pre-correct for the user's tendencies.
     */
    public function feedback(Request $request, int $brew): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['required', 'string', Rule::in(['sour', 'bitter', 'perfect'])],
            'client_id' => ['required', 'string', 'alpha_dash', 'max:64'],
        ]);

        // Looked up by hand rather than through route-model binding. Binding is
        // resolved before route middleware, so a bound model would 404 on a
        // missing id *before* the access-code gate ran — telling an
        // unauthenticated caller which brew ids exist.
        $record = Brew::find($brew);

        // One response for "no such brew" and "not yours", so the API never
        // confirms the existence of another client's brew either.
        if ($record === null || ! hash_equals($record->client_id, $validated['client_id'])) {
            return response()->json(['error' => 'VALIDATION', 'message' => 'Unknown brew.'], 404);
        }

        $record->update(['feedback' => $validated['feedback']]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/brews
     *
     * The user's recent brew log, for the history panel.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'alpha_dash', 'max:64'],
        ]);

        $brews = Brew::query()
            ->where('client_id', $validated['client_id'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Brew $brew) => [
                'id' => $brew->id,
                'method' => $brew->method,
                'origin' => $brew->origin,
                'process' => $brew->process,
                'roast' => $brew->roast,
                'coffee_grams' => $brew->recipe['coffee_grams'] ?? null,
                'water_ml' => $brew->amount_ml,
                'feedback' => $brew->feedback,
                'brewed_at' => $brew->created_at?->toIso8601String(),
            ]);

        return response()->json(['brews' => $brews]);
    }

    /**
     * Write a successful recipe to the brew log.
     *
     * Logging must never break a recipe the user is waiting for, so a failure
     * here is recorded and swallowed rather than surfaced.
     *
     * @param  array<string, mixed>  $setup
     * @param  array<string, mixed>  $recipe
     */
    private function logBrew(array $setup, array $recipe): ?int
    {
        if (blank($setup['client_id'] ?? null)) {
            return null;
        }

        try {
            return Brew::create([
                'client_id' => $setup['client_id'],
                'method' => $setup['method'],
                'roast' => $setup['roast'],
                'origin' => $setup['origin'],
                'process' => $setup['process'],
                'grinder' => $setup['grinder'] ?? null,
                'amount_ml' => $setup['amount_ml'],
                'taste' => $setup['taste'],
                'recipe' => $recipe,
            ])->id;
        } catch (Throwable $e) {
            Log::warning('Could not write to the brew log', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * POST /api/recipes/translate
     *
     * Rewrites an existing recipe in the other language, keeping every brewing
     * number identical. Used when the user switches language with a recipe on
     * screen: the prose is model output and cannot be retranslated client-side.
     */
    public function translate(TranslateRecipeRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->agent->translate(
            $request->recipe(),
            $request->string('language')->toString(),
        ));
    }

    /**
     * Run an agent call and convert any failure into the standard error shape.
     *
     * When $setup is given the successful recipe is also written to the brew log,
     * and its id is returned so the browser can attach feedback to it later.
     *
     * @param  callable(): array<string, mixed>  $work
     * @param  array<string, mixed>|null  $setup
     */
    private function respond(callable $work, ?array $setup = null): JsonResponse
    {
        // One agent request chains several Gemini calls, which on Windows counts
        // against PHP's wall-clock max_execution_time (30s by default). Without
        // this the process is killed mid-flight and none of the error handling
        // below ever runs.
        set_time_limit(config('gemini.request_time_limit'));

        try {
            $recipe = $work();

            return response()->json(array_filter([
                'recipe' => $recipe,
                'brew_id' => $setup === null ? null : $this->logBrew($setup, $recipe),
            ], fn ($value) => $value !== null));
        } catch (AgentException $e) {
            // Expected, classified failures — the code is safe to show.
            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        } catch (Throwable $e) {
            // Anything unexpected: log the detail, tell the client nothing.
            Log::error('Unhandled recipe failure', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'UNKNOWN',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
