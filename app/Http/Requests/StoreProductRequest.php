<?php

namespace App\Http\Requests;

use App\Rules\AmountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseCurrency = $this->input('currency_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', ...AmountRules::money()],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'tax_cost' => ['required', ...AmountRules::money()],
            'manufacturing_cost' => ['required', ...AmountRules::money()],
            'prices' => ['sometimes', 'array', 'max:20'],
            'prices.*' => ['required', 'array:currency_id,price'],
            'prices.*.currency_id' => [
                'required',
                'integer',
                'distinct',
                'exists:currencies,id',
                Rule::when(is_scalar($baseCurrency), fn () => [Rule::notIn([$baseCurrency])]),
            ],
            'prices.*.price' => ['nullable', ...AmountRules::currencyPrice()],
        ];
    }

    public function messages(): array
    {
        return [
            'prices.*.currency_id.not_in' => "The price currency must be different from the product's base currency.",
            'prices.*.currency_id.distinct' => 'Each price must use a different currency.',
        ];
    }
}
