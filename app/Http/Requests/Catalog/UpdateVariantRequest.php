<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('variante')?->id;

        return [
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('variantes', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('variantes', 'barcode')->ignore($id)],
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
