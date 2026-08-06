<?php

namespace Tests\Unit;

use App\Services\Coffee\BeanProfiler;
use Tests\TestCase;

/**
 * The bean profile is the app's coffee knowledge. It has to be deterministic and
 * always within the SCA brewing range, whatever combination the model asks for.
 */
class BeanProfilerTest extends TestCase
{
    private BeanProfiler $profiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profiler = new BeanProfiler;
    }

    public function test_it_returns_the_origin_profile(): void
    {
        $result = $this->profiler->profile([
            'origin' => 'Ethiopia',
            'process' => 'Washed',
            'roast' => 'Medium',
        ]);

        $this->assertSame('Ethiopia', $result['origin']);
        $this->assertSame('high', $result['bean_density']);
        $this->assertContains('jasmine', $result['typical_notes']);
        $this->assertSame(94, $result['recommended_temp_c']);
        $this->assertSame('1:16', $result['recommended_ratio']);
    }

    public function test_natural_processing_lowers_temperature_and_weakens_the_ratio(): void
    {
        $washed = $this->profiler->profile([
            'origin' => 'Ethiopia', 'process' => 'Washed', 'roast' => 'Medium',
        ]);
        $natural = $this->profiler->profile([
            'origin' => 'Ethiopia', 'process' => 'Natural', 'roast' => 'Medium',
        ]);

        // Naturals extract faster, so they are brewed cooler and slightly weaker.
        $this->assertSame(93, $natural['recommended_temp_c']);
        $this->assertLessThan($washed['recommended_temp_c'], $natural['recommended_temp_c']);
        $this->assertSame('1:16.5', $natural['recommended_ratio']);
        $this->assertSame('one step coarser', $natural['grind_adjustment']);
    }

    public function test_dark_roast_lowers_the_temperature(): void
    {
        $medium = $this->profiler->profile([
            'origin' => 'Colombia', 'process' => 'Washed', 'roast' => 'Medium',
        ]);
        $dark = $this->profiler->profile([
            'origin' => 'Colombia', 'process' => 'Washed', 'roast' => 'Dark',
        ]);

        $this->assertSame(93, $medium['recommended_temp_c']);
        $this->assertSame(91, $dark['recommended_temp_c']);
    }

    public function test_yemeni_naturals_are_brewed_cooler_and_stronger(): void
    {
        $result = $this->profiler->profile([
            'origin' => 'Yemen', 'process' => 'Natural', 'roast' => 'Medium',
        ]);

        $this->assertSame('heavy and syrupy', $result['expected_body']);
        $this->assertSame(91, $result['recommended_temp_c']);
        // Base 15.0 + 0.5 for the natural process.
        $this->assertSame('1:15.5', $result['recommended_ratio']);
    }

    public function test_temperature_is_always_clamped_to_the_sca_range(): void
    {
        // Anaerobic (-2) + dark roast (-2) on the coolest origin would fall below 90.
        $coolest = $this->profiler->profile([
            'origin' => 'Brazil', 'process' => 'Anaerobic', 'roast' => 'Dark',
        ]);
        $this->assertGreaterThanOrEqual(90, $coolest['recommended_temp_c']);

        // Light roast on the hottest origin must not exceed 96.
        $hottest = $this->profiler->profile([
            'origin' => 'Kenya', 'process' => 'Washed', 'roast' => 'Light',
        ]);
        $this->assertLessThanOrEqual(96, $hottest['recommended_temp_c']);
    }

    public function test_unknown_values_fall_back_to_neutral_defaults(): void
    {
        $result = $this->profiler->profile([
            'origin' => 'Atlantis', 'process' => 'Cryogenic', 'roast' => 'Blackened',
        ]);

        // A sane recipe beats an error: neutral origin, washed behaviour, no roast shift.
        $this->assertSame(93, $result['recommended_temp_c']);
        $this->assertSame('1:16', $result['recommended_ratio']);
    }

    public function test_the_declaration_lists_every_configured_origin_and_process(): void
    {
        $declaration = $this->profiler->declaration();

        $this->assertSame('get_bean_profile', $declaration['name']);
        $this->assertSame(
            array_keys(config('coffee.origins')),
            $declaration['parameters']['properties']['origin']['enum'],
        );
        $this->assertSame(
            array_keys(config('coffee.processes')),
            $declaration['parameters']['properties']['process']['enum'],
        );
    }
}
