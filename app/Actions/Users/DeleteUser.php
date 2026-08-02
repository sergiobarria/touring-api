<?php

namespace App\Actions\Users;

use App\Enums\UserPermission;
use App\Models\Tour;
use App\Models\User;
use App\Services\Tours\TourRatingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class DeleteUser
{
    public function __construct(private TourRatingService $ratings) {}

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

        if ($user->bookings()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'Users with booking history cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $tourIds = $user->reviews()->select('tour_id')->distinct()->pluck('tour_id')->sort()->values();

            foreach ($tourIds as $tourId) {
                $tour = Tour::withTrashed()->lockForUpdate()->findOrFail($tourId);
                $user->reviews()->where('tour_id', $tourId)->delete();
                $this->ratings->recompute($tour);
            }

            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->roles()->detach();
            $user->delete();
        });
    }
}
