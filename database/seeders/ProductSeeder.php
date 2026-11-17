<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Module 2: sample hardware catalog so the screens are not empty after
 * `php artisan migrate --seed`. Uses firstOrCreate, so running it again
 * (or running it against the shared database) never overwrites real prices or stock.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // sku, name, category, unit, cost, retail, stock, low-stock threshold
        $products = [
            ['HW-CEM-50KG', 'YTL Castle Cement 50kg', 'Building Materials', 'BAG', 16.50, 22.00, 100, 15],
            ['HW-SND-25KG', 'Fine River Sand 25kg', 'Building Materials', 'BAG', 4.20, 6.50, 60, 10],
            ['HW-NAIL-2IN', 'Steel Wire Nails 2 inch', 'Fasteners', 'KG', 5.80, 8.50, 40, 8],
            ['HW-SCRW-BOX', 'Wood Screws 1.5 inch (box of 100)', 'Fasteners', 'BOX', 6.00, 9.90, 8, 10],
            ['HW-PVC-20MM', 'PVC Pipe 20mm Class D', 'Plumbing', 'METER', 2.10, 3.50, 250, 30],
            ['HW-GLUE-PVC', 'PVC Solvent Cement 100g', 'Plumbing', 'TIN', 4.50, 7.00, 25, 6],
            ['HW-PNT-5L-W', 'Nippon Vinilex 5000 White 5L', 'Paint & Coating', 'TIN', 58.00, 79.00, 18, 5],
            ['HW-HMR-CLAW', 'Claw Hammer 16oz', 'Hand Tools', 'PIECE', 14.00, 22.90, 12, 4],
            ['HW-PLY-12MM', 'Plywood Sheet 12mm 4x8', 'Timber', 'PIECE', 52.00, 68.00, 4, 5],
            ['HW-WIRE-2.5', 'PVC Insulated Wire 2.5mm 100m', 'Electrical', 'ROLL', 118.00, 149.00, 9, 3],
        ];

        foreach ($products as [$sku, $name, $category, $unit, $cost, $retail, $stock, $threshold]) {
            Product::firstOrCreate(['sku' => $sku], [
                'name' => $name,
                'category' => $category,
                'unit_type' => $unit,
                'lhdn_class_code' => Product::LHDN_CLASS_CODE,
                'supplier_cost_price' => $cost,
                'retail_selling_price' => $retail,
                'stock_quantity' => $stock,
                'low_stock_threshold' => $threshold,
                'status' => Product::STATUS_ACTIVE,
            ]);
        }
    }
}
