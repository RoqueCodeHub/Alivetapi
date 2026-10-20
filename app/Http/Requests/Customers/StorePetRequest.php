<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StorePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') || $this->user()?->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'especie' => ['nullable', 'string', 'max:100'],
            'raza' => ['nullable', 'string', 'max:100'],
            'sexo' => ['nullable', 'in:macho,hembra,desconocido'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'alergias' => ['nullable', 'string'],
        ];
    }
}
