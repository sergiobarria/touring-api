<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\ReviewData;
use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReviewRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    public function authorize(): bool
    {
        $tour = $this->route('tour');

        if (! $tour instanceof Tour) {
            return false;
        }

        $review = $tour->reviews()->findOrFail((string) $this->route('review'));

        return $this->user()?->can('update', $review) === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('review') && is_string($this->input('review'))) {
            $this->merge(['review' => trim($this->string('review')->toString())]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'review' => ['sometimes', 'required', 'string', 'max:2000'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnsupportedFields($validator, ReviewData::WRITABLE_FIELDS);

            if ($this->requestBodyIsEmpty()) {
                $validator->errors()->add('review', 'At least one review field is required.');
            }
        }];
    }

    private function requestBodyIsEmpty(): bool
    {
        return count($this->isJson() ? $this->json()->all() : $this->request->all()) === 0;
    }
}
