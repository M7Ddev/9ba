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
        // Steps are floats because not every grinder is clicked: the DF54 has a
        // numbered dial read to one decimal, so 0.5 is a real adjustment.
        $step = (float) $grinder['step'];
        $shift = match ($adjustment) {
            'coarser' => $step,
            'finer' => -$step,
            default => 0.0,
        };

        // Never let a shift push the setting to zero or below.
        $min = max($step, (float) $window[0] + $shift);
        $max = max($min + $step, (float) $window[1] + $shift);

        $unit = $this->isStepless($grinder) ? 'on the dial' : 'clicks';

        return [
            'grinder' => $grinderKey,
            'method' => $methodKey,
            'adjustment' => $adjustment,
            'clicks_available' => true,
            // Numbers stay numbers; only the label is formatted for display.
            // Floats because a stepless grinder like the DF54 is set to 4.5.
            'clicks_min' => $min,
            'clicks_max' => $max,
            'clicks_label' => $this->format($min).'-'.$this->format($max).' '.$unit,
            'grinder_note' => $grinder['note'],
            'guidance' => 'Put this range in the `grind_clicks` field verbatim, and still describe the '
                .'texture in `grind_size`. Tell the user it is a starting point to dial in from.',
        ];
    }

    /**
     * A grinder whose adjustment is a continuous dial rather than detents.
     * Saying "4.5 clicks" to someone holding a stepless grinder is meaningless.
     */
    private function isStepless(array $grinder): bool
    {
        return ((float) $grinder['step']) < 1.0;
    }

    /** 5.0 -> "5", 4.5 -> "4.5" — no trailing zeros on whole numbers. */
    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
