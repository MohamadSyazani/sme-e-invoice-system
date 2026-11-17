<?php

namespace Tests\Feature\Module2;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 2: Inventory Valuation & Low-Stock Report maths.
 */
class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valuation_totals_count_active_products_only(): void
    {
        // 100 x 16.50 = 1650.00 at cost, 100 x 22.00 = 2200.00 at retail
        Product::factory()->create([
            'category' => 'Cement & Aggregates',
            'supplier_cost_price' => 16.50, 'retail_selling_price' => 22.00, 'stock_quantity' => 100,
        ]);
        // 8 x 6.00 = 48.00 at cost, 8 x 9.90 = 79.20 at retail
        Product::factory()->create([
            'category' => null,
            'supplier_cost_price' => 6.00, 'retail_selling_price' => 9.90, 'stock_quantity' => 8,
        ]);
        // Archived: must be ignored
        Product::factory()->archived()->create([
            'supplier_cost_price' => 1000, 'retail_selling_price' => 2000, 'stock_quantity' => 50,
        ]);

        $response = $this->actingAs(User::factory()->owner()->create())
            ->get(route('products.report'))
            ->assertOk();

        $summary = $response->viewData('summary');

        $this->assertSame(2, $summary['productCount']);
        $this->assertSame(108, $summary['totalUnits']);
        $this->assertEqualsWithDelta(1698.00, $summary['costValue'], 0.001);
        $this->assertEqualsWithDelta(2279.20, $summary['retailValue'], 0.001);
        $this->assertEqualsWithDelta(581.20, $summary['potentialProfit'], 0.001);

        $response->assertSee('1,698.00')->assertSee('2,279.20')->assertSee('581.20')
            ->assertSee('Uncategorised')
            ->assertSee('1 archived not counted');
    }

    public function test_low_stock_list_shows_only_active_products_at_or_below_their_alert_level(): void
    {
        Product::factory()->create(['name' => 'Plenty Pipe', 'stock_quantity' => 50, 'low_stock_threshold' => 10]);
        Product::factory()->create(['name' => 'Scarce Screws', 'stock_quantity' => 10, 'low_stock_threshold' => 10]);
        Product::factory()->archived()->lowStock()->create(['name' => 'Archived Low Item']);

        $response = $this->actingAs(User::factory()->owner()->create())->get(route('products.report'));

        $this->assertSame(['Scarce Screws'], $response->viewData('lowStock')->pluck('name')->all());
    }

    public function test_report_works_with_no_products(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('products.report'))
            ->assertOk()
            ->assertSee('No active products yet.')
            ->assertSee('Nothing is running low.');
    }
}
