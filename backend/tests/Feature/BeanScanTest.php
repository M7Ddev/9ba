<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The bag scanner reads whatever is printed on a label, so the danger is that a
 * label says something we did not plan for — an origin we do not stock, or an
 * instruction aimed at the model. Both must be neutralised before the answer
 * reaches the rest of the app.
 */
class BeanScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gemini.api_key' => 'test-key-not-real']);
    }

    /** A model turn carrying a scan result. */
    private function scanTurn(array $beans): array
    {
        return ['candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode($beans)]]],
        ]]];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('bag.jpg', 800, 800);
    }

    public function test_it_reads_the_label_into_form_fields(): void
    {
        Http::fake(['*' => Http::response($this->scanTurn([
            'bean_name' => 'Yirgacheffe Kochere',
            'origin' => 'Ethiopia',
            'process' => 'Washed',
            'roast' => 'Light',
            'flavor_notes' => 'jasmine, bergamot, peach',
            'found' => true,
        ]))]);

        $this->postJson('/api/beans/scan', ['photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('beans.found', true)
            ->assertJsonPath('beans.origin', 'Ethiopia')
            ->assertJsonPath('beans.roast', 'Light')
            ->assertJsonPath('beans.bean_name', 'Yirgacheffe Kochere')
            ->assertJsonPath('beans.flavor_notes', 'jasmine, bergamot, peach');
    }

    public function test_it_sends_the_image_as_inline_data(): void
    {
        Http::fake(['*' => Http::response($this->scanTurn(['found' => false]))]);

        $this->postJson('/api/beans/scan', ['photo' => $this->photo()])->assertOk();

        Http::assertSent(function ($request) {
            $part = data_get($request->data(), 'contents.0.parts.1.inline_data');

            return $part !== null
                && str_starts_with($part['mime_type'], 'image/')
                && filled($part['data']);
        });
    }

    public function test_an_origin_we_do_not_stock_becomes_other(): void
    {
        Http::fake(['*' => Http::response($this->scanTurn([
            'origin' => 'Papua New Guinea',   // real country, not in our list
            'process' => 'Wet-hulled',        // real process, not in our list
            'roast' => 'Cinnamon',            // not one of our three
            'found' => true,
        ]))]);

        // Anything outside our vocabulary is coerced, so the values are always
        // safe to drop straight into the form and re-submit.
        $this->postJson('/api/beans/scan', ['photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('beans.origin', 'Other')
            ->assertJsonPath('beans.process', 'Washed')
            ->assertJsonPath('beans.roast', 'Medium');
    }

    public function test_it_reports_when_the_photo_is_not_a_coffee_bag(): void
    {
        Http::fake(['*' => Http::response($this->scanTurn(['found' => false]))]);

        $this->postJson('/api/beans/scan', ['photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('beans.found', false);
    }

    public function test_long_label_text_is_truncated(): void
    {
        Http::fake(['*' => Http::response($this->scanTurn([
            'bean_name' => str_repeat('x', 500),
            'flavor_notes' => str_repeat('y', 500),
            'origin' => 'Kenya', 'process' => 'Washed', 'roast' => 'Medium', 'found' => true,
        ]))]);

        $response = $this->postJson('/api/beans/scan', ['photo' => $this->photo()])->assertOk();

        // Must fit the limits the recipe endpoints validate against.
        $this->assertSame(100, mb_strlen($response->json('beans.bean_name')));
        $this->assertSame(200, mb_strlen($response->json('beans.flavor_notes')));
    }

    public function test_it_rejects_a_non_image_upload(): void
    {
        Http::fake();

        $this->postJson('/api/beans/scan', [
            'photo' => UploadedFile::fake()->create('malware.exe', 100),
        ])->assertStatus(422)->assertJsonPath('error', 'VALIDATION');

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_oversized_image(): void
    {
        Http::fake();

        $this->postJson('/api/beans/scan', [
            'photo' => UploadedFile::fake()->image('huge.jpg', 800, 800)->size(5000),
        ])->assertStatus(422);

        Http::assertNothingSent();
    }
}
