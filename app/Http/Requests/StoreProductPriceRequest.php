<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                Rule::unique('product_prices', 'currency_id')->where('product_id', $productId),
            ],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.unique' => 'This product already has a price registered in that currency.',
        ];
    }
}
