<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class PaySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.charge') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_type' => ['nullable', 'in:boleta,factura'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:efectivo,yape,plin,tarjeta,transferencia'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
