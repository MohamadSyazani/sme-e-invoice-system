<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Module 2: Hardware Product & Inventory Management.
 *
 * index()          Owner and Cashier (Cashier is read-only and never receives cost prices)
 * everything else  Owner only (enforced in routes/web.php)
 */
class ProductController extends Controller
{
    /** Columns a Cashier is allowed to load. supplier_cost_price is deliberately missing. */
    private const CASHIER_COLUMNS = [
        'id', 'sku', 'name', 'category', 'unit_type', 'image_path',
        'retail_selling_price', 'stock_quantity', 'low_stock_threshold', 'status',
    ];

    public function index(Request $request): View
    {
        $isOwner = $request->user()->isOwner();

        $products = Product::query()
            // The cost price is never read from the database for a Cashier,
            // so it cannot leak into the page by mistake.
            ->when(! $isOwner, fn ($query) => $query->select(self::CASHIER_COLUMNS)->active())
            ->when($isOwner && in_array($request->query('status'), Product::STATUSES, true),
                fn ($query) => $query->where('status', $request->query('status')))
            ->search($request->query('q'))
            ->when(filled($request->query('category')),
                fn ($query) => $query->where('category', $request->query('category')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'isOwner' => $isOwner,
            'categories' => $this->categoryOptions(),
            'lowStockCount' => Product::active()->lowStock()->count(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', ['categories' => $this->categoryOptions()]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->productData() + [
            'lhdn_class_code' => Product::LHDN_CLASS_CODE, // physical hardware is always 022
            'status' => Product::STATUS_ACTIVE,
            // Saved on the public disk as products/<random name>.jpg
            'image_path' => $request->file('image')?->store('products', 'public'),
        ]);

        return redirect()->route('products.index')
            ->with('success', "{$product->name} was added to the inventory.");
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->productData();

        if ($request->hasFile('image')) {
            // A new photo replaces the old one.
            $product->deleteImageFile();
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $product->deleteImageFile();
            $data['image_path'] = null;
        }

        $product->update($data);

        return redirect()->route('products.index')
            ->with('success', "{$product->name} was updated.");
    }

    /** Restock button: adds the delivered quantity when a supplier lorry arrives. */
    public function addStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validateWithBag('restock', [
            'add_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [], ['add_quantity' => 'quantity delivered']);

        // increment() runs "stock_quantity = stock_quantity + ?" inside the database,
        // so a sale happening at the same moment cannot be overwritten.
        $product->increment('stock_quantity', $data['add_quantity']);

        return back()->with('success',
            "Added {$data['add_quantity']} to {$product->name}. Stock is now {$product->stock_quantity}.");
    }

    /** Archive / Restore button on the product list. */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update([
            'status' => $product->isActive() ? Product::STATUS_ARCHIVED : Product::STATUS_ACTIVE,
        ]);

        return back()->with('success', $product->isActive()
            ? "{$product->name} was restored and can be sold again."
            : "{$product->name} was archived and is hidden from cashiers.");
    }

    /** Soft-Archive Rule: products that were sold before are archived, never hard-deleted. */
    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;

        if ($product->deleteOrArchive() === 'ARCHIVED') {
            return redirect()->route('products.index')
                ->with('warning', "{$name} has sales history, so it was archived instead of deleted.");
        }

        return redirect()->route('products.index')
            ->with('success', "{$name} was deleted.");
    }

    /**
     * The suggested categories plus any category already saved in the table.
     *
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        $saved = Product::query()->whereNotNull('category')->distinct()->pluck('category')->all();

        $options = array_values(array_unique(array_merge(Product::CATEGORIES, $saved)));
        sort($options);

        return $options;
    }
}
