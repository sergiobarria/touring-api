<?php

namespace App\Actions\Auth;

use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResetUserPassword
{
    public function handle(ResetPasswordRequest $request): void
    {
        $status = Password::reset($request->safe()->only(['email', 'password', 'password_confirmation', 'token']), function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
            });
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => trans($status)]);
        }
    }
}
