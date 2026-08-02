<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TourDifficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.slug' => ['sometimes', 'string', 'max:255'],
            'filter.difficulty' => ['sometimes', Rule::enum(TourDifficulty::class)],
            'filter.duration_days' => ['sometimes', 'integer', 'min:1'],
            'filter.max_group_size' => ['sometimes', 'integer', 'min:1'],
            'filter.min_price' => ['sometimes', 'numeric', 'min:0'],
            'filter.max_price' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get the after validation callables for the request.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $minimumPrice = $this->input('filter.min_price');
                $maximumPrice = $this->input('filter.max_price');

                if (
                    is_numeric($minimumPrice)
                    && is_numeric($maximumPrice)
                    && (float) $maximumPrice < (float) $minimumPrice
                ) {
                    $validator->errors()->add(
                        'filter.max_price',
                        'The maximum price must be greater than or equal to the minimum price.',
                    );
                }
            },
        ];
    }
}
