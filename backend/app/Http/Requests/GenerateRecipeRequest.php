<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the setup form before anything is sent to Gemini.
 *
 * Every allowed value comes from config/coffee.php, so the dropdown options, the
 * validation rules and the brew-ratio guard rails cannot drift apart. This also
 * stops arbitrary user text from being injected into the model prompt.
 */
class GenerateRecipeRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in(array_keys(config('coffee.methods')))],
            'roast' => ['required', 'string', Rule::in(config('coffee.roasts'))],
            'taste' => ['required', 'string', Rule::in(config('coffee.tastes'))],
            'amount_ml' => [
                'required',
                'integer',
                'min:'.config('coffee.amount.min'),
                'max:'.config('coffee.amount.max'),
            ],
            'language' => ['required', 'string', Rule::in(['ar', 'en'])],

            // The user's beans.
            'origin' => ['required', 'string', Rule::in(array_keys(config('coffee.origins')))],
            'process' => ['required', 'string', Rule::in(array_keys(config('coffee.processes')))],

            // Free text copied off the bag. Optional, length-capped, and treated
            // strictly as a description in the prompt — never as an instruction.
            'flavor_notes' => ['nullable', 'string', 'max:200'],

            'grinder' => ['nullable', 'string', Rule::in(array_keys(config('coffee.grinders')))],

            'serve' => ['nullable', 'string', Rule::in(config('coffee.serve_styles'))],

            // Optional overrides. Blank means "you decide", which is the default
            // behaviour and what most people will use.
            'coffee_grams' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'ice_grams' => ['nullable', 'numeric', 'min:1', 'max:2000'],

            // Anonymous per-browser id used to group the brew log. Not a
            // credential: it grants no access and identifies no person.
            'client_id' => ['nullable', 'string', 'alpha_dash', 'max:64'],
        ];
    }

    /**
     * The validated setup in the shape GeminiAgent expects.
     *
     * @return array{method: string, roast: string, amount_ml: int, taste: string, origin: string, process: string, flavor_notes: string, grinder: string, client_id: string}
     */
    public function setup(): array
    {
        return [
            'method' => $this->string('method')->toString(),
            'roast' => $this->string('roast')->toString(),
            'amount_ml' => $this->integer('amount_ml'),
            'taste' => $this->string('taste')->toString(),
            'origin' => $this->string('origin')->toString(),
            'process' => $this->string('process')->toString(),
            'grinder' => $this->filled('grinder') ? $this->string('grinder')->toString() : 'Other',
            'serve' => $this->filled('serve') ? $this->string('serve')->toString() : 'Hot',
            'coffee_grams' => $this->filled('coffee_grams') ? (float) $this->input('coffee_grams') : null,
            'ice_grams' => $this->filled('ice_grams') ? (float) $this->input('ice_grams') : null,
            'client_id' => $this->string('client_id')->toString(),
            // Collapse whitespace so the prompt cannot be reshaped with newlines.
            'flavor_notes' => trim(preg_replace('/\s+/u', ' ', $this->string('flavor_notes')->toString()) ?? ''),
        ];
    }
}
