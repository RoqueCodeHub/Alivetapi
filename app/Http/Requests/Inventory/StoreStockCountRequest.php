<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.count') ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
