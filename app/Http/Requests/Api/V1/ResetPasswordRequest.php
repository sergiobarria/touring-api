<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ResetPasswordRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = ['email', 'token', 'password', 'password_confirmation'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'token' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', Password::defaults()], 'password_confirmation' => ['required', 'string']];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS)];
    }
}
