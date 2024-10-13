<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:tours',
            'price' => 'required|regex:/^\d+(\.\d{1,2})?$/',
            'duration' => 'required|integer|min:1|max:30',
            'max_group_size' => 'required|integer|min:1|max:30',
            'difficulty' => 'required|in:easy,medium,difficult',
            'rating' => 'sometimes|numeric|min:0|max:5',
            'ratings_quantity' => 'sometimes|integer|min:0',
            'summary' => 'required|string',
            'description' => 'sometimes|string',
            'is_public' => 'sometimes|boolean',
            'start_dates' => 'required|array',
            'start_dates.*' => 'required|date_format:Y-m-d',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A Tour with this name already exists.',
            'price.regex' => 'Price must be a number with up to 2 decimal places. Like: 100.00, 100, 100.1, 100.12',
        ];
    }
}
