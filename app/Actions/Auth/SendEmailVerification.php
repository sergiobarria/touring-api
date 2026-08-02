<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;

final readonly class SendEmailVerification
{
    public function __construct(private EmailVerificationService $emailVerification) {}

    public function handle(User $user): void
    {
        $this->emailVerification->send($user);
    }
}
