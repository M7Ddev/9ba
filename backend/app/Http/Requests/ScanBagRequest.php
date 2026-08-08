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

                // 8 MB. The frontend downscales to roughly 250 KB before
                // uploading, so this is only a backstop for a client where that
                // failed. The previous 4 MB limit rejected ordinary phone
                // photos outright.
                'max:8192',

                // No max_width/max_height rule. It used to cap at 4000px, and
                // an iPhone shoots 4032x3024 — so every photo taken on an
                // iPhone was rejected by 32 pixels, while a small image copied
                // from a website worked. That looked like the scanner being
                // broken rather than an upload limit. Byte size is the real
                // constraint; pixel dimensions are not.
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.max' => 'The photo must be 8 MB or smaller.',
            'photo.mimes' => 'The photo must be a JPEG, PNG or WebP image.',
            'photo.image' => 'That file is not an image the server can read.',
        ];
    }
}
