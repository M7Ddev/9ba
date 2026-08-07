<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shared access code that gates every endpoint which costs money.
 *
 * Two properties matter most: with no code configured the app behaves exactly
 * as it always did (so local development is untouched), and with one configured
 * nothing reaches Gemini without it.
 */
class AccessCodeTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'a-long-random-access-code';

    private const SETUP = [
        'method' => 'V60',
        'roast' => 'Medium',
        'amount_ml' => 300,
        'taste' => 'Balanced',
        'language' => 'en',
        'origin' => 'Ethiopia',
        'process' => 'Washed',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['gemini.api_key' => 'test-key-not-real']);
    }

    public function test_with_no_code_configured_the_api_is_open(): void
    {
        config(['security.access_code' => '']);

        $this->getJson('/api/access/check')->assertOk();
        $this->getJson('/api/brews?client_id=someone')->assertOk();
    }

    public function test_a_configured_code_blocks_requests_that_lack_it(): void
    {
        config(['security.access_code' => self::CODE]);
        Http::fake();

        $this->postJson('/api/recipes/generate', self::SETUP)
            ->assertStatus(401)
            ->assertJsonPath('error', 'UNAUTHORIZED');

        // The whole point: no Gemini request is made, so no money is spent.
        Http::assertNothingSent();
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        config(['security.access_code' => self::CODE]);
        Http::fake();

        $this->withHeader('X-Access-Code', 'not-the-code')
            ->postJson('/api/recipes/generate', self::SETUP)
            ->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_the_correct_code_is_accepted(): void
    {
        config(['security.access_code' => self::CODE]);

        $this->withHeader('X-Access-Code', self::CODE)
            ->getJson('/api/access/check')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_every_paid_endpoint_is_gated(): void
    {
        config(['security.access_code' => self::CODE]);
        Http::fake();

        $this->postJson('/api/recipes/generate', self::SETUP)->assertStatus(401);
        $this->postJson('/api/recipes/adjust', self::SETUP)->assertStatus(401);
        $this->postJson('/api/recipes/translate', ['language' => 'en'])->assertStatus(401);
        $this->postJson('/api/beans/scan', [])->assertStatus(401);
        $this->getJson('/api/brews?client_id=someone')->assertStatus(401);
        $this->postJson('/api/brews/1/feedback', [])->assertStatus(401);

        Http::assertNothingSent();
    }

    /**
     * Health must stay open, or the frontend cannot boot far enough to learn
     * that a code is required — but it must not reveal the code itself.
     */
    public function test_health_stays_open_and_reports_that_a_code_is_required(): void
    {
        config(['security.access_code' => self::CODE]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('access_required', true)
            ->assertDontSee(self::CODE);
    }

    public function test_health_reports_no_code_required_when_the_gate_is_off(): void
    {
        config(['security.access_code' => '']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('access_required', false);
    }

    /** A 401 must not disclose anything about the expected value. */
    public function test_the_rejection_reveals_nothing_about_the_code(): void
    {
        config(['security.access_code' => self::CODE]);

        $body = (string) $this->getJson('/api/access/check')->getContent();

        $this->assertStringNotContainsString(self::CODE, $body);
        $this->assertStringNotContainsString((string) strlen(self::CODE), $body);
    }
}
