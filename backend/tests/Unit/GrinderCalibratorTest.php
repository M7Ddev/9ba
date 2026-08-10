<?php

namespace Tests\Unit;

use App\Services\Coffee\GrinderCalibrator;
use Tests\TestCase;

/**
 * Click counts are exactly the kind of specific fact a language model states
 * confidently and wrongly, so the lookup has to be airtight.
 */
class GrinderCalibratorTest extends TestCase
{
    private GrinderCalibrator $calibrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calibrator = new GrinderCalibrator;
    }

    public function test_it_returns_the_click_window_for_a_known_grinder(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'Comandante C40',
            'method' => 'V60',
        ]);

        $this->assertTrue($result['clicks_available']);
        $this->assertSame(20.0, $result['clicks_min']);
        $this->assertSame(26.0, $result['clicks_max']);
        $this->assertSame('20-26 clicks', $result['clicks_label']);
    }

    /**
     * The DF54 is stepless with a numbered dial, so its settings are decimals
     * and "clicks" would be the wrong word to put in front of a user.
     */
    public function test_a_stepless_grinder_reports_decimal_dial_settings(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'DF54',
            'method' => 'V60',
        ]);

        $this->assertSame(4.5, $result['clicks_min']);
        $this->assertSame(5.5, $result['clicks_max']);
        $this->assertSame('4.5-5.5 on the dial', $result['clicks_label']);
    }

    public function test_a_stepless_grinder_shifts_by_half_a_point(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'DF54',
            'method' => 'V60',
            'adjustment' => 'coarser',
        ]);

        $this->assertSame(5.0, $result['clicks_min']);
        $this->assertSame('5-6 on the dial', $result['clicks_label']);
    }

    public function test_a_coarser_adjustment_shifts_the_whole_window_up(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'Comandante C40',
            'method' => 'V60',
            'adjustment' => 'coarser',
        ]);

        // Comandante's step is 2 clicks.
        $this->assertSame(22.0, $result['clicks_min']);
        $this->assertSame(28.0, $result['clicks_max']);
    }

    public function test_a_finer_adjustment_shifts_the_window_down(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'Timemore C2',
            'method' => 'V60',
            'adjustment' => 'finer',
        ]);

        $this->assertSame(16.0, $result['clicks_min']);
        $this->assertSame(20.0, $result['clicks_max']);
    }

    public function test_a_finer_adjustment_can_never_go_below_one_click(): void
    {
        // Espresso on the Skerton starts at 3 clicks, so "finer" must clamp.
        $result = $this->calibrator->calibrate([
            'grinder' => 'Hario Skerton Pro',
            'method' => 'Espresso',
            'adjustment' => 'finer',
        ]);

        $this->assertGreaterThanOrEqual(1, $result['clicks_min']);
        $this->assertGreaterThan($result['clicks_min'], $result['clicks_max']);
    }

    public function test_an_unknown_grinder_admits_it_has_no_numbers(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'Other',
            'method' => 'V60',
        ]);

        $this->assertFalse($result['clicks_available']);
        $this->assertArrayNotHasKey('clicks_min', $result);
        $this->assertStringContainsString('Describe the grind', $result['guidance']);
    }

    public function test_a_method_with_no_data_admits_it_too(): void
    {
        $result = $this->calibrator->calibrate([
            'grinder' => 'Comandante C40',
            'method' => 'Siphon',
        ]);

        $this->assertFalse($result['clicks_available']);
    }

    public function test_the_declaration_lists_every_configured_grinder(): void
    {
        $declaration = $this->calibrator->declaration();

        $this->assertSame('get_grind_setting', $declaration['name']);
        $this->assertSame(
            array_keys(config('coffee.grinders')),
            $declaration['parameters']['properties']['grinder']['enum'],
        );
    }
}
