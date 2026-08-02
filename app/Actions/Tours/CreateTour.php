<?php

namespace App\Actions\Tours;

use App\DataTransferObjects\TourData;
use App\Models\Tour;
use App\Services\Tours\TourGuideService;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreateTour
{
    public function __construct(private TourGuideService $guideTeams) {}

    /**
     * @param  list<string>  $guideIds
     *
     * @throws Throwable
     */
    public function handle(TourData $data, array $guideIds): Tour
    {
        $tour = DB::transaction(function () use ($data, $guideIds): Tour {
            $tour = Tour::create($data->toArray());
            $this->guideTeams->sync($tour, $guideIds);

            return $tour;
        });

        return $tour->refresh()->load([
            'media',
            'upcomingStartDates',
            'leadGuide:id,name',
            'guides:id,name',
        ]);
    }
}
