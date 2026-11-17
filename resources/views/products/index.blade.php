@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Hardware products</h1>
            @unless ($isOwner)
                <div class="text-muted small">Read-only catalog: stock levels and retail prices.</div>
            @endunless
        </div>
        @if ($isOwner)
            <div class="d-flex gap-2">
                <a href="{{ route('products.report') }}" class="btn btn-outline-secondary">Inventory report</a>
                <a href="{{ route('products.create') }}" class="btn btn-primary">Add hardware product</a>
            </div>
        @endif
    </div>

    @if ($lowStockCount > 0)
        <div class="alert alert-warning d-flex justify-content-between align-items-center py-2">
            <span>
                <strong>{{ $lowStockCount }}</strong>
                {{ $lowStockCount === 1 ? 'product is' : 'products are' }} at or below the low-stock level.
            </span>
            <a href="{{ route('products.index', ['low_stock' => 1]) }}" class="btn btn-sm btn-warning">Show them</a>
        </div>
    @endif

    <form method="GET" action="{{ route('products.index') }}" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label small mb-1">Search</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control"
                       placeholder="Name or SKU">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label small mb-1">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            @if ($isOwner)
                <div class="col-md-2">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Product::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-auto">
                <div class="form-check mb-2">
                    <input type="checkbox" id="low_stock" name="low_stock" value="1" class="form-check-input"
                           @checked(request()->boolean('low_stock'))>
                    <label for="low_stock" class="form-check-label small">Low stock only</label>
                </div>
            </div>
            <div class="col-md d-flex gap-2 justify-content-md-end">
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">Stock</th>
                        {{-- The cost column is not rendered at all for cashiers (and the value is never loaded). --}}
                        @if ($isOwner)
                            <th class="text-end">Cost (RM)</th>
                        @endif
                        <th class="text-end">Retail (RM)</th>
                        @if ($isOwner)
                            <th class="text-end">Margin</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr @class(['table-secondary' => ! $product->isActive()])>
                            <td class="text-nowrap"><code>{{ $product->sku }}</code></td>
                            <td>{{ $product->name }}</td>
                            <td class="text-muted small">{{ $product->category ?? '-' }}</td>
                            <td class="text-end text-nowrap">
                                {{ number_format($product->stock_quantity) }}
                                <span class="text-muted small">{{ strtolower($product->unit_type) }}</span>
                                @if ($product->isActive() && $product->isLowStock())
                                    <span class="badge {{ $product->stock_quantity === 0 ? 'text-bg-danger' : 'text-bg-warning' }}">
                                        {{ $product->stock_quantity === 0 ? 'OUT OF STOCK' : 'LOW STOCK' }}
                                    </span>
                                @endif
                            </td>
                            @if ($isOwner)
                                <td class="text-end">{{ number_format($product->supplier_cost_price, 2) }}</td>
                            @endif
                            <td class="text-end fw-semibold">{{ number_format($product->retail_selling_price, 2) }}</td>
                            @if ($isOwner)
                                <td class="text-end text-muted small">
                                    {{ number_format($product->retail_selling_price - $product->supplier_cost_price, 2) }}
                                </td>
                                <td>
                                    <span class="badge {{ $product->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $product->status }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($product->isActive())
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal" data-bs-target="#restockModal"
                                                data-action="{{ route('products.add-stock', $product) }}"
                                                data-name="{{ $product->name }}"
                                                data-stock="{{ number_format($product->stock_quantity) }} {{ strtolower($product->unit_type) }}">
                                            Restock
                                        </button>
                                    @endif

                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>

                                    <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-sm {{ $product->isActive() ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $product->isActive() ? 'Archive' : 'Restore' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($product->name) }}? If it was sold before, it will be archived instead.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOwner ? 9 : 5 }}" class="text-center text-muted py-4">
                                No products match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $products->links('pagination::bootstrap-5') }}
    </div>

    @if ($isOwner)
        {{-- One shared Restock dialog. The clicked button fills in which product it is for. --}}
        <div class="modal fade" id="restockModal" tabindex="-1" aria-labelledby="restockModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ old('restock_action') }}" class="modal-content" id="restockForm">
                    @csrf @method('PATCH')
                    <input type="hidden" name="restock_action" id="restockAction" value="{{ old('restock_action') }}">
                    <input type="hidden" name="restock_name" id="restockNameInput" value="{{ old('restock_name') }}">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="restockModalLabel">
                            Restock <span id="restockName">{{ old('restock_name') }}</span>
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3" id="restockCurrent"></p>
                        <label for="add_quantity" class="form-label">Quantity delivered</label>
                        <input type="number" id="add_quantity" name="add_quantity" min="1" step="1" required
                               value="{{ old('add_quantity') }}"
                               class="form-control @if ($errors->restock->has('add_quantity')) is-invalid @endif">
                        @if ($errors->restock->has('add_quantity'))
                            <div class="invalid-feedback">{{ $errors->restock->first('add_quantity') }}</div>
                        @endif
                        <div class="form-text">This amount is added on top of the current stock.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add to stock</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($isOwner)
    @push('scripts')
        <script>
            const restockModal = document.getElementById('restockModal');

            // Fill the dialog with the product whose Restock button was clicked.
            restockModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                if (! button) return; // reopened after a validation error: keep the old values

                document.getElementById('restockForm').action = button.dataset.action;
                document.getElementById('restockAction').value = button.dataset.action;
                document.getElementById('restockNameInput').value = button.dataset.name;
                document.getElementById('restockName').textContent = button.dataset.name;
                document.getElementById('restockCurrent').textContent = 'Current stock: ' + button.dataset.stock;
                document.getElementById('add_quantity').value = '';
            });

            restockModal.addEventListener('shown.bs.modal', () => {
                document.getElementById('add_quantity').focus();
            });

            // If the quantity was rejected, open the dialog again so the error is visible.
            @if ($errors->restock->any())
                new bootstrap.Modal(restockModal).show();
            @endif
        </script>
    @endpush
@endif
