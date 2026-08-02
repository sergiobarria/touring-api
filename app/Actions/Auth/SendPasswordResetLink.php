<?php

namespace App\Actions\Auth;

use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use Illuminate\Support\Facades\Password;
use Throwable;

final readonly class SendPasswordResetLink
{
    public function handle(ForgotPasswordRequest $request): void
    {
        try {
            Password::sendResetLink(['email' => (string) $request->string('email')]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
