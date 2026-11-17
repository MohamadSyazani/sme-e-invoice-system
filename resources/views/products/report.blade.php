@extends('layouts.app')

@section('title', 'Inventory Report')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Inventory valuation &amp; low-stock report</h1>
            <div class="text-muted small">
                Generated {{ $generatedAt->format('d M Y, h:i A') }}.
                Active products only{{ $archivedCount > 0 ? " ({$archivedCount} archived not counted)" : '' }}.
            </div>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Back to products</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Active products</div>
                    <div class="h3 fw-semibold mb-0">{{ number_format($summary['productCount']) }}</div>
                    <div class="text-muted small">{{ number_format($summary['totalUnits']) }} units in stock</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Stock value at cost</div>
                    <div class="h3 fw-semibold mb-0">RM {{ number_format($summary['costValue'], 2) }}</div>
                    <div class="text-muted small">What the shop paid for it</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Stock value at retail</div>
                    <div class="h3 fw-semibold mb-0">RM {{ number_format($summary['retailValue'], 2) }}</div>
                    <div class="text-muted small">If everything sells at full price</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Potential gross profit</div>
                    <div class="h3 fw-semibold mb-0 text-success">RM {{ number_format($summary['potentialProfit'], 2) }}</div>
                    <div class="text-muted small">Retail value minus cost value</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Valuation by category</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Category</th>
                        <th class="text-end">Products</th>
                        <th class="text-end">Value at cost (RM)</th>
                        <th class="text-end">Value at retail (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byCategory as $row)
                        <tr>
                            <td>{{ $row->category_name }}</td>
                            <td class="text-end">{{ number_format($row->product_count) }}</td>
                            <td class="text-end">{{ number_format($row->cost_value, 2) }}</td>
                            <td class="text-end">{{ number_format($row->retail_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No active products yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($byCategory->isNotEmpty())
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end">{{ number_format($summary['productCount']) }}</td>
                            <td class="text-end">{{ number_format($summary['costValue'], 2) }}</td>
                            <td class="text-end">{{ number_format($summary['retailValue'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card shadow-sm {{ $lowStock->isNotEmpty() ? 'border-warning' : '' }}">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>Low-stock products</span>
            <span class="badge {{ $lowStock->isNotEmpty() ? 'text-bg-warning' : 'text-bg-success' }}">{{ $lowStock->count() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th class="text-end">In stock</th>
                        <th class="text-end">Alert level</th>
                        <th class="text-end">Cost each (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStock as $product)
                        <tr>
                            <td><code>{{ $product->sku }}</code></td>
                            <td>{{ $product->name }}</td>
                            <td class="text-end {{ $product->stock_quantity === 0 ? 'text-danger fw-semibold' : '' }}">
                                {{ number_format($product->stock_quantity) }} {{ strtolower($product->unit_type) }}
                            </td>
                            <td class="text-end">{{ number_format($product->low_stock_threshold) }}</td>
                            <td class="text-end">{{ number_format($product->supplier_cost_price, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Nothing is running low.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
