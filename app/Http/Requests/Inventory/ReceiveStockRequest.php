<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'exists:productos,id'],
            'variant_id' => ['nullable', 'exists:variantes,id'],
            'presentation' => ['nullable', 'string', 'max:150'],
            'batch_code' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
