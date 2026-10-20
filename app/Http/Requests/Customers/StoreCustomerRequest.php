<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') || $this->user()?->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:DNI,RUC,CE,PASAPORTE'],
            'document_number' => ['nullable', 'string', 'max:20', 'unique:personas,numero_documento'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('phone') && $this->filled('whatsapp')) {
            $this->merge(['phone' => $this->whatsapp]);
        }
        foreach (['document_number', 'phone'] as $field) {
            if ($this->has($field) && $this->$field === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
