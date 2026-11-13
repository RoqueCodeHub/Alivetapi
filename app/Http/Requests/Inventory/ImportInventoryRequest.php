<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ImportInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $extension = strtolower((string) $value?->getClientOriginalExtension());
                    if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xlsm'], true)) {
                        $fail('El archivo debe ser Excel (.xlsx) o CSV.');
                    }
                },
            ],
        ];
    }
}
