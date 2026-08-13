<?php

namespace Tests\Unit;

use App\Models\Brew;
use App\Services\Coffee\BrewHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A repeated weak cup is its own tendency, tracked apart from sour/bitter.
 *
 * Extraction and strength are independent axes: someone can brew consistently
 * sour AND consistently weak, and those need different corrections. Merging
 * them would produce advice that grinds finer to fix a watery cup.
 */
class BrewHistoryStrengthTest extends TestCase
{
    use RefreshDatabase;

    private function seedRatings(string $client, array $feedbacks): void
    {
        foreach ($feedbacks as $feedback) {
            Brew::create([
                'client_id' => $client, 'method' => 'V60', 'roast' => 'Medium',
                'origin' => 'Kenya', 'process' => 'Washed', 'amount_ml' => 300,
                'taste' => 'Balanced', 'recipe' => ['coffee_grams' => 18.8],
                'feedback' => $feedback,
            ]);
        }
    }

    public function test_repeated_weak_cups_produce_a_strength_tendency(): void
    {
        $this->seedRatings('weak-user', ['weak', 'weak', 'weak', 'perfect']);

        $result = (new BrewHistory)->summarise(['client_id' => 'weak-user']);

        $this->assertSame(3, $result['reported_weak']);
        $this->assertStringContainsString('stronger ratio', $result['strength_tendency']);

        // And it must NOT be reported as an extraction problem.
        $this->assertNull($result['tendency']);
    }

    public function test_both_axes_can_report_at_once(): void
    {
        $this->seedRatings('both-user', ['sour', 'sour', 'sour', 'weak', 'weak']);

        $result = (new BrewHistory)->summarise(['client_id' => 'both-user']);

        $this->assertStringContainsString('SOUR', $result['tendency']);
        $this->assertStringContainsString('WEAK', $result['strength_tendency']);
    }

    public function test_one_weak_cup_is_not_a_pattern(): void
    {
        $this->seedRatings('noise-user', ['weak', 'perfect', 'perfect']);

        $result = (new BrewHistory)->summarise(['client_id' => 'noise-user']);

        $this->assertNull($result['strength_tendency']);
    }
}
