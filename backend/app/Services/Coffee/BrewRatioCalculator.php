<?php

namespace App\Services\Coffee;

use App\Services\Gemini\GeminiAgent;

/**
 * The one deterministic piece of maths in the whole application.
 *
 * Gemini is explicitly forbidden from working out the coffee dose itself. It has
 * to call the `calculate_brew_ratio` tool, which is routed to this class, and use
 * whatever number comes back. That is what makes the recipe numbers trustworthy
 * instead of plausible-sounding.
 *
 * @see GeminiAgent where the tool is declared and dispatched.
 */
class BrewRatioCalculator
{
    /**
     * The tool declaration handed to Gemini so it knows the function exists.
     *
     * The description carries the "never calculate this yourself" rule, because
     * that text is the only thing telling the model when to call the tool.
     *
     * @return array<string, mixed>
     */
    public function declaration(): array
    {
        return [
            'name' => 'calculate_brew_ratio',
            'description' => 'Calculates the exact coffee dose in grams from a water amount and a brew '
                .'ratio. You MUST call this function to obtain coffee_grams. Never compute or estimate '
                .'the dose yourself. For espresso, water_ml is the target liquid yield in ml.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'method' => [
                        'type' => 'STRING',
                        'description' => 'Brew method.',
                        'enum' => array_keys(config('coffee.methods')),
                    ],
                    'water_ml' => [
                        'type' => 'NUMBER',
                        'description' => 'Total brew water in millilitres (espresso: target yield in ml).',
                    ],
                    'ratio' => [
                        'type' => 'STRING',
                        'description' => 'Brew ratio as a string, e.g. "1:16" for V60 or "1:2" for espresso.',
                    ],
                    'serve' => [
                        'type' => 'STRING',
                        'description' => 'How the coffee is served. "Iced" means Japanese iced / flash '
                            .'brew: part of the total liquid is supplied as ice. Defaults to "Hot".',
                        'enum' => config('coffee.serve_styles'),
                    ],
                ],
                'required' => ['method', 'water_ml', 'ratio'],
            ],
        ];
    }

    /**
     * Execute the tool. The returned array is sent straight back to Gemini as the
     * function response, so every key here is something the model can read.
     *
     * @param  array<string, mixed>  $args  Raw arguments from the model.
     * @return array<string, mixed>
     */
    public function calculate(array $args): array
    {
        $method = (string) ($args['method'] ?? '');
        $limits = config("coffee.methods.{$method}") ?? config('coffee.methods.V60');

        $water = filter_var($args['water_ml'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($water === false || $water <= 0) {
            return ['error' => 'water_ml must be a positive number.'];
        }

        // Decide which ratio is actually used, and tell the model if we overrode it.
        $parts = $this->parseRatio($args['ratio'] ?? null);
        $adjusted = false;

        if ($parts === null || $parts < $limits['min'] || $parts > $limits['max']) {
            $parts = $limits['fallback'];
            $adjusted = true;
        }

        // 1 ml of water ~= 1 g, the standard assumption in coffee brewing.
        //
        // Note this is computed from the TOTAL liquid, before any ice split. The
        // dose must match the strength of the finished drink, not the volume
        // that happens to pass through the brewer.
        $coffeeGrams = round($water / $parts, 1);

        return [
            'method' => $method,
            'water_ml' => (int) round($water),
            'ratio' => $this->formatRatio($parts),
            'coffee_grams' => $coffeeGrams,
            ...$this->serveSplit($method, (string) ($args['serve'] ?? 'Hot'), $water),
            'adjustment_note' => $adjusted
                ? sprintf(
                    'The requested ratio was outside the sensible range for %s (1:%s to 1:%s); 1:%s was used instead.',
                    $method,
                    $this->trimNumber($limits['min']),
                    $this->trimNumber($limits['max']),
                    $this->trimNumber($parts),
                )
                : null,
        ];
    }

    /**
     * Split the total liquid into brew water and ice.
     *
     * This is the part of an iced recipe people get wrong. The dose is set by
     * the TOTAL liquid, but only some of that total is poured through the
     * brewer — the rest is ice that melts into the cup. Pouring the full amount
     * over a glass of ice produces far more, far weaker coffee than intended.
     *
     * Two distinct behaviours:
     *
     *   Dilution methods (V60, French Press, AeroPress) — the ice IS part of
     *   the recipe. Brew water is reduced by the weight of the ice.
     *
     *   Over-ice methods (Espresso, Moka Pot) — the brew is unchanged and
     *   simply poured over ice, so the ratio maths must not be touched.
     *
     * @return array<string, mixed>
     */
    private function serveSplit(string $method, string $serve, float $totalMl): array
    {
        if ($serve !== 'Iced') {
            return [
                'serve' => 'Hot',
                'brew_water_ml' => (int) round($totalMl),
                'ice_grams' => 0,
            ];
        }

        $config = config('coffee.iced');

        // Espresso and Moka Pot: brew normally, serve over ice.
        if (in_array($method, $config['over_ice_methods'], true)) {
            return [
                'serve' => 'Iced',
                'brew_water_ml' => (int) round($totalMl),
                'ice_grams' => $config['serving_ice_g'],
                'serve_note' => 'Brew exactly as normal and pour it over '
                    .$config['serving_ice_g'].' g of ice in the serving glass. The brew ratio is '
                    .'unchanged — the ice only chills and lightly dilutes the finished drink.',
            ];
        }

        // Dilution methods: ice replaces part of the brew water.
        $ice = (int) round($totalMl * $config['ice_fraction']);
        $brewWater = (int) round($totalMl) - $ice;

        return [
            'serve' => 'Iced',
            'brew_water_ml' => $brewWater,
            'ice_grams' => $ice,
            'serve_note' => "Japanese iced method. Put {$ice} g of ice in the carafe BEFORE brewing, "
                ."then brew with only {$brewWater} ml of hot water directly onto it. The ice melts "
                .'into the drink, so the total liquid and the coffee dose are unchanged. Brew hotter '
                .'(94-96 C) and one step finer than the hot version, because contact time is shorter.',
        ];
    }

    /**
     * Parse a ratio written as "1:16" (or "16", or "1 : 16") into the float 16.0.
     * Returns null when it cannot be understood.
     */
    private function parseRatio(mixed $ratio): ?float
    {
        if (is_numeric($ratio)) {
            return (float) $ratio;
        }

        if (! is_string($ratio)) {
            return null;
        }

        $segments = explode(':', $ratio);
        $raw = trim(count($segments) === 2 ? $segments[1] : $segments[0]);

        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);

        return ($value === false || $value <= 0) ? null : $value;
    }

    private function formatRatio(float $parts): string
    {
        return '1:'.$this->trimNumber($parts);
    }

    /** 16.0 -> "16", 1.5 -> "1.5" */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
