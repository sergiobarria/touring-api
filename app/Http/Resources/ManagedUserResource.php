<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin User */
final class ManagedUserResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->roles->sole()->name,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }

    public function toType(Request $request): string
    {
        return 'users';
    }
}
