<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_id' => ['nullable', 'exists:clientes,id'],
            'pet_id' => ['nullable', 'exists:pets,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'series' => ['nullable', 'string', 'max:10'],
            'invoice_type' => ['nullable', 'in:boleta,factura'],
            'pending' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'in:product,service'],
            'items.*.variant_id' => ['exclude_if:items.*.item_type,service', 'required', 'exists:variantes,id'],
            'items.*.description' => ['exclude_unless:items.*.item_type,service', 'required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['exclude_if:pending,true', 'required_unless:pending,true', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:efectivo,yape,plin,tarjeta,transferencia'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
