<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\TourData;
use App\Enums\TourDifficulty;
use App\Enums\TourPermission;
use App\Http\Requests\Api\V1\Concerns\ValidatesTourGuides;
use App\Models\Tour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTourRequest extends FormRequest
{
    use ValidatesTourGuides;

    public function authorize(): bool
    {
        return $this->user()?->can(TourPermission::UPDATE->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'lead_guide_id' => ['sometimes', 'string', 'ulid', Rule::exists('users', 'id')],
            'guide_ids' => ['sometimes', 'array', 'max:'.Tour::MAX_SUPPORTING_GUIDES],
            'guide_ids.*' => ['required', 'string', 'ulid', 'distinct', Rule::exists('users', 'id')],
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

            $this->validateGuideRoles($validator);

            if ($validator->errors()->has('max_group_size') || ! array_key_exists('max_group_size', $body)) {
                return;
            }

            $tour = $this->route('tour');

            if (! $tour instanceof Tour) {
                $tour = Tour::find($tour);
            }

            if ($tour?->startDates()->where('available_spots', '>', $body['max_group_size'])->exists()) {
                $validator->errors()->add(
                    'max_group_size',
                    'The maximum group size must not be less than available spots on an existing start date.',
                );
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
