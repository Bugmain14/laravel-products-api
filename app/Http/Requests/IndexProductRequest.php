<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sortable = collect(Product::SORTABLE)
            ->flatMap(fn (string $field) => [$field, "-{$field}"])
            ->all();

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'sort' => ['nullable', 'string', Rule::in($sortable)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
