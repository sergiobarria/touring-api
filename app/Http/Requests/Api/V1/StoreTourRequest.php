<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\TourData;
use App\Enums\TourDifficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTourRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:255'],
            'max_group_size' => ['required', 'integer', 'min:1', 'max:255'],
            'difficulty' => ['required', Rule::enum(TourDifficulty::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'price_discount_percent' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator)];
    }

    public function toDto(): TourData
    {
        return TourData::forCreate($this->validated());
    }

    private function rejectUnsupportedFields(Validator $validator): void
    {
        foreach (array_diff(array_keys($this->body()), TourData::WRITABLE_FIELDS) as $field) {
            $validator->errors()->add($field, "The {$field} field is not supported.");
        }
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }
}
