<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchases.create') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('proveedor')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:11', Rule::unique('proveedores', 'ruc')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ruc') && $this->ruc === '') {
            $this->merge(['ruc' => null]);
        }
        if ($this->has('email') && $this->email === '') {
            $this->merge(['email' => null]);
        }
        if ($this->has('phone') && $this->phone === '') {
            $this->merge(['phone' => null]);
        }
    }
}
