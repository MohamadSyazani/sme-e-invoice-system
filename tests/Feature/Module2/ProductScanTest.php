<?php

namespace Tests\Feature\Module2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Module 2: AI Photo Inventory Scanner.
 * Http::fake() plays the part of Google Gemini, so these tests need no API key and no internet.
 */
class ProductScanTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
        ]);

        Http::preventStrayRequests(); // a test that reaches the real internet fails loudly

        $this->owner = User::factory()->owner()->create();
    }

    /**
     * A real (1x1 pixel) PNG image. Built from fixed bytes instead of
     * UploadedFile::fake()->image(), so the tests also run on a PHP without the GD extension.
     */
    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cement.png', base64_decode(self::TINY_PNG));
    }

    /** What Gemini's reply looks like when it answers with the given text. */
    private function geminiReply(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    private function fakeGemini(array $suggestion): void
    {
        Http::fake(['gemini.test/*' => Http::response($this->geminiReply(json_encode($suggestion)))]);
    }

    private function scan(): TestResponse
    {
        return $this->actingAs($this->owner)->postJson(route('products.scan'), ['photo' => $this->photo()]);
    }

    // ---------- access control ----------

    public function test_guests_and_cashiers_cannot_scan(): void
    {
        Http::fake();

        $this->postJson(route('products.scan'), ['photo' => $this->photo()])->assertUnauthorized();

        $this->actingAs(User::factory()->cashier()->create())
            ->postJson(route('products.scan'), ['photo' => $this->photo()])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // ---------- the happy path ----------

    public function test_owner_gets_form_suggestions_from_a_photo(): void
    {
        $this->fakeGemini([
            'is_hardware' => true,
            'name' => 'YTL Castle Portland Cement 50kg',
            'category' => 'Building Materials',
            'unit_type' => 'BAG',
            'sku' => 'HW-CEM-050',
            'description' => 'Portland cement for concrete and brickwork.',
        ]);

        $this->scan()->assertOk()->assertExactJson(['suggestion' => [
            'name' => 'YTL Castle Portland Cement 50kg',
            'category' => 'Building Materials',
            'unit_type' => 'BAG',
            'sku' => 'HW-CEM-050',
            'description' => 'Portland cement for concrete and brickwork.',
        ]]);

        $this->assertDatabaseCount('products', 0); // scanning never saves anything
    }

    public function test_the_request_to_gemini_uses_the_configured_model_and_key_and_carries_the_photo(): void
    {
        Product::factory()->create(['category' => 'Roofing Sheets']);
        $this->fakeGemini(['is_hardware' => true, 'name' => 'Anything']);

        $this->scan()->assertOk();

        Http::assertSent(function (Request $request) {
            $parts = $request->data()['contents'][0]['parts'];

            return $request->url() === 'https://gemini.test/v1beta/models/gemini-test-model:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($parts[0]['text'], 'Roofing Sheets')   // the shop's own categories are offered
                && str_contains($parts[0]['text'], 'PIECE, BAG, KG')    // and the allowed unit types
                && $parts[1]['inline_data']['mime_type'] === 'image/png'
                && $parts[1]['inline_data']['data'] === self::TINY_PNG   // the photo itself, base64-encoded
                && $request->data()['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    // ---------- cleaning up what the AI says ----------

    public function test_ai_output_is_cleaned_before_it_reaches_the_form(): void
    {
        Product::factory()->create(['sku' => 'HW-PVC-002', 'category' => 'Plumbing']);

        // Wrapped in ```json fences, wrong-case category, a unit that is not allowed, a messy SKU that is already taken.
        Http::fake(['gemini.test/*' => Http::response($this->geminiReply(
            "```json\n".json_encode([
                'is_hardware' => true,
                'name' => '  BBB PVC Pipe 20mm  ',
                'category' => 'plumbing',
                'unit_type' => 'litre',
                'sku' => ' hw pvc-002! ',
                'description' => '',
            ])."\n```"
        ))]);

        $this->scan()->assertOk()->assertExactJson(['suggestion' => [
            'name' => 'BBB PVC Pipe 20mm',
            'category' => 'Plumbing',     // the shop's own spelling
            'unit_type' => null,          // left for the Owner to choose
            'sku' => 'HWPVC-002',         // cleaned: capitals, no spaces or symbols
            'description' => null,
        ]]);
    }

    public function test_a_suggested_sku_that_is_already_used_gets_a_number_added(): void
    {
        Product::factory()->create(['sku' => 'HW-CEM-050']);
        Product::factory()->create(['sku' => 'HW-CEM-050-2']);
        $this->fakeGemini(['is_hardware' => true, 'name' => 'Cement', 'sku' => 'HW-CEM-050']);

        $this->scan()->assertOk()->assertJsonPath('suggestion.sku', 'HW-CEM-050-3');
    }

    // ---------- when things go wrong, the form must still be usable ----------

    public function test_a_photo_that_is_not_hardware_is_refused_with_a_clear_message(): void
    {
        $this->fakeGemini(['is_hardware' => false, 'name' => null]);

        $this->scan()->assertStatus(422)
            ->assertJsonPath('message', 'This does not look like a hardware product. Take a photo of the item or its packaging and try again.');
    }

    public function test_missing_api_key_gives_a_setup_message_and_calls_nobody(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->scan()->assertStatus(503)->assertJsonFragment([
            'message' => 'The AI scanner is not set up yet: GEMINI_API_KEY is missing in the .env file. You can still fill in the form by hand.',
        ]);

        Http::assertNothingSent();
    }

    public function test_timeout_or_no_internet_falls_back_to_manual_entry(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $response = $this->scan()->assertStatus(503);

        $this->assertStringContainsString('did not answer in time', $response->json('message'));
        $this->assertStringContainsString('fill in the form by hand', $response->json('message'));
    }

    public function test_gemini_errors_are_turned_into_friendly_messages(): void
    {
        Http::fakeSequence('gemini.test/*')
            ->push(['error' => ['message' => 'Resource exhausted']], 429)
            ->push(['error' => ['message' => 'models/gemini-test-model is not found']], 404)
            ->push('Internal error', 500);

        $limit = $this->scan()->assertStatus(503)->json('message');
        $setup = $this->scan()->assertStatus(503)->json('message');
        $down = $this->scan()->assertStatus(503)->json('message');

        $this->assertStringContainsString('usage limit', $limit);
        $this->assertStringContainsString('GEMINI_MODEL', $setup);
        $this->assertStringContainsString('unavailable right now', $down);

        // Google's raw error text is never shown to the user.
        $this->assertStringNotContainsString('Resource exhausted', $limit);
        $this->assertStringNotContainsString('not found', $setup);
    }

    public function test_an_unreadable_reply_is_handled(): void
    {
        Http::fake(['gemini.test/*' => Http::response($this->geminiReply('Sorry, I cannot help with that.'))]);

        $this->assertStringContainsString('could not read this photo', $this->scan()->assertStatus(503)->json('message'));

        Http::fake(['gemini.test/*' => Http::response(['candidates' => []])]); // blocked or empty reply

        $this->scan()->assertStatus(503);
    }

    // ---------- input validation ----------

    public function test_only_a_real_image_is_accepted(): void
    {
        Http::fake();

        $this->actingAs($this->owner)->postJson(route('products.scan'), [])
            ->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->actingAs($this->owner)->postJson(route('products.scan'), [
            'photo' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');

        Http::assertNothingSent();
    }

    public function test_the_add_form_shows_the_scan_button(): void
    {
        $this->actingAs($this->owner)->get(route('products.create'))
            ->assertOk()
            ->assertSee('Scan with AI')
            ->assertSee(route('products.scan'));
    }
}
