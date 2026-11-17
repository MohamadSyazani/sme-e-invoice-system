<?php

namespace Tests\Feature\Module2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Module 2: Owner add / edit / restock / archive / delete rules.
 */
class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->actingAs($this->owner);
    }

    /** @return array<string, mixed> */
    private function validProduct(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'HW-CEM-50KG',
            'name' => 'YTL Castle Cement 50kg',
            'category' => 'Cement & Aggregates',
            'description' => 'Ordinary Portland cement.',
            'unit_type' => 'BAG',
            'supplier_cost_price' => '16.50',
            'retail_selling_price' => '22.00',
            'stock_quantity' => 100,
            'low_stock_threshold' => 15,
        ], $overrides);
    }

    // ---------- create ----------

    public function test_owner_can_add_a_product(): void
    {
        $this->get(route('products.create'))->assertOk()->assertSee('Supplier cost price');

        $this->post(route('products.store'), $this->validProduct())
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => 'HW-CEM-50KG',
            'name' => 'YTL Castle Cement 50kg',
            'unit_type' => 'BAG',
            'lhdn_class_code' => '022',
            'supplier_cost_price' => 16.50,
            'retail_selling_price' => 22.00,
            'stock_quantity' => 100,
            'low_stock_threshold' => 15,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_sku_is_saved_in_upper_case(): void
    {
        $this->post(route('products.store'), $this->validProduct(['sku' => ' hw-cem-50kg ']));

        $this->assertDatabaseHas('products', ['sku' => 'HW-CEM-50KG']);
    }

    public function test_lhdn_class_code_and_status_cannot_be_set_from_the_form(): void
    {
        $this->post(route('products.store'), $this->validProduct([
            'lhdn_class_code' => '999',
            'status' => 'ARCHIVED',
        ]));

        $this->assertDatabaseHas('products', [
            'sku' => 'HW-CEM-50KG', 'lhdn_class_code' => '022', 'status' => 'ACTIVE',
        ]);
    }

    public function test_retail_price_cannot_be_lower_than_cost_price(): void
    {
        $this->post(route('products.store'), $this->validProduct([
            'supplier_cost_price' => '22.00',
            'retail_selling_price' => '21.99',
        ]))->assertSessionHasErrors('retail_selling_price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_retail_price_may_equal_cost_price(): void
    {
        $this->post(route('products.store'), $this->validProduct([
            'supplier_cost_price' => '22.00',
            'retail_selling_price' => '22.00',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'HW-CEM-50KG']);

        $this->post(route('products.store'), $this->validProduct(['sku' => 'hw-cem-50kg']))
            ->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->post(route('products.store'), $this->validProduct([
            'sku' => 'has spaces!',
            'name' => '',
            'unit_type' => 'SERVICE',          // physical hardware units only
            'supplier_cost_price' => '-1',
            'stock_quantity' => '2.5',         // whole units only
            'low_stock_threshold' => '-3',
        ]))->assertSessionHasErrors([
            'sku', 'name', 'unit_type', 'supplier_cost_price', 'stock_quantity', 'low_stock_threshold',
        ]);

        $this->assertDatabaseCount('products', 0);
    }

    // ---------- update ----------

    public function test_owner_can_edit_a_product_and_keep_its_own_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'HW-CEM-50KG']);

        $this->get(route('products.edit', $product))->assertOk()->assertSee('HW-CEM-50KG');

        $this->put(route('products.update', $product), $this->validProduct([
            'name' => 'YTL Castle Cement 50kg (New Bag)',
            'retail_selling_price' => '23.50',
        ]))->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'HW-CEM-50KG',
            'name' => 'YTL Castle Cement 50kg (New Bag)',
            'retail_selling_price' => 23.50,
        ]);
    }

    public function test_editing_cannot_take_another_products_sku(): void
    {
        Product::factory()->create(['sku' => 'HW-TAKEN']);
        $product = Product::factory()->create(['sku' => 'HW-MINE']);

        $this->put(route('products.update', $product), $this->validProduct(['sku' => 'HW-TAKEN']))
            ->assertSessionHasErrors('sku');

        $this->assertSame('HW-MINE', $product->fresh()->sku);
    }

    public function test_edit_is_also_blocked_from_selling_below_cost(): void
    {
        $product = Product::factory()->create(['supplier_cost_price' => 10, 'retail_selling_price' => 15]);

        $this->put(route('products.update', $product), $this->validProduct([
            'supplier_cost_price' => '20.00',
            'retail_selling_price' => '15.00',
        ]))->assertSessionHasErrors('retail_selling_price');

        $this->assertSame('10.00', $product->fresh()->supplier_cost_price);
    }

    // ---------- restock ----------

    public function test_restock_adds_to_the_current_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 12]);

        $this->from(route('products.index'))
            ->patch(route('products.add-stock', $product), ['add_quantity' => 50])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertSame(62, $product->fresh()->stock_quantity);
    }

    public function test_restock_rejects_zero_negative_and_decimal_quantities(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 12]);

        foreach ([0, -5, '1.5', ''] as $bad) {
            $this->patch(route('products.add-stock', $product), ['add_quantity' => $bad])
                ->assertSessionHasErrorsIn('restock', 'add_quantity');
        }

        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    // ---------- archive / restore / delete ----------

    public function test_owner_can_archive_and_restore_a_product(): void
    {
        $product = Product::factory()->create();

        $this->patch(route('products.toggle-status', $product));
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);

        $this->patch(route('products.toggle-status', $product));
        $this->assertSame(Product::STATUS_ACTIVE, $product->fresh()->status);
    }

    public function test_a_product_that_was_never_sold_is_really_deleted(): void
    {
        $product = Product::factory()->create();

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_a_product_with_sales_history_is_archived_instead_of_deleted(): void
    {
        // Stand-in for Module 4's table: only the column Module 2 reads.
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
        });

        $sold = Product::factory()->create();
        $neverSold = Product::factory()->create();
        DB::table('transaction_items')->insert(['product_id' => $sold->id]);

        $this->delete(route('products.destroy', $sold))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('products', ['id' => $sold->id, 'status' => 'ARCHIVED']);

        $this->delete(route('products.destroy', $neverSold));
        $this->assertDatabaseMissing('products', ['id' => $neverSold->id]);
    }
}
