<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiVisionException;
use App\Models\Product;
use App\Services\GeminiVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module 2: AI Photo Inventory Scanner. Owner only (enforced in routes/web.php).
 *
 * The product form sends the chosen photo here in the background and gets
 * back suggestions as JSON. This never saves anything.
 */
class ProductScanController extends Controller
{
    public function __invoke(Request $request, GeminiVisionService $scanner): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5 MB
        ], [
            'photo.max' => 'The photo is too large. Use one smaller than 5 MB.',
        ]);

        $photo = $request->file('photo');

        // Offer the shop's existing categories so the AI reuses them instead of inventing new ones.
        $categories = Product::query()->whereNotNull('category')->distinct()->pluck('category')->all();
        $categories = array_values(array_unique(array_merge($categories, Product::CATEGORIES)));

        try {
            $suggestion = $scanner->describeProduct($photo->get(), (string) $photo->getMimeType(), $categories);
        } catch (GeminiVisionException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['suggestion' => $suggestion]);
    }
}
