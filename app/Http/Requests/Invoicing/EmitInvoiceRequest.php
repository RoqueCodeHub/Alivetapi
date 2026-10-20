<?php

namespace App\Http\Requests\Invoicing;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoicing.emit') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Invoice::TYPE_BOLETA, Invoice::TYPE_FACTURA])],
            'customer_doc_type' => ['nullable', 'in:DNI,RUC,CE,PASAPORTE'],
            'customer_doc_number' => ['nullable', 'string', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
