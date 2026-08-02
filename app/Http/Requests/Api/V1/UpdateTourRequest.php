<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\TourData;
use App\Enums\TourDifficulty;
use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTourRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'max_group_size' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'difficulty' => ['sometimes', Rule::enum(TourDifficulty::class)],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'price_discount_percent' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'summary' => ['sometimes', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $body = $this->body();

            foreach (array_diff(array_keys($body), TourData::WRITABLE_FIELDS) as $field) {
                $validator->errors()->add($field, "The {$field} field is not supported.");
            }

            if (array_intersect(array_keys($body), TourData::WRITABLE_FIELDS) === []) {
                $validator->errors()->add('request', 'At least one writable tour field is required.');
            }
        }];
    }

    public function toDto(Tour $tour): TourData
    {
        return TourData::forUpdate($tour, $this->validated());
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }
}
