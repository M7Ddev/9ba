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

    public function test_a_hot_brew_uses_all_the_water_and_no_ice(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'serve' => 'Hot',
        ]);

        $this->assertSame('Hot', $result['serve']);
        $this->assertSame(300, $result['brew_water_ml']);
        $this->assertSame(0, $result['ice_grams']);
    }

    /**
     * The headline case. The dose is set by the TOTAL drink, while only part of
     * that total is poured as hot water — brewing the full amount over ice is
     * the mistake this exists to prevent.
     */
    public function test_an_iced_brew_splits_the_total_into_water_and_ice(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'serve' => 'Iced',
        ]);

        // 40% of 300 = 120 g ice, leaving 180 ml to brew with.
        $this->assertSame(120, $result['ice_grams']);
        $this->assertSame(180, $result['brew_water_ml']);

        // Water and ice must add back up to the total the user asked for.
        $this->assertSame(300, $result['brew_water_ml'] + $result['ice_grams']);

        // And crucially the dose is unchanged: still 300/16, not 180/16.
        $this->assertSame(18.8, $result['coffee_grams']);
        $this->assertSame(300, $result['water_ml']);
    }

    public function test_iced_and_hot_produce_the_same_dose(): void
    {
        $hot = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'serve' => 'Hot',
        ]);
        $iced = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'serve' => 'Iced',
        ]);

        // A weaker dose for iced would mean the melted ice dilutes it twice.
        $this->assertSame($hot['coffee_grams'], $iced['coffee_grams']);
    }

    public function test_espresso_is_served_over_ice_without_changing_the_brew(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'Espresso', 'water_ml' => 36, 'ratio' => '1:2', 'serve' => 'Iced',
        ]);

        // The shot is pulled normally; the ice goes in the glass.
        $this->assertSame(36, $result['brew_water_ml']);
        $this->assertSame(18.0, $result['coffee_grams']);
        $this->assertGreaterThan(0, $result['ice_grams']);
        $this->assertStringContainsString('unchanged', $result['serve_note']);
    }

    public function test_serve_defaults_to_hot_when_not_supplied(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16',
        ]);

        $this->assertSame('Hot', $result['serve']);
        $this->assertSame(0, $result['ice_grams']);
    }

    public function test_a_user_supplied_dose_drives_the_ratio_instead(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'coffee_grams' => 20,
        ]);

        // The dose the user weighed is kept exactly, and the ratio follows.
        $this->assertSame(20.0, $result['coffee_grams']);
        $this->assertSame('1:15', $result['ratio']);
        $this->assertTrue($result['dose_set_by_user']);
    }

    /**
     * Clamping exists to catch a model inventing nonsense. A person who typed a
     * dose measured it, so it is kept — but flagged, so the recipe can warn.
     */
    public function test_an_unusual_user_dose_is_kept_but_flagged(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'coffee_grams' => 40,
        ]);

        $this->assertSame(40.0, $result['coffee_grams']); // not overridden
        $this->assertStringContainsString('outside the usual', $result['adjustment_note']);
        $this->assertStringContainsString('stronger', $result['adjustment_note']);
    }

    public function test_a_user_supplied_ice_amount_is_used(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16',
            'serve' => 'Iced', 'ice_grams' => 150,
        ]);

        $this->assertSame(150, $result['ice_grams']);
        $this->assertSame(150, $result['brew_water_ml']);
        $this->assertSame(300, $result['ice_grams'] + $result['brew_water_ml']);
        $this->assertTrue($result['ice_set_by_user']);
    }

    /**
     * Unlike the dose, ice IS clamped: too much leaves too little water to wet
     * the grounds, which is not a preference but a brew that cannot work.
     */
    public function test_an_impossible_ice_amount_is_clamped(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16',
            'serve' => 'Iced', 'ice_grams' => 290,
        ]);

        $this->assertSame(210, $result['ice_grams']); // 70% ceiling
        $this->assertGreaterThan(0, $result['brew_water_ml']);
        $this->assertStringContainsString('adjusted', $result['ice_clamped_note']);
    }

    public function test_blank_overrides_keep_the_original_behaviour(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'water_ml' => 300, 'ratio' => '1:16', 'serve' => 'Iced',
        ]);

        $this->assertSame(18.8, $result['coffee_grams']);
        $this->assertFalse($result['dose_set_by_user']);
        $this->assertSame(120, $result['ice_grams']); // the 40% default
        $this->assertFalse($result['ice_set_by_user']);
    }

    /**
     * "I have 18 g in the grinder — how much water?" The volume follows from the
     * dose and the ratio, which is the way people actually think when scooping.
     */
    public function test_a_blank_amount_is_derived_from_the_dose(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'ratio' => '1:16', 'coffee_grams' => 18,
        ]);

        $this->assertSame(18.0, $result['coffee_grams']);
        $this->assertSame(288, $result['water_ml']); // 18 x 16
        $this->assertFalse($result['amount_set_by_user']);
    }

    public function test_a_blank_amount_and_blank_dose_use_the_method_default(): void
    {
        $v60 = $this->calculator->calculate(['method' => 'V60', 'ratio' => '1:16']);
        $press = $this->calculator->calculate(['method' => 'French Press', 'ratio' => '1:15']);

        $this->assertSame(300, $v60['water_ml']);   // one mug
        $this->assertSame(500, $press['water_ml']); // one small pot
        $this->assertSame(18.8, $v60['coffee_grams']);
    }

    public function test_a_given_amount_still_wins(): void
    {
        $result = $this->calculator->calculate([
            'method' => 'V60', 'ratio' => '1:16', 'water_ml' => 500, 'coffee_grams' => 18,
        ]);

        // Both supplied: the amount is respected and the ratio is derived.
        $this->assertSame(500, $result['water_ml']);
        $this->assertSame(18.0, $result['coffee_grams']);
        $this->assertTrue($result['amount_set_by_user']);
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
