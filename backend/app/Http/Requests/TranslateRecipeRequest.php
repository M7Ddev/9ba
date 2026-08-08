<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a translation request.
 *
 * Only the recipe and the target language are needed — translating does not
 * re-derive anything, so none of the setup fields apply here.
 */
class TranslateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // No auth in this prototype.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string', Rule::in(['ar', 'en'])],

            'recipe' => ['required', 'array'],
            'recipe.coffee_grams' => ['required', 'numeric'],
            'recipe.water_ml' => ['required', 'numeric'],
            'recipe.ratio' => ['required', 'string', 'max:20'],
            'recipe.water_temp_c' => ['required', 'numeric'],
            'recipe.grind_size' => ['required', 'string', 'max:200'],
            'recipe.total_time' => ['required', 'string', 'max:50'],
            'recipe.steps' => ['required', 'array', 'max:20'],
            'recipe.steps.*' => ['required', 'string', 'max:500'],
            'recipe.brew_water_ml' => ['nullable', 'numeric'],
            'recipe.ice_grams' => ['nullable', 'numeric'],
            'recipe.grind_clicks' => ['nullable', 'string', 'max:100'],
            'recipe.notes' => ['nullable', 'string', 'max:1000'],
            'recipe.bean_insight' => ['nullable', 'string', 'max:500'],
            'recipe.change_summary' => ['nullable', 'string', 'max:500'],
        ];
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
