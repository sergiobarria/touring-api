<?php

namespace App\Http\Requests;

use App\Models\Tour;
use App\Rules\CommaSeparatedValues;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourListRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', 'array'],
            'filter.name' => ['nullable', 'string', 'max:255'],
            'filter.slug' => ['nullable', 'string', 'max:255'],
            'filter.difficulty' => ['nullable', 'string', Rule::in(['easy', 'moderate', 'difficult'])],
            'filter.duration_days' => ['nullable', 'integer', 'min:1'],
            'filter.max_group_size' => ['nullable', 'integer', 'min:1'],
            'filter.min_price' => ['nullable', 'integer', 'min:1'],
            'filter.max_price' => ['nullable', 'integer', 'min:1'],

            'sort' => ['nullable', 'string', Rule::in(Tour::ALLOWED_SORTS)],

            'fields' => ['nullable', 'string', new CommaSeparatedValues(Tour::ALLOWED_SELECT_FIELDS)],

            'include' => ['nullable', 'string', new CommaSeparatedValues(Tour::ALLOWED_INCLUDES)],
        ];
    }
}
