<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:100', 'unique:variantes,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:variantes,barcode'],
            'presentation' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'sale_unit' => ['nullable', 'string', 'max:50'],
            'units_per_box' => ['nullable', 'integer', 'min:1'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'needs_review' => ['sometimes', 'boolean'],
            'review_note' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
