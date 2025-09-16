<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StoreTourDateData;
use App\Data\TourDateData;
use App\Data\UpdateTourDateData;
use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDate;
use Dedoc\Scramble\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

#[Group('Tour Dates')]
class TourDateController extends Controller
{
    /** List tour dates */
    public function index(Tour $tour)
    {
        return TourDateData::collect($tour->dates()->get());
    }

    /** Get tour date */
    public function show(Tour $tour, TourDate $date)
    {
        $this->checkIfDateBelongsToTour($tour, $date);
        return TourDateData::from($date);
    }


    /** Verify the date to be modified belongs to the tour. */
    private function checkIfDateBelongsToTour(Tour $tour, TourDate $date)
    {
        if ($date->tour_id !== $tour->id) {
            abort(Response::HTTP_NOT_FOUND, 'Tour Date not found for this Tour.');
        }
    }

    /** Create tour date */
    public function store(StoreTourDateData $data, Tour $tour)
    {
        $tourDate = $tour->dates()->create($data->toArray());

        return TourDateData::from($tourDate);
    }

    /** Update tour date */
    public function update(UpdateTourDateData $data, Tour $tour, TourDate $date)
    {
        $this->checkIfDateBelongsToTour($tour, $date);
        $date->update($data->toArray());

        return TourDateData::from($date);
    }

    /** Delete tour date */
    public function destroy(Tour $tour, TourDate $date)
    {
        $this->checkIfDateBelongsToTour($tour, $date);
        $date->delete();

        return response()->noContent();
    }
}
