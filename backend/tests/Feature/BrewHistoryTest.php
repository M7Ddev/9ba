<?php

namespace Tests\Feature;

use App\Models\Brew;
use App\Services\Coffee\BrewHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The history tool is what makes the agent learn. The important behaviour is
 * restraint: it must only claim a tendency when the data actually shows one.
 */
class BrewHistoryTest extends TestCase
{
    use RefreshDatabase;

    private BrewHistory $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = new BrewHistory;
    }

    /** @param array<int, string|null> $feedbacks */
    private function seedBrews(string $clientId, array $feedbacks, string $origin = 'Ethiopia'): void
    {
        foreach ($feedbacks as $feedback) {
            Brew::create([
                'client_id' => $clientId,
                'method' => 'V60',
                'roast' => 'Light',
                'origin' => $origin,
                'process' => 'Washed',
                'grinder' => 'Comandante C40',
                'amount_ml' => 300,
                'taste' => 'Balanced',
                'recipe' => ['coffee_grams' => 18.8],
                'feedback' => $feedback,
            ]);
        }
    }

    public function test_it_reports_no_history_for_an_unknown_client(): void
    {
        $result = $this->history->summarise(['client_id' => 'nobody']);

        $this->assertFalse($result['has_history']);
    }

    public function test_two_rated_brews_are_not_enough_to_claim_a_pattern(): void
    {
        $this->seedBrews('user-a', ['sour', 'sour']);

        $result = $this->history->summarise(['client_id' => 'user-a']);

        $this->assertFalse($result['has_history']);
        $this->assertSame(2, $result['rated_brews']);
    }

    public function test_it_detects_a_consistent_sour_tendency(): void
    {
        $this->seedBrews('user-b', ['sour', 'sour', 'sour', 'perfect']);

        $result = $this->history->summarise(['client_id' => 'user-b']);

        $this->assertTrue($result['has_history']);
        $this->assertSame(3, $result['reported_sour']);
        $this->assertStringContainsString('SOUR', $result['tendency']);
        $this->assertStringContainsString('finer', $result['tendency']);
    }

    public function test_it_detects_a_consistent_bitter_tendency(): void
    {
        $this->seedBrews('user-c', ['bitter', 'bitter', 'bitter', 'bitter']);

        $result = $this->history->summarise(['client_id' => 'user-c']);

        $this->assertStringContainsString('BITTER', $result['tendency']);
        $this->assertStringContainsString('coarser', $result['tendency']);
    }

    public function test_an_even_split_reports_no_tendency(): void
    {
        // Three each way is noise, not a palate.
        $this->seedBrews('user-d', ['sour', 'bitter', 'sour', 'bitter', 'perfect', 'perfect']);

        $result = $this->history->summarise(['client_id' => 'user-d']);

        $this->assertTrue($result['has_history']);
        $this->assertNull($result['tendency']);
    }

    public function test_history_is_scoped_to_one_client(): void
    {
        $this->seedBrews('user-e', ['sour', 'sour', 'sour']);
        $this->seedBrews('user-f', ['perfect']);

        $result = $this->history->summarise(['client_id' => 'user-f']);

        $this->assertFalse($result['has_history']);
        $this->assertSame(1, $result['total_brews']);
    }

    public function test_it_reports_the_most_common_origin(): void
    {
        $this->seedBrews('user-g', ['perfect', 'perfect'], 'Yemen');
        $this->seedBrews('user-g', ['sour'], 'Brazil');

        $result = $this->history->summarise(['client_id' => 'user-g']);

        $this->assertSame('Yemen', $result['favourite_origin']);
    }
}
