<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'currency_id' => ['sometimes', 'required', 'integer', 'exists:currencies,id'],
            'tax_cost' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'manufacturing_cost' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }
}
