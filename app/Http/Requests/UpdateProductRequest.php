<?php

namespace App\Http\Requests;

use App\Enums\PriceOrigin;
use App\Rules\AmountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $changesCurrency = (string) $this->input('currency_id') !== (string) $product->currency_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', ...AmountRules::money()],
            'currency_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:currencies,id',
                Rule::when($changesCurrency, fn () => [
                    Rule::unique('product_prices', 'currency_id')
                        ->where('product_id', $product->id)
                        ->where('origin', PriceOrigin::Manual->value),
                ]),
            ],
            'tax_cost' => ['sometimes', 'required', ...AmountRules::money()],
            'manufacturing_cost' => ['sometimes', 'required', ...AmountRules::money()],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.unique' => 'This product has a manual price in that currency. Delete that price before using it as the base currency.',
        ];
    }
}
