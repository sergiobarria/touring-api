<?php

namespace App\Actions\Users;

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class DeleteUser
{
    /**
     * @throws Throwable
     */
    public function handle(string $userId): void
    {
        Gate::authorize(UserPermission::DELETE->value);
        $user = User::query()->findOrFail($userId);
        Gate::authorize('delete', $user);

        if ($user->leadTours()->exists() || $user->supportingTours()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Assigned tour guides must be replaced or removed before deletion.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->roles()->detach();
            $user->delete();
        });
    }
}
