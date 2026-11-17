<?php

namespace Tests\Feature\Module2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 2: who can open what, and what the product list shows to each role.
 */
class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create();
    }

    // ---------- access control ----------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('products.report'))->assertRedirect(route('login'));
    }

    public function test_cashiers_get_403_on_every_owner_only_route(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 20]);

        $this->actingAs($this->cashier);

        $this->get(route('products.create'))->assertForbidden();
        $this->post(route('products.store'), ['sku' => 'HW-X', 'name' => 'X'])->assertForbidden();
        $this->get(route('products.edit', $product))->assertForbidden();
        $this->put(route('products.update', $product), ['name' => 'Hacked'])->assertForbidden();
        $this->patch(route('products.add-stock', $product), ['add_quantity' => 5])->assertForbidden();
        $this->patch(route('products.toggle-status', $product))->assertForbidden();
        $this->delete(route('products.destroy', $product))->assertForbidden();
        $this->get(route('products.report'))->assertForbidden();

        $fresh = $product->fresh();
        $this->assertSame($product->name, $fresh->name);
        $this->assertSame(20, $fresh->stock_quantity);
        $this->assertSame(Product::STATUS_ACTIVE, $fresh->status);
    }

    // ---------- what each role sees ----------

    public function test_owner_sees_cost_price_and_action_buttons(): void
    {
        Product::factory()->create([
            'name' => 'YTL Castle Cement 50kg',
            'supplier_cost_price' => 16.37,
            'retail_selling_price' => 22.00,
        ]);

        $this->actingAs($this->owner)->get(route('products.index'))
            ->assertOk()
            ->assertSee('YTL Castle Cement 50kg')
            ->assertSee('Cost (RM)')
            ->assertSee('16.37')
            ->assertSee('22.00')
            ->assertSee('Add hardware product')
            ->assertSee('Restock');
    }

    public function test_cashier_sees_retail_price_but_never_the_cost_price(): void
    {
        Product::factory()->create([
            'name' => 'YTL Castle Cement 50kg',
            'supplier_cost_price' => 16.37,
            'retail_selling_price' => 22.00,
        ]);

        $this->actingAs($this->cashier)->get(route('products.index'))
            ->assertOk()
            ->assertSee('YTL Castle Cement 50kg')
            ->assertSee('22.00')
            ->assertDontSee('16.37')
            ->assertDontSee('Cost (RM)')
            ->assertDontSee('Add hardware product')
            ->assertDontSee('Restock')
            ->assertDontSee('Inventory report');
    }

    public function test_cashier_cannot_see_archived_products_even_with_the_status_filter(): void
    {
        Product::factory()->create(['name' => 'Active Hammer']);
        Product::factory()->archived()->create(['name' => 'Old Discontinued Drill']);

        $this->actingAs($this->cashier)->get(route('products.index', ['status' => 'ARCHIVED']))
            ->assertSee('Active Hammer')
            ->assertDontSee('Old Discontinued Drill');

        $this->actingAs($this->owner)->get(route('products.index', ['status' => 'ARCHIVED']))
            ->assertSee('Old Discontinued Drill')
            ->assertDontSee('Active Hammer');
    }

    // ---------- search, filters, low-stock badge ----------

    public function test_search_matches_name_or_sku(): void
    {
        Product::factory()->create(['sku' => 'HW-CEM-50KG', 'name' => 'YTL Castle Cement 50kg']);
        Product::factory()->create(['sku' => 'HW-NAIL-2IN', 'name' => 'Steel Wire Nails']);

        $this->actingAs($this->owner);

        $this->get(route('products.index', ['q' => 'cement']))
            ->assertSee('YTL Castle Cement 50kg')->assertDontSee('Steel Wire Nails');

        $this->get(route('products.index', ['q' => 'nail-2']))
            ->assertSee('Steel Wire Nails')->assertDontSee('YTL Castle Cement 50kg');
    }

    public function test_category_filter(): void
    {
        Product::factory()->create(['name' => 'White Paint 5L', 'category' => 'Paint']);
        Product::factory()->create(['name' => 'Claw Hammer', 'category' => 'Tools']);

        $this->actingAs($this->cashier)->get(route('products.index', ['category' => 'Paint']))
            ->assertSee('White Paint 5L')
            ->assertDontSee('Claw Hammer');
    }

    public function test_low_stock_badge_and_filter(): void
    {
        Product::factory()->create(['name' => 'Plenty Pipe', 'stock_quantity' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->create(['name' => 'Scarce Screws', 'stock_quantity' => 10, 'low_stock_threshold' => 10]);
        Product::factory()->create(['name' => 'Empty Plywood', 'stock_quantity' => 0, 'low_stock_threshold' => 5]);

        $this->actingAs($this->cashier);

        $this->get(route('products.index'))
            ->assertSee('LOW STOCK')
            ->assertSee('OUT OF STOCK')
            ->assertSee('2</strong>', false); // the warning banner counts both

        $this->get(route('products.index', ['low_stock' => 1]))
            ->assertSee('Scarce Screws')
            ->assertSee('Empty Plywood')
            ->assertDontSee('Plenty Pipe');
    }

    public function test_the_list_is_paginated_ten_per_page(): void
    {
        Product::factory()->count(12)->create();

        $response = $this->actingAs($this->owner)->get(route('products.index'));

        $this->assertCount(10, $response->viewData('products'));
        $this->assertSame(12, $response->viewData('products')->total());
    }
}
