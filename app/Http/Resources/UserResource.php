<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin User */
class UserResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    /**
     * The resource's attributes.
     */
    public array $attributes = [
        'name',
        'email',
    ];

    public function toType(Request $request): string
    {
        return 'users';
    }
}
