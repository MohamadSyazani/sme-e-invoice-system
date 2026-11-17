@php
    /** @var \App\Models\Product|null $product */
    $product = $product ?? null;
    $currentUnit = old('unit_type', $product?->unit_type ?? 'PIECE');
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($product)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <label for="sku" class="form-label">SKU</label>
            <input type="text" id="sku" name="sku" value="{{ old('sku', $product?->sku) }}"
                   class="form-control text-uppercase @error('sku') is-invalid @enderror"
                   required maxlength="50" autocomplete="off" placeholder="HW-CEM-50KG">
            @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">The shop's own product code. Must be unique.</div>
        </div>

        <div class="col-md-8">
            <label for="name" class="form-label">Product name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $product?->name) }}"
                   class="form-control @error('name') is-invalid @enderror"
                   required maxlength="255" placeholder="YTL Castle Cement 50kg">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="category" class="form-label">Category <span class="text-muted">(optional)</span></label>
            <input type="text" id="category" name="category" list="categoryOptions"
                   value="{{ old('category', $product?->category) }}"
                   class="form-control @error('category') is-invalid @enderror" maxlength="50">
            <datalist id="categoryOptions">
                @foreach ($categories as $category)
                    <option value="{{ $category }}"></option>
                @endforeach
            </datalist>
            @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Pick a suggestion or type a new one.</div>
        </div>

        <div class="col-md-4">
            <label for="unit_type" class="form-label">Unit type</label>
            <select id="unit_type" name="unit_type" class="form-select @error('unit_type') is-invalid @enderror" required>
                @foreach (\App\Models\Product::UNIT_TYPES as $unit)
                    <option value="{{ $unit }}" @selected($currentUnit === $unit)>{{ ucfirst(strtolower($unit)) }}</option>
                @endforeach
            </select>
            @error('unit_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">How this item is counted and sold.</div>
        </div>

        <div class="col-md-4">
            <label for="lhdn_class_code" class="form-label">LHDN classification code</label>
            <input type="text" id="lhdn_class_code" class="form-control"
                   value="{{ $product?->lhdn_class_code ?? \App\Models\Product::LHDN_CLASS_CODE }}" disabled>
            <div class="form-text">Fixed for physical hardware goods.</div>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description <span class="text-muted">(optional)</span></label>
            <textarea id="description" name="description" rows="2" maxlength="2000"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $product?->description) }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="supplier_cost_price" class="form-label">Supplier cost price</label>
            <div class="input-group has-validation">
                <span class="input-group-text">RM</span>
                <input type="number" id="supplier_cost_price" name="supplier_cost_price" step="0.01" min="0"
                       value="{{ old('supplier_cost_price', $product?->supplier_cost_price) }}"
                       class="form-control @error('supplier_cost_price') is-invalid @enderror" required>
                @error('supplier_cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text">What the shop pays the supplier. Only the Owner can see this.</div>
        </div>

        <div class="col-md-4">
            <label for="retail_selling_price" class="form-label">Retail selling price</label>
            <div class="input-group has-validation">
                <span class="input-group-text">RM</span>
                <input type="number" id="retail_selling_price" name="retail_selling_price" step="0.01" min="0"
                       value="{{ old('retail_selling_price', $product?->retail_selling_price) }}"
                       class="form-control @error('retail_selling_price') is-invalid @enderror" required>
                @error('retail_selling_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text">What the customer pays. Cannot be lower than the cost price.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Margin per unit</label>
            <div class="form-control bg-light" id="marginPreview" aria-live="polite">-</div>
            <div class="form-text">Retail price minus cost price.</div>
        </div>

        <div class="col-md-4">
            <label for="stock_quantity" class="form-label">Stock quantity</label>
            <input type="number" id="stock_quantity" name="stock_quantity" step="1" min="0"
                   value="{{ old('stock_quantity', $product?->stock_quantity ?? 0) }}"
                   class="form-control @error('stock_quantity') is-invalid @enderror" required>
            @error('stock_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">
                @if ($product)
                    Correct the count here. For a new delivery, use Restock on the product list.
                @else
                    How many are in the shop right now.
                @endif
            </div>
        </div>

        <div class="col-md-4">
            <label for="low_stock_threshold" class="form-label">Low-stock alert level</label>
            <input type="number" id="low_stock_threshold" name="low_stock_threshold" step="1" min="0"
                   value="{{ old('low_stock_threshold', $product?->low_stock_threshold ?? 10) }}"
                   class="form-control @error('low_stock_threshold') is-invalid @enderror" required>
            @error('low_stock_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">A LOW STOCK badge shows when stock is at or below this number.</div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $product ? 'Save changes' : 'Add product' }}</button>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@push('scripts')
    <script>
        // Live margin preview. The real check happens on the server in StoreProductRequest.
        const costInput = document.getElementById('supplier_cost_price');
        const retailInput = document.getElementById('retail_selling_price');
        const marginPreview = document.getElementById('marginPreview');

        function updateMargin() {
            const cost = parseFloat(costInput.value);
            const retail = parseFloat(retailInput.value);

            if (Number.isNaN(cost) || Number.isNaN(retail)) {
                marginPreview.textContent = '-';
                marginPreview.classList.remove('text-danger', 'text-success');
                return;
            }

            const margin = retail - cost;
            const percent = cost > 0 ? ' (' + ((margin / cost) * 100).toFixed(1) + '% markup)' : '';

            marginPreview.textContent = 'RM ' + margin.toFixed(2) + percent;
            marginPreview.classList.toggle('text-danger', margin < 0);
            marginPreview.classList.toggle('text-success', margin >= 0);
        }

        costInput.addEventListener('input', updateMargin);
        retailInput.addEventListener('input', updateMargin);
        updateMargin();
    </script>
@endpush
