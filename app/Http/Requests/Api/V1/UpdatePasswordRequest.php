<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = ['current_password', 'password', 'password_confirmation'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'current_password'], 'password' => ['required', 'string', 'confirmed', Password::defaults()], 'password_confirmation' => ['required', 'string']];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS)];
    }
}
