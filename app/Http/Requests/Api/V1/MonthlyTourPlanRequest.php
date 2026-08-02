<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MonthlyTourPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            /** @ignoreParam */
            'year' => ['required', 'regex:/^\d{4}$/', 'integer', 'between:1000,9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->route('year'),
        ]);
    }
}
