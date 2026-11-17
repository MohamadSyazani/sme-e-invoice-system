<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2: Hardware Product & Inventory Management.
 */
#[Fillable([
    'sku', 'name', 'category', 'description', 'image_path',
    'unit_type', 'lhdn_class_code',
    'supplier_cost_price', 'retail_selling_price',
    'stock_quantity', 'low_stock_threshold', 'status',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const UNIT_TYPES = ['PIECE', 'BAG', 'KG', 'METER', 'BOX', 'TIN', 'ROLL', 'SET'];

    /** Suggestions for the form. Any other category saved in the table is offered too. */
    public const CATEGORIES = [
        'Building Materials',
        'Plumbing',
        'Hand Tools',
        'Fasteners',
        'Paint & Coating',
        'Cabinet Hardware',
        'Timber',
        'Electrical',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    public const LHDN_CLASS_CODE = '022';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_cost_price' => 'decimal:2',
            'retail_selling_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    // ---------- query scopes ----------

    /** Product::active()->get() */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** Product::active()->lowStock()->get() */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /** Product::search('cement')->get() matches the name or the SKU. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(
            fn (Builder $w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
        );
    }

    // ---------- helpers ----------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Low-Stock Alert Rule: drives the LOW STOCK badge in the views. */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Soft-Archive Rule: has this product appeared on any past invoice?
     * Uses the query builder so Module 2 does not depend on Module 4's model class,
     * and returns false until Module 4's transaction_items table exists.
     */
    public function hasSalesHistory(): bool
    {
        if (! Schema::hasTable('transaction_items')) {
            return false;
        }

        return DB::table('transaction_items')->where('product_id', $this->id)->exists();
    }

    /**
     * Used by the controller's destroy(): products with sales history are archived,
     * products that were never sold are really deleted.
     *
     * @return 'ARCHIVED'|'DELETED'
     */
    public function deleteOrArchive(): string
    {
        if ($this->hasSalesHistory()) {
            $this->update(['status' => self::STATUS_ARCHIVED]);

            return 'ARCHIVED';
        }

        $this->delete();

        return 'DELETED';
    }
}
