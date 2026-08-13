<?php

namespace App\Services\Coffee;

use App\Models\Brew;
use App\Services\Gemini\GeminiAgent;
use Illuminate\Support\Collection;

/**
 * Fourth tool: `get_brew_history`.
 *
 * This is what makes the agent learn instead of starting from zero every time.
 * It aggregates the user's past brews into tendencies — "4 of their last 6 light
 * roasts came out sour" — so the model can pre-empt a complaint rather than wait
 * for it.
 *
 * The aggregation is deterministic PHP. The model receives conclusions it can
 * act on, not a pile of rows to reason over, which keeps it from inventing
 * patterns that are not in the data.
 *
 * @see GeminiAgent where the tools are declared and dispatched.
 */
class BrewHistory
{
    /** Ignore history below this many rated brews: two data points is not a pattern. */
    private const MIN_RATED_BREWS = 3;

    /** How many recent brews to consider. */
    private const WINDOW = 20;

    /**
     * The tool declaration handed to Gemini.
     *
     * @return array<string, mixed>
     */
    public function declaration(): array
    {
        return [
            'name' => 'get_brew_history',
            'description' => 'Returns what this user has brewed before and how it tasted, summarised '
                .'into tendencies. Call this before designing a recipe so you can correct for their '
                .'past results. If it reports no usable history, just design a standard recipe.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'client_id' => [
                        'type' => 'STRING',
                        'description' => 'The identifier supplied in the prompt. Pass it through exactly.',
                    ],
                ],
                'required' => ['client_id'],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function summarise(array $args): array
    {
        $clientId = (string) ($args['client_id'] ?? '');

        if ($clientId === '') {
            return ['has_history' => false, 'guidance' => 'No client id: design a standard recipe.'];
        }

        /** @var Collection<int, Brew> $brews */
        $brews = Brew::query()
            ->where('client_id', $clientId)
            ->latest('id')
            ->limit(self::WINDOW)
            ->get();

        $rated = $brews->whereNotNull('feedback');

        if ($rated->count() < self::MIN_RATED_BREWS) {
            return [
                'has_history' => false,
                'total_brews' => $brews->count(),
                'rated_brews' => $rated->count(),
                'guidance' => 'Not enough rated brews to infer a preference yet. Design a standard '
                    .'recipe and do not mention the history.',
            ];
        }

        $sour = $rated->where('feedback', 'sour')->count();
        $bitter = $rated->where('feedback', 'bitter')->count();
        $weak = $rated->where('feedback', 'weak')->count();
        $perfect = $rated->where('feedback', 'perfect')->count();

        return [
            'has_history' => true,
            'total_brews' => $brews->count(),
            'rated_brews' => $rated->count(),
            'reported_sour' => $sour,
            'reported_bitter' => $bitter,
            'reported_weak' => $weak,
            'reported_perfect' => $perfect,
            'favourite_origin' => $this->mostCommon($brews, 'origin'),
            'favourite_method' => $this->mostCommon($brews, 'method'),

            // Two independent axes, so two independent tendencies. A user can be
            // consistently sour AND consistently weak; those need different
            // corrections and one must not mask the other.
            'tendency' => $this->tendency($sour, $bitter, $rated->count()),
            'strength_tendency' => $this->strengthTendency($weak, $rated->count()),
            'guidance' => 'Pre-correct for any tendency reported above, and say so in one short '
                .'sentence in `notes`. Extraction faults (sour: grind finer or brew hotter; bitter: '
                .'grind coarser or brew cooler) are fixed differently from strength faults (weak: use '
                .'a stronger ratio). If both are reported, apply both — they do not conflict.',
        ];
    }

    /**
     * Turn the sour/bitter split into an instruction, but only when it is lopsided
     * enough to mean something. A 3-2 split is noise, not a palate.
     */
    private function tendency(int $sour, int $bitter, int $rated): ?string
    {
        $threshold = max(2, (int) ceil($rated * 0.4));

        if ($sour >= $threshold && $sour > $bitter) {
            return 'This user repeatedly reports SOUR cups (under-extraction). Start finer and/or '
                .'hotter than the profile default.';
        }

        if ($bitter >= $threshold && $bitter > $sour) {
            return 'This user repeatedly reports BITTER cups (over-extraction). Start coarser and/or '
                .'cooler than the profile default.';
        }

        return null;
    }

    /**
     * Whether this user's cups keep coming out thin.
     *
     * Deliberately separate from tendency(). Weak is not "a bit sour" — it is a
     * concentration fault, fixed by a stronger ratio rather than by changing
     * extraction. Folding the two together would produce advice that grinds
     * finer to fix a watery cup, which makes it sour and no stronger.
     */
    private function strengthTendency(int $weak, int $rated): ?string
    {
        if ($weak < 2 || $weak < (int) ceil($rated * 0.3)) {
            return null;
        }

        return 'This user repeatedly reports WEAK, watery cups. Start from a stronger ratio than the '
            .'profile default (roughly one to two points lower, e.g. 1:16 -> 1:15), which raises the '
            .'dose for the same volume. Do not compensate by grinding finer alone.';
    }

    /**
     * @param  Collection<int, Brew>  $brews
     */
    private function mostCommon(Collection $brews, string $column): ?string
    {
        return $brews->countBy($column)->sortDesc()->keys()->first();
    }
}
