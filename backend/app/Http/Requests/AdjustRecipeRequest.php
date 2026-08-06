<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Same setup fields as GenerateRecipeRequest, plus the recipe the user brewed
 * and what was wrong with it.
 *
 * The incoming recipe is validated field by field rather than passed through as
 * a free-form blob, because it is fed back into the model prompt.
 */
class AdjustRecipeRequest extends GenerateRecipeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'feedback' => ['required', 'string', Rule::in(['sour', 'bitter'])],

            'recipe' => ['required', 'array'],
            'recipe.coffee_grams' => ['required', 'numeric'],
            'recipe.water_ml' => ['required', 'numeric'],
            'recipe.ratio' => ['required', 'string', 'max:20'],
            'recipe.water_temp_c' => ['required', 'numeric'],
            'recipe.grind_size' => ['required', 'string', 'max:200'],
            'recipe.total_time' => ['required', 'string', 'max:50'],
            'recipe.steps' => ['required', 'array', 'max:20'],
            'recipe.steps.*' => ['required', 'string', 'max:500'],
            'recipe.notes' => ['nullable', 'string', 'max:1000'],
            'recipe.bean_insight' => ['nullable', 'string', 'max:500'],
            'recipe.change_summary' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * Only the recipe keys we validated — anything else the client sent is dropped.
     *
     * @return array<string, mixed>
     */
    public function recipe(): array
    {
        return array_filter(
            $this->validated()['recipe'],
            fn ($value) => $value !== null,
        );
    }
}
