<?php

namespace App\Services;

use App\Exceptions\GeminiVisionException;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Module 2: AI Photo Inventory Scanner.
 *
 * Sends a photo of a hardware item to Google Gemini and turns the reply into
 * suggestions for the product form. Nothing is saved here: the Owner checks
 * the suggestions on the form and saves the product the normal way.
 */
class GeminiVisionService
{
    /**
     * @param  string  $imageBytes  the raw contents of the photo
     * @param  string  $mimeType  for example image/jpeg
     * @param  list<string>  $categories  categories the shop already uses
     * @return array{name: ?string, category: ?string, unit_type: ?string, sku: ?string, description: ?string}
     *
     * @throws GeminiVisionException
     */
    public function describeProduct(string $imageBytes, string $mimeType, array $categories = []): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new GeminiVisionException(
                'The AI scanner is not set up yet: GEMINI_API_KEY is missing in the .env file. You can still fill in the form by hand.'
            );
        }

        $url = rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.config('services.gemini.model').':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout((int) config('services.gemini.vision_timeout', 20))
                ->acceptJson()
                ->post($url, [
                    'contents' => [[
                        'parts' => [
                            ['text' => $this->prompt($categories)],
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]],
                        ],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json', // ask for JSON, not prose
                        'temperature' => 0.2,                     // low = consistent, not creative
                    ],
                ]);
        } catch (ConnectionException $e) {
            // No internet, or Gemini took longer than the timeout.
            throw new GeminiVisionException(
                'The AI scanner did not answer in time. Check the internet connection, or fill in the form by hand.'
            );
        }

        if ($response->failed()) {
            // Keep the technical detail in storage/logs/laravel.log, never on screen.
            Log::warning('Gemini photo scan failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new GeminiVisionException(match (true) {
                $response->status() === 429 => 'The AI scanner has reached its usage limit for now. Try again in a minute, or fill in the form by hand.',
                in_array($response->status(), [400, 401, 403, 404], true) => 'The AI scanner rejected the request. Check GEMINI_API_KEY and GEMINI_MODEL in the .env file. You can still fill in the form by hand.',
                default => 'The AI scanner is unavailable right now. You can still fill in the form by hand.',
            });
        }

        $data = $this->decode((string) $response->json('candidates.0.content.parts.0.text'));

        if ($data === null) {
            throw new GeminiVisionException(
                'The AI scanner could not read this photo. Try a clearer photo, or fill in the form by hand.'
            );
        }

        // Business rule: physical hardware goods only.
        if (($data['is_hardware'] ?? true) === false) {
            throw new GeminiVisionException(
                'This does not look like a hardware product. Take a photo of the item or its packaging and try again.', 422
            );
        }

        return [
            'name' => $this->text($data['name'] ?? null, 255),
            'category' => $this->category($data['category'] ?? null, $categories),
            'unit_type' => $this->unitType($data['unit_type'] ?? null),
            'sku' => $this->uniqueSku($data['sku'] ?? null),
            'description' => $this->text($data['description'] ?? null, 2000),
        ];
    }

    /** The instructions sent with the photo. */
    private function prompt(array $categories): string
    {
        $categoryList = implode(', ', $categories ?: Product::CATEGORIES);
        $unitList = implode(', ', Product::UNIT_TYPES);

        return <<<PROMPT
        You are helping a Malaysian hardware shop add a product to its inventory.
        Look at the photo and describe the single main hardware item in it.

        Reply with one JSON object and nothing else, using exactly these keys:
        - "is_hardware": true if the photo shows a physical hardware or building-supply product, otherwise false.
        - "name": a short shop-style product name with brand, type and size when they are visible (for example "YTL Castle Portland Cement 50kg"). Do not invent a brand or size you cannot see.
        - "category": the best match from this list: {$categoryList}. If none fits, give a short new category.
        - "unit_type": how the item is normally sold, exactly one of: {$unitList}.
        - "sku": a short stock code in capitals in the form HW-XXX-NNN, where XXX is 3 letters for the product type.
        - "description": one plain sentence saying what the item is used for.

        If "is_hardware" is false, set the other keys to null.
        PROMPT;
    }

    /**
     * Turn Gemini's text into an array. Gemini sometimes wraps JSON in ```json fences.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        $data = json_decode($text, true);

        // A list with one object is accepted too.
        if (is_array($data) && array_is_list($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        return is_array($data) && ! array_is_list($data) ? $data : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $max, '');
    }

    /** Prefer the shop's own spelling when the AI's category matches one, ignoring upper and lower case. */
    private function category(mixed $value, array $categories): ?string
    {
        $value = $this->text($value, 50);

        if ($value === null) {
            return null;
        }

        foreach ($categories ?: Product::CATEGORIES as $known) {
            if (strcasecmp($known, $value) === 0) {
                return $known;
            }
        }

        return $value;
    }

    /** Only the eight physical unit types are allowed. Anything else is left for the Owner to choose. */
    private function unitType(mixed $value): ?string
    {
        $value = is_string($value) ? strtoupper(trim($value)) : null;

        return in_array($value, Product::UNIT_TYPES, true) ? $value : null;
    }

    /** Clean the suggested SKU and make sure it is not already used by another product. */
    private function uniqueSku(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $sku = preg_replace('/[^A-Z0-9.\-]/', '', strtoupper(trim($value))) ?? '';
        $sku = ltrim(substr($sku, 0, 40), '.-');

        if ($sku === '') {
            return null;
        }

        $candidate = $sku;

        for ($n = 2; Product::where('sku', $candidate)->exists(); $n++) {
            if ($n > 50) {
                return null; // give up and let the Owner type one
            }

            $candidate = "{$sku}-{$n}";
        }

        return $candidate;
    }
}
