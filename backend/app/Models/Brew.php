<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One brewed recipe and how it turned out.
 *
 * @property string $client_id
 * @property array<string, mixed> $recipe
 * @property string|null $feedback 'sour' | 'bitter' | 'perfect' | null
 */
class Brew extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'method',
        'roast',
        'origin',
        'process',
        'grinder',
        'amount_ml',
        'taste',
        'recipe',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'recipe' => 'array',
            'amount_ml' => 'integer',
        ];
    }
}
