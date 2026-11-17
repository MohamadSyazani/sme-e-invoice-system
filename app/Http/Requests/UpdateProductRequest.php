<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Module 2: same rules as adding a product, except the SKU may stay the same
 * on the product being edited.
 */
class UpdateProductRequest extends StoreProductRequest
{
    protected function uniqueSkuRule(): mixed
    {
        return Rule::unique('products', 'sku')->ignore($this->route('product'));
    }
}
