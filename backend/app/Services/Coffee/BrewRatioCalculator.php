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
        $coffeeGrams = round($water / $parts, 1);

        return [
            'method' => $method,
            'water_ml' => (int) round($water),
            'ratio' => $this->formatRatio($parts),
            'coffee_grams' => $coffeeGrams,
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
