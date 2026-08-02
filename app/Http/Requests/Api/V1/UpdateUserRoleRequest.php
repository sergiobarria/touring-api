<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateUserRoleRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    private const array SUPPORTED_FIELDS = ['role'];

    public function authorize(): bool
    {
        return $this->user()?->can(UserPermission::UPDATE_ROLE->value) === true;
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(UserRole::class)]];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields($validator, self::SUPPORTED_FIELDS)];
    }
}
