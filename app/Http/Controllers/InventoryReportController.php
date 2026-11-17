<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Module 2: Inventory Valuation & Low-Stock Report. Owner only.
 * Counts ACTIVE products only, because archived items are no longer for sale.
 */
class InventoryReportController extends Controller
{
    public function __invoke(): View
    {
        // One query: the database does the SUM(stock x price) maths.
        $totals = Product::active()
            ->selectRaw('COUNT(*) as product_count')
            ->selectRaw('COALESCE(SUM(stock_quantity), 0) as total_units')
            ->selectRaw('COALESCE(SUM(stock_quantity * supplier_cost_price), 0) as cost_value')
            ->selectRaw('COALESCE(SUM(stock_quantity * retail_selling_price), 0) as retail_value')
            ->toBase()
            ->first();

        $byCategory = Product::active()
            ->select(DB::raw("COALESCE(category, 'Uncategorised') as category_name"))
            ->selectRaw('COUNT(*) as product_count')
            ->selectRaw('COALESCE(SUM(stock_quantity * supplier_cost_price), 0) as cost_value')
            ->selectRaw('COALESCE(SUM(stock_quantity * retail_selling_price), 0) as retail_value')
            ->groupBy('category_name')
            ->orderByDesc('cost_value')
            ->toBase()
            ->get();

        $lowStock = Product::active()->lowStock()
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get();

        return view('products.report', [
            'summary' => [
                'productCount' => (int) $totals->product_count,
                'totalUnits' => (int) $totals->total_units,
                'costValue' => (float) $totals->cost_value,
                'retailValue' => (float) $totals->retail_value,
                'potentialProfit' => (float) $totals->retail_value - (float) $totals->cost_value,
            ],
            'byCategory' => $byCategory,
            'lowStock' => $lowStock,
            'archivedCount' => Product::where('status', Product::STATUS_ARCHIVED)->count(),
            'generatedAt' => now(),
        ]);
    }
}
