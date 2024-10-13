<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTourRequest extends FormRequest
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
            'name' => 'sometimes|string|unique:tours,name,' . $this->route('tour')->id, // ignore the current tour
            'price' => 'sometimes|regex:/^\d+(\.\d{1,2})?$/',
            'duration' => 'sometimes|integer|min:1|max:30',
            'max_group_size' => 'sometimes|integer|min:1|max:30',
            'difficulty' => 'sometimes|in:easy,medium,difficult',
            'rating' => 'sometimes|numeric|min:0|max:5',
            'ratings_quantity' => 'sometimes|integer|min:0',
            'summary' => 'sometimes|string',
            'description' => 'sometimes|string',
            'is_public' => 'sometimes|boolean',
            'start_dates' => 'sometimes|array',
            'start_dates.*' => 'sometimes|date_format:Y-m-d',
        ];
    }
}
