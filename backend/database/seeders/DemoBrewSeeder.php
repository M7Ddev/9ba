<?php

namespace Database\Seeders;

use App\Models\Brew;
use Illuminate\Database\Seeder;

/**
 * Seeds a client whose cups keep coming out sour, so the personalisation path
 * (`get_brew_history` reporting a tendency) can be demonstrated without brewing
 * and rating four coffees by hand.
 *
 * Run with: php artisan db:seed --class=DemoBrewSeeder
 */
class DemoBrewSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['sour', 'sour', 'sour', 'perfect'] as $feedback) {
            Brew::create([
                'client_id' => 'learner-1',
                'method' => 'V60',
                'roast' => 'Light',
                'origin' => 'Ethiopia',
                'process' => 'Washed',
                'grinder' => 'Comandante C40',
                'amount_ml' => 300,
                'taste' => 'Balanced',
                'recipe' => ['coffee_grams' => 18.8],
                'feedback' => $feedback,
            ]);
        }

        $this->command->info('Seeded '.Brew::where('client_id', 'learner-1')->count().' brews for learner-1.');
    }
}
