<?php

namespace App\Http\Requests;

use App\Rules\AmountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::upper(trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'unique:currencies,name'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', ...AmountRules::exchangeRate()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The name must be a 3-letter ISO 4217 code.',
        ];
    }
}
