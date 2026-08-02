<?php

namespace App\Services\Tours;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Throwable;

final readonly class TourGuideService
{
    /**
     * Replace a tour's supporting guides and record the relationship change.
     *
     * @param  list<string>  $guideIds
     *
     * @throws Throwable
     */
    public function sync(Tour $tour, array $guideIds): void
    {
        $oldGuideIds = $tour->guides()->pluck('users.id')->sort()->values()->all();
        $newGuideIds = collect($guideIds)->sort()->values()->all();

        if ($oldGuideIds === $newGuideIds) {
            return;
        }

        $tour->guides()->sync($newGuideIds);

        if (! config('audit.enabled', true)) {
            return;
        }

        $user = Auth::user();
        $request = app()->bound('request') ? app(Request::class) : null;

        Audit::query()->create([
            'user_type' => $user?->getMorphClass(),
            'user_id' => $user?->getAuthIdentifier(),
            'event' => 'updated',
            'auditable_type' => $tour->getMorphClass(),
            'auditable_id' => $tour->getKey(),
            'old_values' => ['guide_ids' => $oldGuideIds],
            'new_values' => ['guide_ids' => $newGuideIds],
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => ($userAgent = $request?->userAgent()) !== null
                ? Str::limit($userAgent, 1023, '')
                : null,
            'tags' => 'guide-assignments',
        ]);
    }
}
