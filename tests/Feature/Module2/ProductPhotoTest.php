<?php

namespace Tests\Feature\Module2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module 2: saving, replacing and removing the product photo (image_path).
 */
class ProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public'); // photos go to a throwaway folder during tests
        $this->actingAs(User::factory()->owner()->create());
    }

    /** @return array<string, mixed> */
    private function validProduct(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'HW-CEM-050',
            'name' => 'YTL Castle Cement 50kg',
            'category' => 'Building Materials',
            'unit_type' => 'BAG',
            'supplier_cost_price' => '16.50',
            'retail_selling_price' => '22.00',
            'stock_quantity' => 100,
            'low_stock_threshold' => 15,
        ], $overrides);
    }

    /**
     * A real (1x1 pixel) PNG image. Built from fixed bytes instead of
     * UploadedFile::fake()->image(), so the tests also run on a PHP without the GD extension.
     */
    private function photo(string $name = 'cement.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::TINY_PNG));
    }

    public function test_a_product_can_be_added_with_a_photo(): void
    {
        $this->post(route('products.store'), $this->validProduct(['image' => $this->photo()]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHasNoErrors();

        $product = Product::firstWhere('sku', 'HW-CEM-050');

        $this->assertNotNull($product->image_path);
        $this->assertStringStartsWith('products/', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_a_product_can_be_added_without_a_photo(): void
    {
        $this->post(route('products.store'), $this->validProduct())->assertSessionHasNoErrors();

        $this->assertNull(Product::firstWhere('sku', 'HW-CEM-050')->image_path);
    }

    public function test_only_image_files_up_to_5mb_are_accepted(): void
    {
        $this->post(route('products.store'), $this->validProduct([
            'image' => UploadedFile::fake()->create('price-list.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('image');

        $this->post(route('products.store'), $this->validProduct([
            'image' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'), // 6 MB
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_uploading_a_new_photo_replaces_and_deletes_the_old_one(): void
    {
        $old = $this->photo('old.png')->store('products', 'public');
        $product = Product::factory()->create(['sku' => 'HW-CEM-050', 'image_path' => $old]);

        $this->put(route('products.update', $product), $this->validProduct(['image' => $this->photo('new.png')]))
            ->assertSessionHasNoErrors();

        $new = $product->fresh()->image_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_editing_without_choosing_a_photo_keeps_the_current_one(): void
    {
        $path = $this->photo()->store('products', 'public');
        $product = Product::factory()->create(['sku' => 'HW-CEM-050', 'image_path' => $path]);

        $this->put(route('products.update', $product), $this->validProduct(['name' => 'Renamed Cement']));

        $this->assertSame($path, $product->fresh()->image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_photo_can_be_removed(): void
    {
        $path = $this->photo()->store('products', 'public');
        $product = Product::factory()->create(['sku' => 'HW-CEM-050', 'image_path' => $path]);

        $this->put(route('products.update', $product), $this->validProduct(['remove_image' => '1']));

        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_a_never_sold_product_also_deletes_its_photo(): void
    {
        $path = $this->photo()->store('products', 'public');
        $product = Product::factory()->create(['image_path' => $path]);

        $this->delete(route('products.destroy', $product));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_archiving_keeps_the_photo(): void
    {
        $path = $this->photo()->store('products', 'public');
        $product = Product::factory()->create(['image_path' => $path]);

        $this->patch(route('products.toggle-status', $product));

        $this->assertSame($path, $product->fresh()->image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_list_shows_a_thumbnail_only_when_the_file_exists_on_this_computer(): void
    {
        $path = $this->photo()->store('products', 'public');
        Product::factory()->create(['name' => 'Has Photo', 'image_path' => $path]);
        // A row uploaded from a teammate's laptop: the path is in the database but the file is not here.
        Product::factory()->create(['name' => 'Photo Elsewhere', 'image_path' => 'products/not-on-this-laptop.jpg']);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('storage/'.$path)
            ->assertDontSee('not-on-this-laptop.jpg');
    }
}
