<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('marca')?->id;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('marcas', 'nombre')->ignore($id)],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
