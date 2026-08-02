<?php

namespace App\Actions\Auth;

use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserPassword
{
    public function handle(User $user, UpdatePasswordRequest $request): void
    {
        DB::transaction(function () use ($user, $request): void {
            $user->update(['password' => (string) $request->string('password')]);
            $user->tokens()->whereKeyNot($user->currentAccessToken()?->getKey())->delete();
        });
    }
}
