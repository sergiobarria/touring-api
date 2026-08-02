<?php

namespace App\Actions\Tours;

use App\Http\Requests\Api\V1\UpdateTourRequest;
use App\Models\Tour;
use App\Services\Tours\TourGuideService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UpdateTour
{
    public function __construct(private TourGuideService $guideTeams) {}

    /**
     * @throws Throwable
     */
    public function handle(string $tourId, UpdateTourRequest $request): Tour
    {
        $tour = DB::transaction(function () use ($tourId, $request): Tour {
            $tour = Tour::query()->lockForUpdate()->findOrFail($tourId);
            $attributes = $request->toDto($tour)->toArray();

            if ($request->has('max_group_size') && $tour->startDates()
                ->whereRaw('available_spots + reserved_spots > ?', [$attributes['max_group_size']])
                ->exists()) {
                throw ValidationException::withMessages([
                    'max_group_size' => 'The maximum group size must not be less than available and reserved spots on an existing start date.',
                ]);
            }

            $tour->update($attributes);

            if ($request->has('guide_ids')) {
                $this->guideTeams->sync($tour, $request->validated('guide_ids'));
            }

            return $tour->refresh();
        });

        return $tour->load(['media', 'upcomingStartDates', 'leadGuide:id,name', 'guides:id,name']);
    }
}
