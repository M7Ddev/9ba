<?php

namespace App\Services\Coffee;

use App\Services\Gemini\GeminiAgent;

/**
 * Third tool: `get_grind_setting`.
 *
 * Turns "medium-fine, like table salt" into "22 clicks on your Comandante C40",
 * which is the difference between advice a beginner can follow and advice they
 * cannot. Click windows live in config/coffee.php.
 *
 * Like the other tools, the model is told to look the number up rather than
 * invent it — grinder click counts are exactly the kind of specific fact
 * language models are confidently wrong about.
 *
 * @see GeminiAgent where the tools are declared and dispatched.
 */
class GrinderCalibrator
{
    /**
     * The tool declaration handed to Gemini.
     *
     * @return array<string, mixed>
     */
    public function declaration(): array
    {
        return [
            'name' => 'get_grind_setting',
            'description' => 'Looks up the click / dial setting for a specific grinder and brew method. '
                .'Call this whenever the user has told you which grinder they own, so the recipe can '
                .'give a real number instead of only a description. Never invent click counts.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'grinder' => [
                        'type' => 'STRING',
                        'description' => 'The grinder the user owns.',
                        'enum' => array_keys(config('coffee.grinders')),
                    ],
                    'method' => [
                        'type' => 'STRING',
                        'description' => 'Brew method.',
                        'enum' => array_keys(config('coffee.methods')),
                    ],
                    'adjustment' => [
                        'type' => 'STRING',
                        'description' => 'Optional shift from the standard window, e.g. when the bean '
                            .'profile calls for a coarser grind. Defaults to "none".',
                        'enum' => ['none', 'finer', 'coarser'],
                    ],
                ],
                'required' => ['grinder', 'method'],
            ],
        ];
    }

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $args  Raw arguments from the model.
     * @return array<string, mixed>
     */
    public function calibrate(array $args): array
    {
        $grinderKey = (string) ($args['grinder'] ?? '');
        $methodKey = (string) ($args['method'] ?? '');
        $adjustment = (string) ($args['adjustment'] ?? 'none');

        $grinder = config("coffee.grinders.{$grinderKey}");

        // Unknown grinder, or a grinder we have no numbers for: say so plainly
        // rather than guessing a click count.
        if ($grinder === null || ($grinder['settings'] ?? []) === []) {
            return [
                'grinder' => $grinderKey !== '' ? $grinderKey : 'Other',
                'clicks_available' => false,
                'guidance' => 'No click data for this grinder. Describe the grind by texture instead '
                    .'(for example "medium-fine, like table salt") and leave grind_clicks empty.',
            ];
        }

        $window = $grinder['settings'][$methodKey] ?? null;

        if ($window === null) {
            return [
                'grinder' => $grinderKey,
                'method' => $methodKey,
                'clicks_available' => false,
                'guidance' => "No click data for {$methodKey} on this grinder. Describe the grind by "
                    .'texture instead and leave grind_clicks empty.',
            ];
        }

        // Shift the whole window when the bean profile asked for a step change.
        $step = (int) $grinder['step'];
        $shift = match ($adjustment) {
            'coarser' => $step,
            'finer' => -$step,
            default => 0,
        };

        // Never let a shift push the setting below 1 click.
        $min = max(1, $window[0] + $shift);
        $max = max($min + 1, $window[1] + $shift);

        return [
            'grinder' => $grinderKey,
            'method' => $methodKey,
            'adjustment' => $adjustment,
            'clicks_available' => true,
            'clicks_min' => $min,
            'clicks_max' => $max,
            'clicks_label' => "{$min}-{$max} clicks",
            'grinder_note' => $grinder['note'],
            'guidance' => 'Put this range in the `grind_clicks` field, and still describe the texture '
                .'in `grind_size`. Tell the user it is a starting point to dial in from.',
        ];
    }
}
