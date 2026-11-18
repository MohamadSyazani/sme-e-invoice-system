@php
    /** @var \App\Models\Product|null $product */
    $product = $product ?? null;
    $currentUnit = old('unit_type', $product?->unit_type ?? 'PIECE');
    $currentImage = $product?->imageUrl();
@endphp

{{-- enctype is required, otherwise the browser does not send the photo file. --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($product)
        @method('PUT')
    @endif

    {{-- Product photo + AI Photo Scanner --}}
    <div class="border rounded p-3 mb-4 bg-light">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-sm-auto">
                <img id="imagePreview" src="{{ $currentImage }}" alt="Product photo preview"
                     class="rounded border bg-white @unless ($currentImage) d-none @endunless"
                     style="width: 120px; height: 120px; object-fit: cover;">
                <div id="imagePlaceholder"
                     class="rounded border bg-white text-muted small align-items-center justify-content-center text-center
                            {{ $currentImage ? 'd-none' : 'd-flex' }}"
                     style="width: 120px; height: 120px;">No photo</div>
            </div>
            <div class="col-12 col-sm">
                <label for="image" class="form-label mb-1">Product photo <span class="text-muted">(optional)</span></label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('image') is-invalid @enderror">
                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">
                    On a phone this opens the camera. On a laptop, choose a JPG, PNG or WebP file.
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="button" id="scanButton" class="btn btn-dark btn-sm" disabled
                            data-url="{{ route('products.scan') }}">
                        <span class="spinner-border spinner-border-sm d-none" id="scanSpinner" aria-hidden="true"></span>
                        <span id="scanLabel">Scan with AI</span>
                    </button>
                    <span class="small text-muted" id="scanHint">Choose a photo first, then let AI fill in the form.</span>
                </div>

                @if ($product?->image_path)
                    <div class="form-check mt-2">
                        <input type="checkbox" id="remove_image" name="remove_image" value="1" class="form-check-input"
                               @checked(old('remove_image'))>
                        <label for="remove_image" class="form-check-label small">Remove the current photo</label>
                    </div>
                @endif
            </div>
        </div>
        <div id="scanStatus" class="alert py-2 mt-3 mb-0 d-none" role="status" aria-live="polite"></div>
    </div>

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
        // ---------------------------------------------------------------
        // Product photo + AI Photo Scanner
        // ---------------------------------------------------------------
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const scanButton = document.getElementById('scanButton');
        const scanSpinner = document.getElementById('scanSpinner');
        const scanLabel = document.getElementById('scanLabel');
        const scanHint = document.getElementById('scanHint');
        const scanStatus = document.getElementById('scanStatus');

        function showScanStatus(type, message) {
            scanStatus.className = 'alert py-2 mt-3 mb-0 alert-' + type;
            scanStatus.textContent = message;
        }

        // Phone photos are often 5 to 10 MB. Shrink them in the browser to at most
        // 1600px JPEG so uploads are fast and stay under the server's size limit.
        async function shrinkPhoto(file) {
            const bitmap = await createImageBitmap(file);
            const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));

            if (scale === 1 && file.size <= 1024 * 1024) {
                return file; // already small enough
            }

            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

            return new File([blob], name, { type: 'image/jpeg' });
        }

        imageInput.addEventListener('change', async () => {
            let file = imageInput.files[0];

            if (! file) {
                scanButton.disabled = true;
                return;
            }

            try {
                const small = await shrinkPhoto(file);

                if (small !== file) {
                    // Put the smaller photo back into the file input, so the form submits it too.
                    const transfer = new DataTransfer();
                    transfer.items.add(small);
                    imageInput.files = transfer.files;
                    file = small;
                }
            } catch (error) {
                // Shrinking is only a convenience. If the browser cannot do it, keep the original photo.
            }

            imagePreview.src = URL.createObjectURL(file);
            imagePreview.classList.remove('d-none');
            imagePlaceholder.classList.replace('d-flex', 'd-none');

            const removeBox = document.getElementById('remove_image');
            if (removeBox) removeBox.checked = false;

            scanButton.disabled = false;
            scanHint.textContent = 'Photo ready. Click Scan with AI to fill in the form.';
            scanStatus.classList.add('d-none');
        });

        // Put one suggested value into a form field and mark it, so the Owner knows what to check.
        function fillField(id, value, { onlyIfEmpty = false } = {}) {
            const field = document.getElementById(id);

            if (! field || value === null || value === undefined || value === '') return false;
            if (onlyIfEmpty && field.value.trim() !== '') return false;

            field.value = value;
            field.classList.add('border-success', 'bg-success-subtle');
            field.addEventListener('input', () => field.classList.remove('border-success', 'bg-success-subtle'), { once: true });

            return true;
        }

        scanButton.addEventListener('click', async () => {
            const file = imageInput.files[0];
            if (! file) return;

            scanButton.disabled = true;
            scanSpinner.classList.remove('d-none');
            scanLabel.textContent = 'Scanning...';
            showScanStatus('info', 'Sending the photo to the AI scanner. This can take a few seconds.');

            const body = new FormData();
            body.append('photo', file);

            // Stop waiting after 30 seconds so the page never hangs.
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 30000);

            try {
                const response = await fetch(scanButton.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: body,
                    signal: controller.signal,
                });

                const data = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                    throw new Error(firstError || data.message || 'The AI scanner is unavailable right now. You can still fill in the form by hand.');
                }

                const s = data.suggestion || {};
                const filled = [
                    fillField('name', s.name),
                    fillField('category', s.category),
                    fillField('unit_type', s.unit_type),
                    fillField('description', s.description),
                    fillField('sku', s.sku, { onlyIfEmpty: true }), // never overwrite a SKU that is already typed
                ].filter(Boolean).length;

                if (filled === 0) {
                    showScanStatus('warning', 'The AI scanner could not suggest anything for this photo. Fill in the form by hand.');
                } else {
                    showScanStatus('success', 'AI filled in ' + filled + (filled === 1 ? ' field' : ' fields')
                        + ' (highlighted in green). Check them, then enter the prices and stock yourself.');
                }
            } catch (error) {
                showScanStatus('warning', error.name === 'AbortError'
                    ? 'The AI scanner took too long. You can still fill in the form by hand.'
                    : error.message);
            } finally {
                clearTimeout(timer);
                scanButton.disabled = false;
                scanSpinner.classList.add('d-none');
                scanLabel.textContent = 'Scan with AI';
            }
        });

        // ---------------------------------------------------------------
        // Live margin preview. The real check happens on the server in StoreProductRequest.
        // ---------------------------------------------------------------
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
