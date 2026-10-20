<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchases.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:11', 'unique:proveedores,ruc'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc' => $this->ruc === '' ? null : $this->ruc,
            'email' => $this->email === '' ? null : $this->email,
            'phone' => $this->phone === '' ? null : $this->phone,
        ]);
    }
}
