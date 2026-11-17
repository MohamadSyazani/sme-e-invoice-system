<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Module 2: rules for adding a hardware product.
 * UpdateProductRequest extends this class and only changes the SKU uniqueness rule.
 */
class StoreProductRequest extends FormRequest
{
    /** Access is already restricted to OWNER by the route's role middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /** Tidy the input before the rules run: "hw-cem-50kg " becomes "HW-CEM-50KG". */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9.\-]*$/', $this->uniqueSkuRule()],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_type' => ['required', Rule::in(Product::UNIT_TYPES)],
            'supplier_cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            // Validation Rule: the shop must never sell below cost by accident.
            'retail_selling_price' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'gte:supplier_cost_price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, dashes and dots (for example HW-CEM-50KG).',
            'sku.unique' => 'Another product already uses this SKU.',
            'retail_selling_price.gte' => 'The retail selling price cannot be lower than the supplier cost price.',
        ];
    }

    protected function uniqueSkuRule(): mixed
    {
        return Rule::unique('products', 'sku');
    }
}
