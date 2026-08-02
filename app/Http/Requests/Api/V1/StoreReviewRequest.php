<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\ReviewData;
use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('review') && is_string($this->input('review'))) {
            $this->merge(['review' => trim($this->string('review')->toString())]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnsupportedFields($validator, ReviewData::WRITABLE_FIELDS);

            if ($this->user()?->reviews()->whereBelongsTo($this->route('tour'))->exists()) {
                $validator->errors()->add('review', 'You have already reviewed this tour.');
            }
        }];
    }

    public function toDto(): ReviewData
    {
        return ReviewData::forCreate($this->validated());
    }
}
