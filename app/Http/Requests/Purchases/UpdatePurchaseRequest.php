<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchases.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'exists:proveedores,id'],
            'comprobante' => ['nullable', 'string', 'max:100'],
            'fecha' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.variant_id' => ['required_with:items', 'exists:variantes,id'],
            'items.*.batch_code' => ['required_with:items', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.cost' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
