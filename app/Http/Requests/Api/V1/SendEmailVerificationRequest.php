<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SendEmailVerificationRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = [];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS)];
    }
}
