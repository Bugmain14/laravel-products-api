<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'name')->ignore($this->route('currency')),
            ],
            'symbol' => ['sometimes', 'required', 'string', 'max:10'],
            'exchange_rate' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999999.999999', 'decimal:0,6'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The name must be a 3-letter ISO 4217 code.',
        ];
    }
}
