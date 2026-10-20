<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') || $this->user()?->can('reports.view_all') || false;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'format' => ['nullable', 'in:csv,xlsx,pdf'],
            'report' => ['nullable', 'in:sales-by-day,top-products,by-category,by-cashier,profit,slow-movers,inventory-value'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['category_id', 'cashier_id', 'from', 'to'] as $field) {
            if ($this->has($field) && $this->$field === '') {
                $clean[$field] = null;
            }
        }
        if ($clean !== []) {
            $this->merge($clean);
        }
    }
}
