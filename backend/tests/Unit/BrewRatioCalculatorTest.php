<?php

namespace Tests\Unit;

use App\Services\Coffee\BrewRatioCalculator;
use Tests\TestCase;

/**
 * The dose maths is the one thing in this app that must never be wrong, so it is
 * tested directly rather than only through the model.
 */
class BrewRatioCalculatorTest extends TestCase
{
    private BrewRatioCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BrewRatioCalculator;
    }

    public function test_it_computes_the_dose_from_water_and_ratio(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60',
            'water_ml' => 300,
            'ratio' => '1:16',
        ]);

        $this->assertSame(18.8, $result['coffee_grams']);
        $this->assertSame('1:16', $result['ratio']);
        $this->assertSame(300, $result['water_ml']);
        $this->assertNull($result['adjustment_note']);
    }

    public function test_it_handles_espresso_fractional_ratios(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'Espresso',
            'water_ml' => 36,
            'ratio' => '1:2',
        ]);

        $this->assertSame(18.0, $result['coffee_grams']);
        $this->assertNull($result['adjustment_note']);
    }

    public function test_it_clamps_an_absurd_ratio_and_explains_itself(): void
    {
        // 1:40 espresso would be undrinkable — and would give a 0.9 g dose.
        $result = $this->calculator->calculate([
            'method' => 'Espresso',
            'water_ml' => 36,
            'ratio' => '1:40',
        ]);

        $this->assertSame('1:2', $result['ratio']);
        $this->assertSame(18.0, $result['coffee_grams']);
        $this->assertStringContainsString('outside the sensible range', $result['adjustment_note']);
    }

    public function test_it_falls_back_when_the_ratio_is_unparseable(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'French Press',
            'water_ml' => 500,
            'ratio' => 'about medium please',
        ]);

        $this->assertSame('1:15', $result['ratio']);
        $this->assertSame(33.3, $result['coffee_grams']);
        $this->assertNotNull($result['adjustment_note']);
    }

    public function test_it_rejects_a_non_positive_water_amount(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60',
            'water_ml' => -5,
            'ratio' => '1:16',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('coffee_grams', $result);
    }

    public function test_an_unknown_method_falls_back_to_v60_limits(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'Siphon',
            'water_ml' => 320,
            'ratio' => '1:16',
        ]);

        $this->assertSame(20.0, $result['coffee_grams']);
    }

    public function test_the_declaration_lists_every_configured_method(): void
    {
        $declaration = $this->calculator->declaration();

        $this->assertSame('calculate_brew_ratio', $declaration['name']);
        $this->assertSame(
            array_keys(config('coffee.methods')),
            $declaration['parameters']['properties']['method']['enum'],
        );
    }
}
