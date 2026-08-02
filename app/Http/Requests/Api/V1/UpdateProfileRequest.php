<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = ['name', 'email', 'current_password'];

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
        /** @var User $user */
        $user = $this->user();
        $emailChanged = $this->has('email') && $this->input('email') !== $user->email;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'required_without:email'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'current_password' => $emailChanged
                ? ['required', 'string', 'current_password']
                : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS),
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'email'])) {
                    $validator->errors()->add('profile', 'At least one profile field must be provided.');
                }
            },
        ];
    }
}
