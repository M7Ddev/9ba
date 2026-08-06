<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an uploaded photo of a coffee bag.
 *
 * The limits matter: the image is base64-encoded and sent to Gemini, so an
 * oversized upload costs both memory and tokens. `image` also rejects anything
 * that is not a real decodable image, so a renamed executable never reaches the
 * encoding step.
 */
class ScanBagRequest extends FormRequest
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
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:4096',            // kilobytes
                'dimensions:max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.max' => 'The photo must be 4 MB or smaller.',
            'photo.mimes' => 'The photo must be a JPEG, PNG or WebP image.',
        ];
    }
}
