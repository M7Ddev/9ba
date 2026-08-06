<?php

namespace App\Services\Coffee;

use App\Services\Gemini\GeminiAgent;

/**
 * The second tool available to the agent: `get_bean_profile`.
 *
 * Where BrewRatioCalculator owns the arithmetic, this owns the *coffee
 * knowledge*. Given an origin, a processing method and a roast level it returns
 * a concrete starting point — water temperature, brew ratio, grind guidance and
 * the extraction behaviour to expect.
 *
 * The point is the same as with the ratio tool: the model is told to look the
 * bean up rather than recall origin characteristics from memory, so the advice
 * comes from one auditable table in config/coffee.php instead of whatever the
 * model happens to believe about Yemeni coffee.
 *
 * @see GeminiAgent where both tools are declared and dispatched.
 */
class BeanProfiler
{
    /**
     * Roast level shifts brewing temperature: darker roasts are more soluble and
     * more porous, so they need cooler water to avoid pulling out bitterness.
     */
    private const ROAST_TEMP_ADJUST = [
        'Light' => 1,
        'Medium' => 0,
        'Dark' => -2,
    ];

    /**
     * The tool declaration handed to Gemini.
     *
     * @return array<string, mixed>
     */
    public function declaration(): array
    {
        return [
            'name' => 'get_bean_profile',
            'description' => 'Looks up the brewing profile for a coffee based on its origin, processing '
                .'method and roast level. Returns the recommended water temperature, brew ratio, grind '
                .'guidance and expected extraction behaviour. You MUST call this before designing a '
                .'recipe when the origin is known. Do not rely on your own knowledge of origins.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'origin' => [
                        'type' => 'STRING',
                        'description' => 'Country of origin of the beans.',
                        'enum' => array_keys(config('coffee.origins')),
                    ],
                    'process' => [
                        'type' => 'STRING',
                        'description' => 'Processing method.',
                        'enum' => array_keys(config('coffee.processes')),
                    ],
                    'roast' => [
                        'type' => 'STRING',
                        'description' => 'Roast level.',
                        'enum' => config('coffee.roasts'),
                    ],
                ],
                'required' => ['origin', 'process', 'roast'],
            ],
        ];
    }

    /**
     * Execute the tool. The returned array goes straight back to Gemini, so every
     * key is written to be readable by the model.
     *
     * @param  array<string, mixed>  $args  Raw arguments from the model.
     * @return array<string, mixed>
     */
    public function profile(array $args): array
    {
        $originKey = (string) ($args['origin'] ?? '');
        $processKey = (string) ($args['process'] ?? '');
        $roastKey = (string) ($args['roast'] ?? '');

        // Unknown values fall back to neutral defaults rather than failing: the
        // model may pass an origin we do not stock, and a sane recipe still beats
        // an error.
        $origin = config("coffee.origins.{$originKey}") ?? config('coffee.origins.Other');
        $process = config("coffee.processes.{$processKey}") ?? config('coffee.processes.Washed');
        $roastAdjust = self::ROAST_TEMP_ADJUST[$roastKey] ?? 0;

        // Temperature: origin baseline, shifted by process and roast, then clamped
        // to the SCA brewing range so no combination can produce nonsense.
        $temperature = $origin['base_temp_c'] + $process['temp_adjust_c'] + $roastAdjust;
        $temperature = max(90, min(96, $temperature));

        $ratioParts = $origin['base_ratio'] + $process['ratio_adjust'];

        return [
            'origin' => $originKey !== '' ? $originKey : 'Other',
            'process' => $processKey !== '' ? $processKey : 'Washed',
            'roast' => $roastKey,

            // What to expect in the cup.
            'bean_density' => $origin['density'],
            'expected_acidity' => $origin['acidity'],
            'expected_body' => $origin['body'],
            'typical_notes' => $origin['typical_notes'],

            // What to actually do about it.
            'recommended_temp_c' => $temperature,
            'recommended_ratio' => '1:'.rtrim(rtrim(number_format($ratioParts, 1, '.', ''), '0'), '.'),
            'grind_adjustment' => $process['grind_adjust'],
            'extraction_behaviour' => $process['extraction'],
            'origin_note' => $origin['note'],

            'guidance' => 'Use recommended_temp_c and recommended_ratio as your starting point. '
                .'Pass recommended_ratio to calculate_brew_ratio to get the dose. You may shift the '
                .'ratio for the user\'s taste preference, but keep the temperature within 1 degree '
                .'unless you explain why in notes.',
        ];
    }
}
