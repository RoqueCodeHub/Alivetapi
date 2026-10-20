<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') || $this->user()?->can('sales.create');
    }

    public function rules(): array
    {
        $personaId = $this->route('cliente')?->persona_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type' => ['nullable', 'in:DNI,RUC,CE,PASAPORTE'],
            'document_number' => ['nullable', 'string', 'max:20', Rule::unique('personas', 'numero_documento')->ignore($personaId)],
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
    }
}
