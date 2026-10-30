<?php

namespace App\Http\Requests;

use App\Rules\AmountRules;
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
        $product = $this->route('product');

        return [
            'currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                Rule::notIn([$product->currency_id]),
                Rule::unique('product_prices', 'currency_id')->where('product_id', $product->id),
            ],
            'price' => ['nullable', ...AmountRules::currencyPrice()],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.not_in' => "The price currency must be different from the product's base currency.",
            'currency_id.unique' => 'This product already has a price registered in that currency.',
        ];
    }
}
