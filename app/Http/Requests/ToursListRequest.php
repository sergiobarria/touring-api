<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToursListRequest extends FormRequest
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
            'priceFrom' => 'nullable|numeric|min:0',
            'priceTo' => 'nullable|numeric|min:0',
            'sortBy' => 'nullable|string|in:name,price',
            'sortOrder' => 'nullable|string|in:asc,desc',
            'include' => 'nullable|string|in:start_dates',
            'fields' => 'nullable|string|in:id,name,slug,price,duration,max_group_size,rating,ratings_quantity,summary,description,start_dates',
        ];
    }

    public function messages(): array
    {
        return [
            'sortBy.in' => 'The sort by must be one of the following: name, price.',
            'sortOrder.in' => 'The sort order must be one of the following: asc, desc.',
        ];
    }
}
