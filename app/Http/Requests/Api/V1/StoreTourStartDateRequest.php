<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\TourStartDateData;
use App\Http\Requests\Api\V1\Concerns\ValidatesTourStartDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTourStartDateRequest extends FormRequest
{
    use ValidatesTourStartDate;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'start_datetime_utc' => [
                'required',
                'string',
                'date',
                'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
            ],
            'available_spots' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnsupportedFields($validator);
            $this->validateStartDateInvariants($validator);
        }];
    }

    public function toDto(): TourStartDateData
    {
        return TourStartDateData::forCreate($this->validated());
    }
}
