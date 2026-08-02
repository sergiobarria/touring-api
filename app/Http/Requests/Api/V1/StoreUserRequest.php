<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class StoreUserRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = ['name', 'email', 'password', 'password_confirmation', 'role'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS)];
    }
}
